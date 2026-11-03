/* The outbox owns attribution, addressing, storage and idempotency. */
(function () {
    'use strict';
    const config = window.axismundiC2SComposer;
    const form = document.getElementById('ax-c2s-composer');
    if (!config || !form) return;
    const parent = document.getElementById('ax-c2s-parent');
    const content = document.getElementById('ax-c2s-content');
    const result = document.getElementById('ax-c2s-result');
    const button = form.querySelector('button[type="submit"], input[type="submit"]');
    let intent = '';
    let retryKey = '';
    let busy = false;
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const escaped = document.createElement('div');
        escaped.textContent = content.value;
        const body = JSON.stringify({
            type: 'Note',
            inReplyTo: parent.value.trim(),
            content: '<p>' + escaped.innerHTML.replace(/\r?\n/g, '<br>') + '</p>',
            to: ['https://www.w3.org/ns/activitystreams#Public']
        });
        if (body !== intent) {
            intent = body;
            retryKey = window.crypto.randomUUID();
        }
        busy = true;
        button.disabled = true;
        parent.readOnly = true;
        content.readOnly = true;
        result.textContent = config.busy;
        try {
            const response = await fetch(config.outbox, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/activity+json', 'X-WP-Nonce': config.nonce, 'Idempotency-Key': retryKey},
                body: body
            });
            if (!response.ok) {
                const error = await response.json().catch(function () { return {}; });
                throw new Error(error.message || config.failure);
            }
            result.textContent = config.success;
            const location = response.headers.get('Location');
            if (location) {
                const link = document.createElement('a');
                const url = new URL(location, window.location.href);
                if (url.protocol === 'https:' || url.protocol === 'http:') {
                    link.href = url.href;
                    link.textContent = location;
                    result.append(document.createElement('br'), link);
                }
            }
            form.reset();
            intent = '';
            retryKey = '';
        } catch (error) {
            result.textContent = error.message || config.failure;
        } finally {
            busy = false;
            button.disabled = false;
            parent.readOnly = false;
            content.readOnly = false;
        }
    });
}());
