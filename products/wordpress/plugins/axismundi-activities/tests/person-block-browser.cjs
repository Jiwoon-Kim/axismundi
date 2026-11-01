/* Actual admin POST/nonce and local inbox checks on disposable Persons. */
const {chromium} = require('playwright');
const {spawnSync} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const path = require('node:path');
const os = require('node:os');
const origin = process.env.AX_BLOCK_HTTP_ORIGIN || 'http://localhost:8884';
const container = process.env.AX_BLOCK_CLI_CONTAINER || 'wp-env-axismundi-66f7e116-cli-1';
const prefix = `ax_pb_ui_${randomBytes(4).toString('hex')}`;
function fixture(mode) {
    const result = spawnSync('docker', ['exec', container, 'wp', 'eval-file', 'wp-content/plugins/axismundi-activities/tests/person-block-browser-fixture.php', mode, prefix], {encoding: 'utf8', env: {...process.env, MSYS_NO_PATHCONV: '1', MSYS2_ARG_CONV_EXCL: '*'}});
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
    return result.stdout.trim();
}
(async () => {
    let browser;
    let checks = 0;
    const assert = (value, label) => { if (!value) throw new Error(label); checks++; console.log(`PASS ${label}`); };
    try {
        const data = JSON.parse(fixture('create'));
        browser = await chromium.launch({headless: true, channel: process.env.AX_BLOCK_BROWSER_CHANNEL || 'chrome'});
        const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
        await context.addCookies(Object.entries(data.cookies).map(([name, value]) => ({name, value, url: origin})));
        const page = await context.newPage();
        page.setDefaultTimeout(45000);
        await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
        await page.goto(data.url, {waitUntil: 'domcontentloaded'});
        await page.getByRole('heading', {name: 'Follows', exact: true}).waitFor();
        assert(await page.getByRole('button', {name: 'Block', exact: true}).count() > 0, 'authorized Actor sees Block');
        await Promise.all([
            page.waitForURL(url => url.searchParams.get('ax_follow_notice') === 'blocked'),
            page.getByRole('button', {name: 'Block', exact: true}).first().click(),
        ]);
        let state = JSON.parse(fixture('inspect'));
        assert(state.block === 'active' && state.follow === 'undone', 'Block terminates Follow via real admin POST');
        assert(state.receipts >= 1, 'Block delivered into local target inbox');
        const forbidden = await context.request.post(origin + '/wp-admin/admin-post.php', {form: {action: 'axismundi_act_block', intent: 'unblock', subject_uri: data.subject, target_uri: data.target}});
        assert(forbidden.status() === 403 && JSON.parse(fixture('inspect')).block === 'active', 'missing nonce cannot unblock');
        await page.getByRole('heading', {name: 'Blocked actors', exact: true}).waitFor();
        assert(await page.getByRole('button', {name: 'Unblock', exact: true}).count() === 1, 'Unblock remains reachable after Follow removal');
        const screenshot = path.join(os.tmpdir(), `${prefix}-blocked.png`);
        await page.screenshot({path: screenshot, fullPage: true});
        console.log(`Screenshot: ${screenshot}`);
        await Promise.all([
            page.waitForURL(url => url.searchParams.get('ax_follow_notice') === 'unblocked'),
            page.getByRole('button', {name: 'Unblock', exact: true}).click(),
        ]);
        state = JSON.parse(fixture('inspect'));
        assert(state.block === 'undone' && state.follow === 'undone', 'Unblock does not restore Follow');
        assert(state.receipts >= 2, 'Undo(Block) delivered into local target inbox');
        assert(await page.getByRole('button', {name: 'Unblock', exact: true}).count() === 0, 'inactive Block leaves active list');
        console.log(`PASS: ${checks} browser checks`);
    } finally {
        if (browser) await browser.close();
        console.log(fixture('cleanup'));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
