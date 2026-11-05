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
    const links = [];
    const result = {textContent: '', append(br, link) { links.push(link.href); }};
    const button = {disabled: false};
    let submit;
    let showPreview;
    const requestView = {hidden: true, textContent: ''};
    const preview = {addEventListener(event, handler) { showPreview = handler; }};
    const form = {
        querySelector() { return button; },
        reportValidity() { return true; },
        addEventListener(event, handler) { if (event === 'submit') submit = handler; },
        reset() { parent.value = ''; content.value = ''; }
    };
    const visibility = {value: 'public'};
    const mentions = {value: ''};
    const recipients = {value: ''};
    const extraTo = {value: ''};
    const followers = 'https://example.test/followers';
    const nodes = {'ax-c2s-composer': form, 'ax-c2s-parent': parent, 'ax-c2s-content': content, 'ax-c2s-result': result, 'ax-c2s-visibility': visibility, 'ax-c2s-mentions': mentions, 'ax-c2s-recipients': recipients};
    const calls = [];
    let serial = 0;
    let response = {ok: false, json: async () => ({message: 'Temporary failure'})};
    const context = {
        window: {axismundiC2SComposer: {outbox: 'https://example.test/outbox', followers, nonce: 'test-nonce', success: 'Published', failure: 'Failed', busy: 'Publishing', invalidActor: 'Invalid actor'}, crypto: {randomUUID: () => 'key-' + ++serial}, location: {href: 'https://example.test/admin'}},
        document: {
            getElementById: id => nodes[id] || {'ax-c2s-preview': preview, 'ax-c2s-request': requestView, 'ax-c2s-to': extraTo, 'ax-c2s-object-type': {value: 'Note'}, 'ax-c2s-activity': {value: 'Create'}}[id],
            createElement: () => ({textContent: '', get innerHTML() { return this.textContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }})
        },
        URL,
        fetch: async (url, options) => { calls.push({url, ...options}); return response; }
    };
    vm.runInNewContext(source, context);
    context.window.axismundiC2SMentions = {actor: 'https://example.test/actor', url: 'https://example.test/mention-resolve', unresolved: 'Unresolved mention'};
    const fetchOutbox = context.fetch;
    let resolvedActors = [{uri: 'https://remote.test/actor', mentionName: '@actor@remote.test'}];
    context.fetch = (url, options) => url.startsWith(context.window.axismundiC2SMentions.url)
        ? Promise.resolve({ok: true, json: async () => resolvedActors})
        : fetchOutbox(url, options);
    const send = () => submit({preventDefault() {}});
    await showPreview();
    check(calls.length === 0 && !requestView.hidden, 'Preview displays a request without posting');
    const raw = requestView.textContent;
    check(raw.includes('POST /outbox') && !raw.includes('test-nonce'), 'Preview shows route but redacts nonce');
    await send();
    check(raw.endsWith(calls[0].body) && raw.includes('Idempotency-Key: ' + calls[0].headers['Idempotency-Key']), 'Preview matches the submitted body and key');
    check(result.textContent === 'Temporary failure', 'Server error is visible');
    check(content.value === 'A < B\nSecond line' && parent.value !== '', 'Failed submission retains inputs');
    check(!button.disabled && !content.readOnly && !parent.readOnly, 'Failure releases controls');
    check(calls[0].url === context.window.axismundiC2SComposer.outbox && calls[0].method === 'POST', 'Client uses the real outbox POST');
    check(calls[0].headers['X-WP-Nonce'] === 'test-nonce' && calls[0].credentials === 'same-origin', 'Cookie and nonce are sent');
    const activity = JSON.parse(calls[0].body);
    check(activity.type === 'Create' && activity.actor === context.window.axismundiC2SMentions.actor, 'Explicit Create uses the selected outbox Actor');
    const payload = activity.object;
    check(payload.type === 'Note' && payload.inReplyTo === parent.value, 'Payload uses Note and exact parent URI');
    check(payload.content === '<p>A &lt; B<br>Second line</p>', 'Text is escaped and line breaks retained');
    check(payload.cc[0] === followers && !('audience' in payload) && !('actor' in payload), 'Public addresses followers without assigning a Community or author');
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
    resolve({ok: true, headers: {get: () => 'https://example.test/activities/1'}, json: async () => ({object: 'https://example.test/objects/1'})});
    await pending;
    check(result.textContent === 'Published', 'Success is shown');
    assert.deepEqual(links, ['https://example.test/activities/1', 'https://example.test/objects/1']);
    check(content.value === '' && parent.value === '', 'Only success clears inputs');
    check(!button.disabled, 'Success releases the submit button');
    content.value = 'Standalone Note';
    visibility.value = 'unlisted';
    mentions.value = '@actor@remote.test, @ACTOR@remote.test';
    recipients.value = 'https://remote.test/actor\nhttps://remote.test/other';
    await send();
    const standalone = JSON.parse(calls.at(-1).body).object;
    check(!('inReplyTo' in standalone), 'Standalone Note omits the reply reference');
    assert.deepEqual(standalone.to, [followers]);
    assert.deepEqual(standalone.cc, ['https://www.w3.org/ns/activitystreams#Public', 'https://remote.test/actor', 'https://remote.test/other']);
    assert.deepEqual(standalone.tag, [{type: 'Mention', href: 'https://remote.test/actor', name: '@actor@remote.test'}]);
    visibility.value = 'followers';
    extraTo.value = 'https://remote.test/other';
    await send();
    const restricted = JSON.parse(calls.at(-1).body).object;
    assert.deepEqual(restricted.to, [followers, 'https://remote.test/other']);
    assert.deepEqual(restricted.cc, ['https://remote.test/actor']);
    visibility.value = 'mentioned';
    extraTo.value = recipients.value = '';
    await send();
    assert.deepEqual(JSON.parse(calls.at(-1).body).object.to, ['https://remote.test/actor']);
    resolvedActors = [];
    const beforeLookupFailure = calls.length;
    await send();
    check(calls.length === beforeLookupFailure && result.textContent === 'Unresolved mention', 'Unknown Actor blocks publishing');
    extraTo.value = 'javascript:alert(1)';
    const sent = calls.length;
    await send();
    check(calls.length === sent && result.textContent === 'Invalid actor', 'Invalid Actor URI never submits');
    console.log(`== ${checks} checks, 0 failed ==`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
