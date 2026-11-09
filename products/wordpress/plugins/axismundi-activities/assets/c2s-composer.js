/* The outbox owns attribution, addressing, storage and idempotency. */
(function () {
    'use strict';
    const config = window.axismundiC2SComposer;
    const form = document.getElementById('ax-c2s-composer');
    if (!config || !form) return;
    const parent = document.getElementById('ax-c2s-parent');
    const content = document.getElementById('ax-c2s-content');
    const visibility = document.getElementById('ax-c2s-visibility');
    const community = document.getElementById('ax-c2s-audience');
    const communityUri = document.getElementById('ax-c2s-audience-uri');
    const mentions = document.getElementById('ax-c2s-mentions');
    const recipients = document.getElementById('ax-c2s-recipients');
    const extraTo = document.getElementById('ax-c2s-to');
    const blindTo = document.getElementById('ax-c2s-bto');
    const blindCc = document.getElementById('ax-c2s-bcc');
    const result = document.getElementById('ax-c2s-result');
    const button = form.querySelector('button[type="submit"], input[type="submit"]');
    const preview = document.getElementById('ax-c2s-preview');
    const requestView = document.getElementById('ax-c2s-request');
    const target = document.getElementById('ax-c2s-target');
    const activityType = document.getElementById('ax-c2s-activity');
    const objectType = document.getElementById('ax-c2s-object-type');
    const questionMode = document.getElementById('ax-c2s-question-mode');
    const questionOptions = document.getElementById('ax-c2s-question-options');
    const sensitive = document.getElementById('ax-c2s-sensitive');
    const questionName = document.getElementById('ax-c2s-question-name');
    const questionSummary = document.getElementById('ax-c2s-question-summary');
    const questionLanguage = document.getElementById('ax-c2s-question-language');
    const questionFields = [questionMode, questionOptions, questionName, questionSummary, questionLanguage].filter(Boolean);
    const escapeContent = value => {
        const escaped = document.createElement('div');
        escaped.textContent = value;
        return '<p>' + escaped.innerHTML.replace(/\r?\n/g, '<br>') + '</p>';
    };
    const isQuestion = () => activityType.value === 'Create' && objectType.value === 'Question';
    const addressFields = [mentions, extraTo, recipients, blindTo, blindCc].filter(Boolean);
    const createFields = [parent, visibility, community, communityUri, ...addressFields].filter(Boolean);
    const originalLabel = button.value;
    const publicUri = 'https://www.w3.org/ns/activitystreams#Public';
    const lines = value => value.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
    const groupValue = () => isQuestion() ? '' : (communityUri?.value ?? community?.value ?? '').trim();
    function presetAddresses(mode, group) {
        switch (mode) {
            case 'public': return group ? [[group, publicUri], []] : [[publicUri], [config.followers]];
            case 'unlisted': return [[group || config.followers], [publicUri]];
            case 'followers': return [[config.followers], []];
            default: return [[], []];
        }
    }
    let lastPreset = [[], []];
    function syncPreset() {
        const next = presetAddresses(visibility.value, groupValue());
        [extraTo, recipients].forEach((field, index) => {
            const manual = lines(field.value).filter(value => !lastPreset[index].includes(value));
            field.value = [...new Set([...next[index], ...manual])].join('\n');
        });
        lastPreset = next;
    }
    visibility.addEventListener?.('change', syncPreset);
    community?.addEventListener?.('change', () => {
        if (communityUri) communityUri.value = community.value;
        syncPreset();
    });
    communityUri?.addEventListener?.('change', () => {
        if (community) community.value = groupValue();
        syncPreset();
    });
    syncPreset();
    function syncMode() {
        if (!target) return;
        const updating = activityType.value === 'Update';
        target.closest('tr').hidden = !updating;
        target.disabled = !updating;
        target.required = updating;
        createFields.forEach(field => {
            const hidden = updating || (isQuestion() && [parent, community, communityUri].includes(field));
            field.disabled = hidden;
            field.closest('tr').hidden = hidden;
        });
        questionFields.forEach(field => {
            field.disabled = !isQuestion();
            field.closest('tr').hidden = !isQuestion();
        });
        objectType.disabled = updating;
        if (updating) objectType.value = 'Note';
        content.required = !isQuestion();
        button.value = updating ? window.axismundiC2SMentions.updateLabel
            : isQuestion() ? window.axismundiC2SMentions.publishQuestionLabel : originalLabel;
        syncPreset();
        if (requestView) requestView.hidden = true;
    }
    if (target) {
        if (target.value) content.value = window.axismundiC2SMentions.editContent;
        activityType.addEventListener('change', syncMode);
        objectType.addEventListener('change', syncMode);
        syncMode();
    }
    let intent = '';
    let retryKey = '';
    let busy = false;
    let sensitiveChanged = false;
    sensitive.addEventListener('change', () => { sensitiveChanged = true; });
    async function prepare(previewOnly) {
        if (busy || !form.reportValidity()) return;
        if (requestView) requestView.hidden = true;
        const readActors = (field, visible = false) => lines(field.value).map(value => {
            const url = new URL(value);
            if (!/^https?:$/.test(url.protocol) || url.username || url.password || (!visible && value === publicUri)) throw new Error();
            return value;
        });
        const updating = activityType.value === 'Update';
        const question = isQuestion();
        let mentioned = [], note;
        if (updating) {
            note = {id: target.value.trim(), type: 'Note', content: content.value};
            if (sensitiveChanged) note.sensitive = sensitive.checked;
        } else {
        let to, cc, bto, bcc;
        const group = groupValue();
        try {
            if (group) readActors({value: group});
            syncPreset();
            mentioned = [...new Set(mentions.value.split(',').map(value => value.trim().toLowerCase()).filter(Boolean))];
            to = readActors(extraTo, true);
            cc = readActors(recipients, true);
            bto = blindTo ? [...new Set(readActors(blindTo))] : [];
            bcc = blindCc ? [...new Set(readActors(blindCc))] : [];
        } catch {
            result.textContent = config.invalidActor;
            return;
        }
        const visibilityMode = visibility.value;
        if (!['public', 'unlisted', 'followers', 'mentioned'].includes(visibilityMode)) return;
        if (group && !['public', 'unlisted'].includes(visibilityMode)) {
            result.textContent = window.axismundiC2SMentions.communityVisibility;
            return;
        }
        if (group && (bto.length || bcc.length)) {
            result.textContent = window.axismundiC2SMentions.communityBlindAddress;
            return;
        }
        if (visibilityMode === 'mentioned' && !mentioned.length) {
            result.textContent = window.axismundiC2SMentions.mentionRequired;
            return;
        }
        const [presetTo, presetCc] = presetAddresses(visibilityMode, group);
        (visibilityMode === 'mentioned' ? presetTo : presetCc).push(...mentioned);
        // Explicit To wins over the default Cc for Actors; keep the preset's Public placement.
        const explicit = uri => uri !== config.followers && uri !== publicUri;
        to = [...new Set([...presetTo, ...to.filter(explicit)])];
        cc = [...new Set([...presetCc, ...cc.filter(explicit)])].filter(uri => !to.includes(uri));
        note = {
            type: objectType.value,
            sensitive: sensitive.checked,
            to,
            cc
        };
        if (!question || content.value.trim()) note.content = escapeContent(content.value);
        if (question) {
            note[questionMode.value] = lines(questionOptions.value).map(name => ({
                type: 'Note', name, replies: {type: 'Collection', totalItems: 0}
            }));
            if (questionName.value.trim()) note.name = questionName.value.trim();
            if (questionSummary.value.trim()) note.summary = escapeContent(questionSummary.value);
            const language = questionLanguage.value.trim() || window.axismundiC2SMentions.defaultLanguage;
            if (language && language.toLowerCase() !== 'und') {
                for (const property of ['name', 'content', 'summary']) {
                    if (note[property]) note[property + 'Map'] = {[language]: note[property]};
                }
            }
        } else if (parent.value.trim()) note.inReplyTo = parent.value.trim();
        if (bto.length) note.bto = bto;
        if (bcc.length) note.bcc = bcc;
        if (group) note.audience = group;
        }
        busy = true;
        button.disabled = true;
        objectType.disabled = true;
        sensitive.disabled = true;
        questionFields.forEach(field => { field.disabled = true; });
        if (target) activityType.disabled = target.disabled = true;
        if (preview) preview.disabled = true;
        parent.readOnly = true;
        content.readOnly = true;
        visibility.disabled = true;
        addressFields.forEach(field => { field.readOnly = true; });
        if (community) community.disabled = true;
        if (communityUri) communityUri.readOnly = true;
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
                throw new Error(question && error.code ? error.code + ': ' + (error.message || config.failure) : error.message || config.failure);
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
            if (!updating) {
                form.reset();
                sensitiveChanged = false;
                syncPreset();
            }
            intent = '';
            retryKey = '';
        } catch (error) {
            result.textContent = error.message || config.failure;
        } finally {
            busy = false;
            button.disabled = false;
            objectType.disabled = false;
            sensitive.disabled = false;
            if (preview) preview.disabled = false;
            parent.readOnly = false;
            content.readOnly = false;
            visibility.disabled = false;
            addressFields.forEach(field => { field.readOnly = false; });
            if (community) community.disabled = false;
            if (communityUri) communityUri.readOnly = false;
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
