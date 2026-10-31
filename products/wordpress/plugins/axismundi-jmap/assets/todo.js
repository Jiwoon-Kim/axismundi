/* global axTodoConfig, AxTodoSync */
(() => {
    'use strict';
    const config = axTodoConfig;
    const root = document.getElementById('todo-content');
    const status = document.getElementById('todo-status');
    let context, account = '', selected = '', lists = [], todos = [], busy = false, principals = null, syncing = false, pending = false, viewStates = {};
    const el = (tag, text, attrs = {}) => {
        const node = document.createElement(tag);
        if (text != null) node.textContent = text;
        Object.assign(node, attrs);
        return node;
    };
    const button = (text, action, disabled = false) => {
        const node = el('button', text, {type: 'button', className: 'button', disabled: disabled || busy});
        node.addEventListener('click', () => run(action));
        return node;
    };
    const input = (value, label) => {
        const node = el('input', null, {type: 'text', value, defaultValue: value});
        node.setAttribute('aria-label', label);
        return node;
    };
    async function http(path = '', body) {
        const url = path ? (config.contextUrl || config.url) + path : config.url;
        const response = await fetch(url, {method: body === undefined ? 'GET' : 'POST', credentials: 'same-origin', headers: {'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json'}, ...(body === undefined ? {} : {body: JSON.stringify(body)})});
        const data = await response.json();
        if (!response.ok) throw Object.assign(new Error(data.message || data.detail || 'Request failed.'), {type: data.code || data.type, status: response.status});
        return data;
    }
    async function call(method, args) {
        const envelope = await http('', {using: context.capabilities, methodCalls: [[method, args, 'todo']]});
        const result = envelope.methodResponses?.find(row => row[2] === 'todo');
        if (!result || result[0] === 'error') throw Object.assign(new Error(result?.[1]?.description || result?.[1]?.type || envelope.detail || 'Invalid response.'), {type: result?.[1]?.type});
        const data = result[1];
        for (const key of ['notCreated', 'notUpdated', 'notDestroyed']) {
            const error = Object.values(data[key] || {})[0];
            if (error) throw Object.assign(new Error(error.description || error.type), {type: error.type});
        }
        return data;
    }
    const cache = new AxTodoSync(call);
    const revoked = error => [401, 403].includes(error.status) || ['accountNotFound', 'accountNotSupportedByMethod', 'forbidden'].includes(error.type);
    function acceptSnapshot() {
        const snapshot = cache.snapshot();
        lists = snapshot.lists.filter(list => list.myRights?.mayRead === true);
        todos = snapshot.todos; viewStates = snapshot.states; pending = false; principals = null;
        if (!lists.some(list => list.id === selected)) selected = lists[0]?.id || '';
    }
    function clear() { cache.reset(); lists = []; todos = []; principals = null; viewStates = {}; pending = false; }
    async function refresh() {
        clear();
        if (!account || !context.accounts.find(a => a.id === account)?.enabled) return;
        await cache.load(account); acceptSnapshot();
    }
    function dirty() {
        return [...root.querySelectorAll('input')].some(node => node.type === 'checkbox' ? node.checked !== node.defaultChecked : node.type === 'text' && node.value !== node.defaultValue);
    }
    async function synchronize() {
        if (busy || syncing) return;
        syncing = true;
        const controls = [...root.querySelectorAll('button,select,input[type="checkbox"]')].map(node => [node, node.disabled]);
        controls.forEach(([node]) => { node.disabled = true; });
        try {
            if (!account || !context.accounts.some(option => option.id === account && option.enabled)) {
                const updatedContext = await http('/context');
                if (JSON.stringify(context) !== JSON.stringify(updatedContext)) { context = updatedContext; render(); }
                return;
            }
            const changed = await cache.sync(account);
            pending = pending || changed;
            const updatedContext = await http('/context');
            const contextChanged = JSON.stringify(context) !== JSON.stringify(updatedContext);
            context = updatedContext;
            if (!context.accounts.some(option => option.id === account && option.enabled)) { clear(); render(); return; }
            if (!changed && !pending && !contextChanged) { viewStates = cache.snapshot().states; return; }
            pending = true;
            if (dirty() && !window.confirm('Apply updates and discard unsaved edits?')) { status.textContent = 'Updates available.'; return; }
            acceptSnapshot(); status.textContent = ''; render();
        } catch (error) {
            status.textContent = error.message;
            if (revoked(error)) { clear(); render(); }
        } finally {
            syncing = false;
            controls.forEach(([node, disabled]) => { if (node.isConnected) node.disabled = disabled; });
        }
    }
    async function run(action, resetOnError = false) {
        if (busy || syncing) return;
        busy = true; status.textContent = 'Loading...';
        const controls = [...root.querySelectorAll('button,select,input')].map(node => [node, node.disabled]);
        controls.forEach(([node]) => { node.disabled = true; });
        let redraw = false;
        try { await action(); if (status.textContent === 'Loading...') status.textContent = ''; redraw = true; }
        catch (error) {
            status.textContent = error.message;
            if (revoked(error) || resetOnError) { clear(); redraw = true; }
        } finally {
            busy = false;
            if (redraw) render();
            else controls.forEach(([node, disabled]) => { if (node.isConnected) node.disabled = disabled; });
        }
    }
    async function set(type, changes) {
        await call(type + '/set', {accountId: account, ifInState: viewStates[type], ...changes});
        await cache.sync(account); acceptSnapshot();
        status.textContent = 'Saved.';
    }
    function render() {
        root.replaceChildren();
        if (!context) return;
        const bar = el('div', null, {className: 'todo-toolbar'});
        const select = el('select', null, {disabled: busy});
        select.id = 'todo-account';
        select.append(el('option', 'Select account', {value: ''}));
        context.accounts.forEach(a => select.append(el('option', a.name, {value: a.id, selected: a.id === account})));
        select.addEventListener('change', () => { account = select.value; selected = ''; run(refresh, true); });
        const update = el('button', 'Refresh', {type: 'button', className: 'button', disabled: busy});
        update.addEventListener('click', synchronize);
        bar.append(el('label', 'Account', {htmlFor: select.id}), select, update);
        root.append(bar);
        const option = context.accounts.find(a => a.id === account);
        if (!option?.enabled) {
            if (option?.canEnable && config.canEnable) root.append(button('Enable Todo', async () => { await http('/enable', {accountId: account}); context = await http('/context'); await refresh(); }));
            return;
        }
        const layout = el('div', null, {className: 'todo-layout'}), side = el('aside'), detail = el('section');
        const nav = el('ul', null, {className: 'todo-lists'});
        lists.forEach(list => {
            const item = el('li'), pick = button(list.name, async () => { selected = list.id; principals = null; });
            pick.setAttribute('aria-pressed', String(selected === list.id)); item.append(pick); nav.append(item);
        });
        const name = input('', 'New list name');
        name.disabled = busy || option.canCreate !== true;
        side.append(el('h2', 'Lists'), nav, name, button('Create list', async () => {
            if (!name.value.trim()) throw new Error('List name required.');
            await set('TodoList', {create: {newList: {name: name.value.trim()}}});
        }, option.canCreate !== true));
        layout.append(side, detail); root.append(layout);
        const list = lists.find(l => l.id === selected);
        if (!list) return;
        const rights = list.myRights || {};
        const write = rights.mayWrite === true;
        const admin = rights.mayAdmin === true;
        detail.append(el('h2', list.name));
        const tools = el('div', null, {className: 'todo-toolbar'}), rename = input(list.name, 'List name');
        rename.disabled = busy || !write;
        const subscribed = el('input', null, {type: 'checkbox', checked: !!list.isSubscribed, defaultChecked: !!list.isSubscribed, disabled: busy});
        subscribed.addEventListener('change', () => run(() => set('TodoList', {update: {[list.id]: {isSubscribed: subscribed.checked}}})));
        const subscription = el('label', ''); subscription.append(subscribed, document.createTextNode('Subscribed'));
        tools.append(rename, button('Rename', () => set('TodoList', {update: {[list.id]: {name: rename.value}}}), !write), button('Delete list', async () => {
            if (window.confirm('Delete this list and its todos?')) await set('TodoList', {destroy: [list.id]});
        }, !admin), subscription); detail.append(tools);
        const add = el('div', null, {className: 'todo-toolbar'}), title = input('', 'New todo title'); title.disabled = !write || busy;
        add.append(title, button('Add Todo', async () => {
            if (!title.value.trim()) throw new Error('Title required.');
            await set('Todo', {create: {newTodo: {title: title.value.trim(), keywords: {}, todoListId: list.id}}});
        }, !write)); detail.append(add);
        const table = el('table', null, {className: 'widefat striped'}), head = el('thead'), headings = el('tr');
        ['Done', 'Title', 'Keywords', 'Actions'].forEach(text => headings.append(el('th', text))); head.append(headings); table.append(head);
        const body = el('tbody');
        todos.filter(todo => todo.todoListId === list.id).forEach(todo => {
            const row = el('tr'), done = el('input', null, {type: 'checkbox', checked: !!todo.keywords?.done, defaultChecked: !!todo.keywords?.done, disabled: !write || busy});
            done.setAttribute('aria-label', 'Complete ' + todo.title);
            done.addEventListener('change', () => run(() => {
                const keywords = {...todo.keywords}; if (done.checked) keywords.done = true; else delete keywords.done;
                return set('Todo', {update: {[todo.id]: {keywords}}});
            }));
            const text = input(todo.title, 'Todo title'), keywords = input(Object.keys(todo.keywords || {}).join(', '), 'Keywords');
            text.disabled = keywords.disabled = !write || busy;
            [done, text, keywords].forEach((node, index) => { const cell = el('td'); cell.dataset.label = ['Done', 'Title', 'Keywords'][index]; cell.append(node); row.append(cell); });
            const actions = el('td'); actions.append(button('Save', () => set('Todo', {update: {[todo.id]: {title: text.value, keywords: Object.fromEntries(keywords.value.split(',').map(k => k.trim()).filter(Boolean).map(k => [k, true]))}}}), !write), document.createTextNode(' '), button('Delete', () => set('Todo', {destroy: [todo.id]}), !write));
            actions.dataset.label = 'Actions'; row.append(actions); body.append(row);
        });
        table.append(body); detail.append(table);
        const share = el('fieldset', null, {className: 'todo-share'}); share.append(el('legend', 'Sharing'));
        share.append(button('Load directory', async () => {
            if (!context.directoryAccountId) throw new Error('Directory unavailable.');
            principals = (await call('Principal/get', {accountId: context.directoryAccountId, ids: null})).list.filter(principal => principal.capabilities?.['urn:com.example:jmap:todo']?.mayShareWith === true);
        }, !admin || !context.directoryAccountId));
        if (principals) {
            const entries = el('ul'), controls = [];
            principals.forEach(principal => {
                const entry = el('li'); entry.append(el('strong', principal.name || principal.id));
                const checks = {};
                const groups = {read: 'mayRead', write: 'mayWrite', admin: 'mayAdmin'};
                ['read', 'write', 'admin'].forEach(right => {
                    const checked = list.shareWith?.[principal.id]?.[groups[right]] === true;
                    const check = el('input', null, {type: 'checkbox', checked, defaultChecked: checked, disabled: !admin || busy});
                    const label = el('label'); label.append(check, document.createTextNode(right)); entry.append(label); checks[right] = check;
                });
                controls.push([principal.id, checks]); entries.append(entry);
            });
            share.append(entries, button('Save sharing', async () => {
                const shareWith = {...(list.shareWith || {})};
                controls.forEach(([id, checks]) => {
                    const value = {mayRead: checks.read.checked, mayWrite: checks.write.checked, mayAdmin: checks.admin.checked};
                    if (Object.values(value).some(Boolean)) shareWith[id] = value; else delete shareWith[id];
                });
                await set('TodoList', {update: {[list.id]: {shareWith}}});
            }, !admin));
        }
        detail.append(share);
    }
    run(async () => { context = await http('/context'); account = context.accounts.find(a => a.enabled)?.id || context.accounts[0]?.id || ''; await refresh(); }, true);
})();
