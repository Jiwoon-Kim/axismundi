/* The outbox owns attribution, addressing, storage and idempotency. */
(function () {
    'use strict';
    const config = window.axismundiC2SComposer;
    const form = document.getElementById('ax-c2s-composer');
    if (!config || !form) return;
    const parent = document.getElementById('ax-c2s-parent');
    const content = document.getElementById('ax-c2s-content');
    const visibility = document.getElementById('ax-c2s-visibility');
    const mentions = document.getElementById('ax-c2s-mentions');
    const recipients = document.getElementById('ax-c2s-recipients');
    const extraTo = document.getElementById('ax-c2s-to');
    const result = document.getElementById('ax-c2s-result');
    const button = form.querySelector('button[type="submit"], input[type="submit"]');
    const preview = document.getElementById('ax-c2s-preview');
    const requestView = document.getElementById('ax-c2s-request');
    const target = document.getElementById('ax-c2s-target');
    const activityType = document.getElementById('ax-c2s-activity');
    const createFields = [parent, visibility, mentions, extraTo, recipients];
    const originalLabel = button.value;
    function syncMode() {
        if (!target) return;
        const updating = activityType.value === 'Update';
        target.closest('tr').hidden = !updating;
        target.disabled = !updating;
        target.required = updating;
        createFields.forEach(field => {
            field.disabled = updating;
            field.closest('tr').hidden = updating;
        });
        button.value = updating ? window.axismundiC2SMentions.updateLabel : originalLabel;
        if (requestView) requestView.hidden = true;
    }
    if (target) {
        if (target.value) content.value = window.axismundiC2SMentions.editContent;
        activityType.addEventListener('change', syncMode);
        syncMode();
    }
    let intent = '';
    let retryKey = '';
    let busy = false;
    async function prepare(previewOnly) {
        if (busy || !form.reportValidity()) return;
        if (requestView) requestView.hidden = true;
        const escaped = document.createElement('div');
        escaped.textContent = content.value;
        const publicUri = 'https://www.w3.org/ns/activitystreams#Public';
        const readActors = field => field.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean).map(value => {
            const url = new URL(value);
            if (!/^https?:$/.test(url.protocol) || url.username || url.password || value === publicUri) throw new Error();
            return value;
        });
        const updating = activityType.value === 'Update';
        let mentioned = [], note;
        if (updating) {
            note = {id: target.value.trim(), type: 'Note', content: content.value};
        } else {
        let to, cc;
        try {
            mentioned = [...new Set(mentions.value.split(',').map(value => value.trim().toLowerCase()).filter(Boolean))];
            to = readActors(extraTo);
            cc = readActors(recipients);
        } catch {
            result.textContent = config.invalidActor;
            return;
        }
        const audience = visibility.value;
        const presets = {
            public: [[publicUri], [config.followers, ...mentioned]],
            unlisted: [[config.followers], [publicUri, ...mentioned]],
            followers: [[config.followers], mentioned],
            mentioned: [mentioned, []]
        };
        if (!Object.hasOwn(presets, audience)) return;
        if (audience === 'mentioned' && !mentioned.length) {
            result.textContent = window.axismundiC2SMentions.mentionRequired;
            return;
        }
        const [presetTo, presetCc] = presets[audience];
        // Explicit To wins over the default Cc for Actors; keep the preset's Public placement.
        to = [...new Set([...presetTo, ...to.filter(uri => uri !== config.followers)])];
        cc = [...new Set([...presetCc, ...cc.filter(uri => uri !== config.followers)])].filter(uri => !to.includes(uri));
        note = {
            type: document.getElementById('ax-c2s-object-type').value,
            content: '<p>' + escaped.innerHTML.replace(/\r?\n/g, '<br>') + '</p>',
            to,
            cc
        };
        if (parent.value.trim()) note.inReplyTo = parent.value.trim();
        }
        busy = true;
        button.disabled = true;
        if (target) activityType.disabled = target.disabled = true;
        if (preview) preview.disabled = true;
        parent.readOnly = true;
        content.readOnly = true;
        visibility.disabled = mentions.readOnly = recipients.readOnly = extraTo.readOnly = true;
        result.textContent = config.busy;
        try {
            if (mentioned.length) {
                const lookup = window.axismundiC2SMentions;
                if (mentioned.length > 50) throw new Error(lookup.unresolved);
                const actors = new Map();
                for (const handle of mentioned) {
                    const url = new URL(lookup.url);
                    url.searchParams.set('search', handle);
                    const resolved = await fetch(url.href, {
                        credentials: 'same-origin', headers: {'X-WP-Nonce': config.nonce}
                    });
                    if (!resolved.ok) throw new Error(lookup.unresolved);
                    const exact = (await resolved.json()).filter(actor =>
                        actor.mentionName && [actor.token, actor.mentionName].some(name => name?.toLowerCase() === handle));
                    if (exact.length !== 1) throw new Error(lookup.unresolved);
                    actors.set(handle, exact[0]);
                }
                const resolve = values => [...new Set(values.map(value => actors.get(value)?.uri ?? value))];
                note.to = resolve(note.to);
                note.cc = resolve(note.cc).filter(uri => !note.to.includes(uri));
                note.tag = [...new Map([...actors.values()].map(actor =>
                    [actor.uri, {type: 'Mention', href: actor.uri, name: actor.mentionName}])).values()];
            }
            const body = JSON.stringify({
                type: activityType.value,
                actor: window.axismundiC2SMentions.actor,
                object: note
            }, null, 2);
            if (body !== intent) {
                intent = body;
                retryKey = window.crypto.randomUUID();
            }
            const request = {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/activity+json', 'X-WP-Nonce': config.nonce, 'Idempotency-Key': retryKey},
                body: body
            };
            if (requestView) {
                const endpoint = new URL(config.outbox, window.location.href);
                requestView.textContent = [
                    request.method + ' ' + endpoint.pathname + endpoint.search,
                    'Host: ' + endpoint.host,
                    ...Object.entries(request.headers).map(([name, value]) => name + ': ' + (name === 'X-WP-Nonce' ? '[redacted]' : value)),
                    'Cookie: [browser-managed; redacted]',
                    '', request.body
                ].join('\n');
                requestView.hidden = false;
            }
            if (previewOnly) {
                result.textContent = '';
                return;
            }
            const response = await fetch(config.outbox, request);
            if (!response.ok) {
                const error = await response.json().catch(function () { return {}; });
                throw new Error(error.message || config.failure);
            }
            result.textContent = config.success;
            const created = await response.json().catch(() => null);
            const labels = window.axismundiC2SMentions;
            for (const [label, location] of [[labels.activityLabel, response.headers.get('Location')], [labels.objectLabel, created?.object]]) {
                if (!location) continue;
                const link = document.createElement('a');
                const url = new URL(location, window.location.href);
                if (url.protocol === 'https:' || url.protocol === 'http:') {
                    link.href = url.href;
                    link.textContent = label + ' ' + location;
                    result.append(document.createElement('br'), link);
                }
            }
            if (!updating) form.reset();
            intent = '';
            retryKey = '';
        } catch (error) {
            result.textContent = error.message || config.failure;
        } finally {
            busy = false;
            button.disabled = false;
            if (preview) preview.disabled = false;
            parent.readOnly = false;
            content.readOnly = false;
            visibility.disabled = mentions.readOnly = recipients.readOnly = extraTo.readOnly = false;
            if (target) {
                activityType.disabled = false;
                const wasHidden = requestView?.hidden;
                syncMode();
                if (requestView) requestView.hidden = wasHidden;
            }
        }
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        return prepare(false);
    });
    if (preview) {
        preview.addEventListener('click', () => prepare(true));
        form.addEventListener('input', () => { requestView.hidden = true; });
    }
}());
