/* Exercise the shipped browser client without writing WordPress fixtures. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
const source = fs.readFileSync(path.join(__dirname, '../assets/c2s-composer.js'), 'utf8');
async function run() {
    const parent = {value: 'https://example.test/parent', readOnly: false};
    const content = {value: 'A < B\nSecond line', readOnly: false};
    const result = {textContent: '', append() {}};
    const button = {disabled: false};
    let submit;
    const form = {
        querySelector() { return button; },
        reportValidity() { return true; },
        addEventListener(event, handler) { submit = handler; },
        reset() { parent.value = ''; content.value = ''; }
    };
    const nodes = {'ax-c2s-composer': form, 'ax-c2s-parent': parent, 'ax-c2s-content': content, 'ax-c2s-result': result};
    const calls = [];
    let serial = 0;
    let response = {ok: false, json: async () => ({message: 'Temporary failure'})};
    const context = {
        window: {axismundiC2SComposer: {outbox: 'https://example.test/outbox', nonce: 'test-nonce', success: 'Published', failure: 'Failed', busy: 'Publishing'}, crypto: {randomUUID: () => 'key-' + ++serial}, location: {href: 'https://example.test/admin'}},
        document: {
            getElementById: id => nodes[id],
            createElement: () => ({textContent: '', get innerHTML() { return this.textContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }})
        },
        URL,
        fetch: async (url, options) => { calls.push({url, ...options}); return response; }
    };
    vm.runInNewContext(source, context);
    const send = () => submit({preventDefault() {}});
    await send();
    check(result.textContent === 'Temporary failure', 'Server error is visible');
    check(content.value === 'A < B\nSecond line' && parent.value !== '', 'Failed submission retains inputs');
    check(!button.disabled && !content.readOnly && !parent.readOnly, 'Failure releases controls');
    check(calls[0].url === context.window.axismundiC2SComposer.outbox && calls[0].method === 'POST', 'Client uses the real outbox POST');
    check(calls[0].headers['X-WP-Nonce'] === 'test-nonce' && calls[0].credentials === 'same-origin', 'Cookie and nonce are sent');
    const payload = JSON.parse(calls[0].body);
    check(payload.type === 'Note' && payload.inReplyTo === parent.value, 'Payload uses Note and exact parent URI');
    check(payload.content === '<p>A &lt; B<br>Second line</p>', 'Text is escaped and line breaks retained');
    check(!('cc' in payload) && !('audience' in payload) && !('actor' in payload), 'Community addresses and author are not fabricated by the UI');
    await send();
    check(calls[0].headers['Idempotency-Key'] === calls[1].headers['Idempotency-Key'], 'Unchanged retry reuses its key');
    content.value = 'Different intention';
    await send();
    check(calls[2].headers['Idempotency-Key'] !== calls[1].headers['Idempotency-Key'], 'Changed input gets a new key');
    let resolve;
    response = new Promise(done => { resolve = done; });
    const pending = send();
    check(button.disabled && content.readOnly && parent.readOnly, 'In-flight inputs are frozen');
    const count = calls.length;
    await send();
    check(calls.length === count, 'Repeated click cannot create a concurrent POST');
    resolve({ok: true, headers: {get: () => 'https://example.test/activities/1'}});
    await pending;
    check(result.textContent === 'Published', 'Success is shown');
    check(content.value === '' && parent.value === '', 'Only success clears inputs');
    check(!button.disabled, 'Success releases the submit button');
    console.log(`== ${checks} checks, 0 failed ==`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
