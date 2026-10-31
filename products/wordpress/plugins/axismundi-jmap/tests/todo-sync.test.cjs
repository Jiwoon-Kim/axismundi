const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const Sync = require('../assets/todo-sync.js');
const source = fs.readFileSync(path.join(__dirname, '../assets/todo-sync.js'), 'utf8');
function clientCall() {
    const calls = [];
    const call = async (method, args) => {
        calls.push({method, args});
        const type = method.split('/')[0];
        if (method.endsWith('/get')) return {state: 's0', list: type === 'TodoList' ? [{id: 'l1', name: 'List'}] : [{id: 't1', title: 'Original'}], notFound: []};
        return {oldState: args.sinceState, newState: args.sinceState, hasMoreChanges: false, created: [], updated: [], destroyed: []};
    };
    return {calls, call};
}
async function unchanged(Client) {
    const {calls, call} = clientCall();
    const client = new Client(call);
    await client.load('a1'); calls.length = 0;
    assert.equal(await client.sync('a1'), false, 'Unchanged sync must report no update');
    assert.deepEqual(calls.map(c => c.method), ['TodoList/changes', 'Todo/changes'], 'Unchanged sync must use changes only');
    assert.ok(calls.every(c => c.args.sinceState === 's0'), 'Both saved tokens must be reused');
}
test('unchanged manual synchronization reuses both tokens without full gets', () => unchanged(Sync));
test('delta changes fetch only changed IDs and remove destroyed records', async () => {
    const base = clientCall();
    let delta = false;
    const client = new Sync(async (method, args) => {
        if (!delta) return base.call(method, args);
        base.calls.push({method, args});
        if (method === 'Todo/changes') return {oldState: 's0', newState: 's1', hasMoreChanges: false, created: ['t2'], updated: [], destroyed: ['t1']};
        if (method === 'Todo/get') { assert.deepEqual(args.ids, ['t2']); return {state: 's2', list: [{id: 't2', title: 'Added'}], notFound: []}; }
        return {oldState: 's0', newState: 's0', hasMoreChanges: false, created: [], updated: [], destroyed: []};
    });
    await client.load('a1'); delta = true;
    assert.equal(await client.sync('a1'), true);
    assert.deepEqual(client.snapshot().todos, [{id: 't2', title: 'Added'}]);
    assert.equal(client.snapshot().states.Todo, 's1', 'Later get state must not skip unseen changes');
});
test('expired ACL state refetches only the affected type', async () => {
    const base = clientCall();
    const client = new Sync(async (method, args) => {
        if (method === 'Todo/changes') throw Object.assign(new Error('ACL changed'), {type: 'cannotCalculateChanges'});
        return base.call(method, args);
    });
    await client.load('a1'); base.calls.length = 0;
    assert.equal(await client.sync('a1'), false);
    assert.deepEqual(base.calls.map(c => c.method), ['TodoList/changes', 'Todo/get']);
});
test('revocation aborts the staged snapshot instead of applying half a sync', async () => {
    const base = clientCall();
    let revoke = false;
    const client = new Sync(async (method, args) => {
        if (revoke && method === 'TodoList/changes') return {oldState: 's0', newState: 's1', hasMoreChanges: false, created: [], updated: [], destroyed: ['l1']};
        if (revoke && method === 'Todo/changes') throw Object.assign(new Error('Revoked'), {type: 'accountNotFound'});
        return base.call(method, args);
    });
    await client.load('a1'); const before = client.snapshot(); revoke = true;
    await assert.rejects(client.sync('a1'), {type: 'accountNotFound'});
    assert.deepEqual(client.snapshot(), before);
});
test('changing Account performs a clean initial fetch with isolated tokens', async () => {
    const base = clientCall(); const client = new Sync(base.call);
    await client.load('a1'); base.calls.length = 0;
    assert.equal(await client.sync('a2'), true);
    assert.ok(base.calls.every(c => c.args.accountId === 'a2' && c.method.endsWith('/get')));
});
test('non-advancing pagination is rejected', async () => {
    const base = clientCall();
    const client = new Sync(async (method, args) => method.endsWith('/changes') ? {oldState: args.sinceState, newState: args.sinceState, hasMoreChanges: true, created: [], updated: [], destroyed: []} : base.call(method, args));
    await client.load('a1'); await assert.rejects(client.sync('a1'), {type: 'invalidResponse'});
});
test('large deltas are fetched in bounded ID batches', async () => {
    const base = clientCall(); let updating = false;
    const ids = Array.from({length: 257}, (_, i) => 'new' + i), batches = [];
    const client = new Sync(async (method, args) => {
        if (!updating || method.startsWith('TodoList/')) return base.call(method, args);
        if (method.endsWith('/changes')) return {oldState: 's0', newState: 's1', hasMoreChanges: false, created: ids, updated: [], destroyed: []};
        batches.push(args.ids.length);
        assert.ok(args.ids.length <= 256);
        return {state: 's1', list: args.ids.map(id => ({id, title: id})), notFound: []};
    });
    await client.load('a1'); updating = true; await client.sync('a1');
    assert.deepEqual(batches, [256, 1]); assert.equal(client.snapshot().todos.length, 258);
});
test('a server with a smaller get limit shrinks the batch instead of retrying forever', async () => {
    const base = clientCall(); let updating = false;
    const client = new Sync(async (method, args) => {
        if (!updating || method.startsWith('TodoList/')) return base.call(method, args);
        if (method.endsWith('/changes')) return {oldState: 's0', newState: 's1', hasMoreChanges: false, created: ['a', 'b', 'c', 'd', 'e'], updated: [], destroyed: []};
        if (args.ids.length > 2) throw Object.assign(new Error('limit'), {type: 'requestTooLarge'});
        return {state: 's1', list: args.ids.map(id => ({id})), notFound: []};
    });
    await client.load('a1'); updating = true; assert.equal(await client.sync('a1'), true);
    assert.equal(client.snapshot().todos.length, 6);
});
test('mutant that forgets saved states fails the unchanged-sync assertion', async () => {
    const anchor = 'this.states[type] = loaded[type].state;';
    assert.equal(source.split(anchor).length, 2, 'Mutation anchor must occur exactly once');
    const sandbox = {module: {exports: {}}};
    vm.runInNewContext(source.replace(anchor, 'delete this.states[type];'), sandbox);
    await assert.rejects(unchanged(sandbox.module.exports), error => error.code === 'ERR_ASSERTION' && error.message.includes('Unchanged sync must use changes only'));
});
