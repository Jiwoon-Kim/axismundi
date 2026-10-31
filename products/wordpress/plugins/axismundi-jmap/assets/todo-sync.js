/* Shared by the admin client and its dependency-free synchronization tests. */
(function (root, factory) {
    const Client = factory();
    if (typeof module === 'object' && module.exports) module.exports = Client;
    else root.AxTodoSync = Client;
})(globalThis, function () {
    'use strict';
    const types = ['TodoList', 'Todo'];
    const copy = value => JSON.parse(JSON.stringify(value));
    const invalid = () => Object.assign(new Error('Invalid synchronization response.'), {type: 'invalidResponse'});
    return class TodoSync {
        constructor(call) { this.call = call; this.reset(); }
        reset() { this.account = ''; this.states = {}; this.records = {TodoList: new Map(), Todo: new Map()}; }
        snapshot() {
            return {states: {...this.states}, lists: copy([...this.records.TodoList.values()]), todos: copy([...this.records.Todo.values()])};
        }
        async fetchAll(type, account) {
            const result = await this.call(type + '/get', {accountId: account, ids: null});
            if (typeof result.state !== 'string' || !Array.isArray(result.list)) throw invalid();
            return {state: result.state, records: new Map(result.list.map(item => [item.id, item]))};
        }
        async load(account) {
            const loaded = {};
            for (const type of types) loaded[type] = await this.fetchAll(type, account);
            this.account = account;
            for (const type of types) {
                this.states[type] = loaded[type].state;
                this.records[type] = loaded[type].records;
            }
            return true;
        }
        async delta(type, account) {
            if (typeof this.states[type] !== 'string') return this.fetchAll(type, account);
            let token = this.states[type];
            const records = new Map(this.records[type]);
            for (let page = 0; page < 100; page++) {
                let change;
                try { change = await this.call(type + '/changes', {accountId: account, sinceState: token}); }
                catch (error) {
                    if (['cannotCalculateChanges', 'tooManyChanges'].includes(error.type)) return this.fetchAll(type, account);
                    throw error;
                }
                if (change.oldState !== token || typeof change.newState !== 'string' || typeof change.hasMoreChanges !== 'boolean'
                    || !['created', 'updated', 'destroyed'].every(key => Array.isArray(change[key]) && change[key].every(id => typeof id === 'string'))) throw invalid();
                const ids = [...new Set([...change.created, ...change.updated])];
                if (ids.length) {
                    let size = 256;
                    for (let offset = 0; offset < ids.length;) {
                        const batch = ids.slice(offset, offset + size);
                        let result;
                        try { result = await this.call(type + '/get', {accountId: account, ids: batch}); }
                        catch (error) {
                            if (error.type !== 'requestTooLarge' || batch.length === 1) throw error;
                            size = Math.max(1, Math.floor(batch.length / 2));
                            continue;
                        }
                        if (!Array.isArray(result.list) || !Array.isArray(result.notFound)) throw invalid();
                        for (const id of result.notFound) records.delete(id);
                        for (const item of result.list) {
                            if (!batch.includes(item.id)) throw invalid();
                            records.set(item.id, item);
                        }
                        offset += batch.length;
                    }
                    // Keep the delta token: this /get may already reflect a later revision.
                }
                for (const id of change.destroyed) records.delete(id);
                if (change.hasMoreChanges && token === change.newState) throw invalid();
                token = change.newState;
                if (!change.hasMoreChanges) return {state: token, records};
            }
            throw invalid();
        }
        async sync(account) {
            if (account !== this.account) return this.load(account);
            const staged = {};
            for (const type of types) staged[type] = await this.delta(type, account);
            let changed = false;
            for (const type of types) {
                changed = changed || JSON.stringify([...this.records[type]]) !== JSON.stringify([...staged[type].records]);
                this.records[type] = staged[type].records;
                this.states[type] = staged[type].state;
            }
            return changed;
        }
    };
});
