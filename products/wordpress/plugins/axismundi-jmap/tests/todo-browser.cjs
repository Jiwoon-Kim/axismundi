/* Browser integration on disposable identities. NODE_PATH must include Playwright. */
const { chromium } = require('playwright');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const os = require('node:os');
const origin = process.env.AX_TODO_HTTP_ORIGIN || 'http://localhost:8884';
const container = process.env.AX_TODO_CLI_CONTAINER || 'wp-env-axismundi-66f7e116-cli-1';
function fixture(...args) {
    const run = spawnSync('docker', ['exec', container, 'wp', 'eval-file', 'wp-content/plugins/axismundi-jmap/tests/todo-browser-fixture.php', ...args], {encoding: 'utf8', env: {...process.env, MSYS_NO_PATHCONV: '1', MSYS2_ARG_CONV_EXCL: '*'}});
    if (run.status !== 0) throw new Error(run.stderr || run.stdout);
    return run.stdout.trim();
}
(async () => {
    let browser, data;
    try {
        data = JSON.parse(fixture('create'));
        browser = await chromium.launch({headless: true, ...(process.env.AX_TODO_BROWSER_CHANNEL ? {channel: process.env.AX_TODO_BROWSER_CHANNEL} : {})});
        const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
        context.setDefaultTimeout(30000);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.goto(origin + '/wp-login.php');
        await page.locator('#user_login').fill(data.users[0].login);
        await page.locator('#user_pass').fill(data.users[0].password);
        await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
        await page.goto(origin + '/wp-admin/admin.php?page=axismundi-jmap-todo');
        console.log('Admin page loaded');
        const dismiss = page.locator('.wp-pointer-buttons .close');
        if (await dismiss.count()) await dismiss.first().click();
        await page.locator('#todo-account').selectOption(data.users[0].account);
        await page.getByRole('button', {name: 'Create list', exact: true}).waitFor();
        await page.getByLabel('New list name').fill('Browser Todo');
        await page.getByRole('button', {name: 'Create list', exact: true}).click();
        await page.getByRole('heading', {name: 'Browser Todo', exact: true}).waitFor();
        console.log('List created');
        await page.getByLabel('New todo title').fill('Exercise JMAP');
        await page.getByRole('button', {name: 'Add Todo', exact: true}).click();
        await page.getByLabel('Todo title', {exact: true}).waitFor();
        console.log('Todo created');
        const calls = [];
        const collect = request => {
            if (!request.url().endsWith('/axismundi-jmap/v1/api') || request.method() !== 'POST') return;
            calls.push(...request.postDataJSON().methodCalls.map(row => ({method: row[0], args: row[1]})));
        };
        page.on('request', collect);
        const draft = page.getByLabel('New todo title');
        await draft.fill('Unsaved draft');
        await page.evaluate(() => {
            window.__todoDraft = document.querySelector('input[aria-label="New todo title"]');
            window.__todoRenders = 0;
            window.__todoObserver = new MutationObserver(records => { window.__todoRenders += records.filter(r => r.type === 'childList').length; });
            window.__todoObserver.observe(document.getElementById('todo-content'), {childList: true});
            window.dispatchEvent(new Event('focus'));
        });
        await page.waitForTimeout(250);
        if (calls.length) throw new Error('Focus triggered an automatic API request');
        await page.getByRole('button', {name: 'Refresh', exact: true}).click();
        await page.waitForFunction(() => [...document.querySelectorAll('button')].some(button => button.textContent === 'Refresh' && !button.disabled), null, {timeout: 30000});
        if (JSON.stringify(calls.map(call => call.method)) !== JSON.stringify(['TodoList/changes', 'Todo/changes'])) throw new Error('Unchanged refresh did not use changes only: ' + JSON.stringify(calls));
        const preserved = await page.evaluate(() => window.__todoDraft === document.querySelector('input[aria-label="New todo title"]') && window.__todoDraft.value === 'Unsaved draft' && window.__todoRenders === 0 && !document.getElementById('todo-status').textContent.includes('Loading'));
        if (!preserved) throw new Error('Unchanged refresh discarded a draft or rebuilt the view');
        await draft.fill('');
        await page.evaluate(() => window.__todoObserver.disconnect());
        page.off('request', collect);
        const rejectSave = async route => {
            const request = route.request();
            if (request.method() === 'POST' && request.postDataJSON().methodCalls.some(row => row[0] === 'Todo/set')) {
                await route.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({message: 'Simulated save failure'})});
            } else await route.continue();
        };
        await page.route('**/axismundi-jmap/v1/api', rejectSave);
        await page.getByLabel('Todo title', {exact: true}).fill('Draft survives failed save');
        await page.getByRole('button', {name: 'Save', exact: true}).click();
        await page.waitForFunction(() => document.getElementById('todo-status').textContent === 'Simulated save failure', null, {timeout: 30000});
        if (await page.getByLabel('Todo title', {exact: true}).inputValue() !== 'Draft survives failed save') throw new Error('Failed save discarded its draft');
        await page.unroute('**/axismundi-jmap/v1/api', rejectSave);
        await page.getByLabel('Todo title', {exact: true}).fill('Exercise JMAP');
        await page.getByRole('button', {name: 'Load directory', exact: true}).click();
        const recipient = page.locator('.todo-share li').filter({hasText: data.users[1].login});
        await recipient.getByLabel('read', {exact: true}).check();
        await page.getByRole('button', {name: 'Save sharing', exact: true}).click();
        await page.waitForFunction(() => document.getElementById('todo-status').textContent === 'Saved.', null, {timeout: 30000});
        const desktop = path.join(os.tmpdir(), 'ax-jmap-todo-desktop.png');
        await page.screenshot({path: desktop, fullPage: true});
        const mobile = await browser.newContext({viewport: {width: 390, height: 844}});
        mobile.setDefaultTimeout(30000);
        const b = await mobile.newPage();
        await b.goto(origin + '/wp-login.php');
        await b.locator('#user_login').fill(data.users[1].login);
        await b.locator('#user_pass').fill(data.users[1].password);
        await Promise.all([b.waitForURL('**/wp-admin/**'), b.locator('#wp-submit').click()]);
        await b.goto(origin + '/wp-admin/admin.php?page=axismundi-jmap-todo');
        await b.locator('#todo-account').selectOption(data.users[0].account);
        await b.getByRole('heading', {name: 'Browser Todo', exact: true}).waitFor();
        if (!await b.getByRole('button', {name: 'Add Todo', exact: true}).isDisabled()) throw new Error('Read-only share permits writing');
        if (!await b.getByRole('button', {name: 'Create list', exact: true}).isDisabled()) throw new Error('Share permits Account-wide creation');
        await b.getByLabel('Subscribed', {exact: true}).check();
        await b.waitForFunction(() => document.getElementById('todo-status').textContent === 'Saved.', null, {timeout: 30000});
        const mobileImage = path.join(os.tmpdir(), 'ax-jmap-todo-mobile.png');
        await b.screenshot({path: mobileImage, fullPage: true});
        const overflow = await b.evaluate(() => document.documentElement.scrollWidth > innerWidth);
        if (overflow) throw new Error('Mobile horizontal overflow');
        const forbidden = await b.request.post(origin + '/wp-json/axismundi-jmap/v1/api', {headers: {'X-WP-Nonce': 'invalid'}, data: {using: ['urn:ietf:params:jmap:core'], methodCalls: [['Core/echo', {}, 'x']]}});
        if (forbidden.status() !== 403) throw new Error('Invalid REST nonce accepted');
        await page.getByRole('button', {name: 'Refresh', exact: true}).click();
        await page.waitForFunction(() => [...document.querySelectorAll('button')].some(button => button.textContent === 'Refresh' && !button.disabled), null, {timeout: 30000});
        await page.getByLabel('Todo title', {exact: true}).fill('Updated through JMAP');
        await page.getByRole('button', {name: 'Save', exact: true}).click();
        await page.waitForFunction(() => document.getElementById('todo-status').textContent === 'Saved.', null, {timeout: 30000});
        const incoming = [];
        const collectIncoming = request => {
            if (request.url().endsWith('/axismundi-jmap/v1/api') && request.method() === 'POST') incoming.push(...request.postDataJSON().methodCalls.map(row => ({method: row[0], args: row[1]})));
        };
        b.on('request', collectIncoming);
        let failContext = true;
        const rejectContextOnce = async route => {
            if (failContext) { failContext = false; await route.fulfill({status: 503, contentType: 'application/json', body: JSON.stringify({message: 'Simulated context failure'})}); }
            else await route.continue();
        };
        await b.route('**/axismundi-jmap/v1/todo/context', rejectContextOnce);
        await b.getByRole('button', {name: 'Refresh', exact: true}).click();
        await b.waitForFunction(() => document.getElementById('todo-status').textContent === 'Simulated context failure', null, {timeout: 30000});
        if (await b.getByLabel('Todo title', {exact: true}).inputValue() === 'Updated through JMAP') throw new Error('Failed refresh partially applied its view');
        await b.getByRole('button', {name: 'Refresh', exact: true}).click();
        await b.waitForFunction(() => document.querySelector('input[aria-label="Todo title"]')?.value === 'Updated through JMAP', null, {timeout: 30000});
        await b.unroute('**/axismundi-jmap/v1/todo/context', rejectContextOnce);
        if (!incoming.some(call => call.method === 'Todo/changes') || !incoming.some(call => call.method === 'Todo/get' && Array.isArray(call.args.ids) && call.args.ids.length === 1) || incoming.some(call => call.method.endsWith('/get') && call.args.ids === null)) throw new Error('Remote update did not use ID-scoped delta fetch: ' + JSON.stringify(incoming));
        b.off('request', collectIncoming);
        await page.getByRole('button', {name: 'Load directory', exact: true}).click();
        await recipient.getByLabel('read', {exact: true}).uncheck();
        await page.getByRole('button', {name: 'Save sharing', exact: true}).click();
        await page.waitForFunction(() => document.getElementById('todo-status').textContent === 'Saved.', null, {timeout: 30000});
        await b.getByRole('button', {name: 'Refresh', exact: true}).click();
        await b.getByRole('heading', {name: 'Browser Todo', exact: true}).waitFor({state: 'detached'});
        if (errors.length) throw new Error(errors.join('\n'));
        console.log(JSON.stringify({status: 'PASS', desktop, mobile: mobileImage, checks: 17}));
    } finally {
        if (browser) await browser.close();
        if (data) console.log(fixture('cleanup', data.prefix));
    }
})().catch(e => { console.error(e); process.exitCode = 1; });
