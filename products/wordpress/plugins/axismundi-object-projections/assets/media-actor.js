/* global ajaxurl, wp */
document.addEventListener('DOMContentLoaded', () => {
	const dialog = document.getElementById('find-actors');
	if (!dialog) return;

	const form = dialog.querySelector('form');
	const search = dialog.querySelector('input[type="search"]');
	const table = dialog.querySelector('table');
	const rows = table.querySelector('tbody');
	const submit = dialog.querySelector('button[type="submit"]');
	const empty = dialog.querySelector('[data-empty]');
	const loading = dialog.querySelector('[data-loading]');
	const error = dialog.querySelector('[data-error]');
	const warning = dialog.querySelector('[data-update-warning]');
	const confirmation = warning.querySelector('input');
	const close = dialog.querySelector('[data-close]');
	const { __ } = wp.i18n;
	let trigger;
	let saving = false;
	let request;
	let timer;

	function updateSubmit() {
		submit.disabled = saving || !form.querySelector('input[name="actor"]:checked') || (!warning.hidden && !confirmation.checked);
	}

	function resetSearch() {
		clearTimeout(timer);
		request?.abort();
		rows.replaceChildren();
		table.hidden = true;
		empty.hidden = true;
		error.hidden = true;
		warning.hidden = true;
		confirmation.checked = false;
		loading.hidden = false;
		updateSubmit();
	}

	function appendActor(actor) {
		const row = rows.insertRow();
		const label = document.createElement('label');
		const radio = document.createElement('input');
		radio.type = 'radio';
		radio.name = 'actor';
		radio.value = actor.uri;
		radio.checked = actor.uri === trigger.dataset.currentActor;
		const name = document.createElement('span');
		name.dataset.label = '';
		name.textContent = actor.label;
		label.append(radio, ' ', name);
		if (actor.handle) {
			const handle = document.createElement('span');
			handle.className = 'description';
			handle.textContent = actor.handle;
			label.append(' ', handle);
		}
		const cell = row.insertCell();
		cell.append(label);
		if (actor.adminAssignment) {
			const note = document.createElement('div');
			note.className = 'description';
			note.textContent = __('Assignment only; no publishing access', 'axismundi-object-projections');
			cell.append(note);
		}
		row.insertCell().textContent = actor.type;
	}

	async function loadActors() {
		const controller = new AbortController();
		request = controller;
		const data = new FormData(form);
		data.set('action', 'ax_op_search_list_actors');
		data.set('post_id', trigger.dataset.postId);
		data.set('search', search.value);
		const message = __('Actors could not be loaded. Try searching again.', 'axismundi-object-projections');
		try {
			const response = await fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal });
			const result = await response.json();
			if (controller.signal.aborted) return;
			if (!response.ok || !result.success) {
				throw new Error(typeof result.data === 'string' ? result.data : message);
			}
			result.data.actors.forEach(appendActor);
			table.hidden = result.data.actors.length === 0;
			warning.hidden = table.hidden || !result.data.warnUpdate;
			empty.hidden = !table.hidden;
			updateSubmit();
		} catch (failure) {
			if (controller.signal.aborted) return;
			error.textContent = failure instanceof SyntaxError || failure instanceof TypeError ? message : failure.message;
			error.hidden = false;
		} finally {
			if (!controller.signal.aborted) loading.hidden = true;
		}
	}

	document.addEventListener('click', (event) => {
		const button = event.target.closest('.ax-assign-list-actor');
		if (!button) return;
		trigger = button;
		form.reset();
		resetSearch();
		dialog.showModal();
		search.focus();
		loadActors();
	});
	close.addEventListener('click', () => dialog.close());
	dialog.addEventListener('cancel', (event) => {
		if (saving) event.preventDefault();
	});
	dialog.addEventListener('close', () => {
		clearTimeout(timer);
		request?.abort();
		trigger?.focus();
	});
	search.addEventListener('input', () => {
		resetSearch();
		timer = setTimeout(loadActors, 250);
	});
	search.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' || event.isComposing) return;
		event.preventDefault();
		resetSearch();
		loadActors();
	});
	form.addEventListener('change', updateSubmit);
	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const selected = form.querySelector('input[name="actor"]:checked:not(:disabled)');
		if (submit.disabled || !selected) return;

		const data = new FormData(form);
		data.set('action', 'ax_op_assign_list_actor');
		data.set('post_id', trigger.dataset.postId);
		saving = true;
		close.disabled = true;
		search.disabled = true;
		error.hidden = true;
		for (const radio of rows.querySelectorAll('input')) radio.disabled = true;
		updateSubmit();
		const message = __('The actor could not be assigned. Try again.', 'axismundi-object-projections');
		try {
			const response = await fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' });
			const result = await response.json();
			if (!response.ok || !result.success) {
				throw new Error(typeof result.data === 'string' ? result.data : message);
			}
			if (trigger.dataset.postType === 'post') {
				window.location.reload();
				return;
			}
			const cell = trigger.closest('td').querySelector('[data-assigned-actor]');
			cell.textContent = selected.closest('label').querySelector('[data-label]').textContent;
			cell.title = selected.value;
			trigger.dataset.currentActor = selected.value;
			dialog.close();
		} catch (failure) {
			error.textContent = failure instanceof SyntaxError || failure instanceof TypeError ? message : failure.message;
			error.hidden = false;
		} finally {
			saving = false;
			close.disabled = false;
			search.disabled = false;
			for (const radio of rows.querySelectorAll('input')) radio.disabled = false;
			updateSubmit();
		}
	});
});
