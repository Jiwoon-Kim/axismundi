/**
 * Comment thread connectors + reply UI (bubble thread).
 *
 * 1) Draws an SVG connector from each parent comment's avatar (bottom-centre) to
 *    each of its direct replies' avatars (left edge), measured from the live layout
 *    so the line tracks the bubbles as they wrap (CSS-only offsets drift on wrap).
 * 2) Reply UI built ON TOP of core's comment-reply.js (we do NOT block it — core
 *    still moves the form and sets comment_parent): when a reply starts we light the
 *    reply link + the connector chain from the root down to the target, and reformat
 *    core's reply title into a Facebook-style "Replying to <name> · Cancel" line.
 *    Cancel is core's own #cancel-comment-reply-link, reused so core stays in charge.
 *
 * Ported/adapted from the Omphalos theme; scoped to the `Comments (bubble thread)`
 * pattern via `.ax-comments-thread`. Reply strings are English so they can be wrapped
 * in wp.i18n (+ wp_set_script_translations) for localisation later.
 */
(() => {
	const TEMPLATE_SELECTOR = '.ax-comments-thread .wp-block-comment-template';
	const ITEM_SELECTOR = ':scope > .ax-comment-item';
	const SVG_NS = 'http://www.w3.org/2000/svg';
	const REPLYING_TO = 'Replying to'; // i18n: __('Replying to', 'axismundi')
	const CANCEL = 'Cancel'; // i18n: __('Cancel', 'axismundi')
	const DEFAULT_REPLY_TITLE = 'Leave a Reply'; // i18n: __('Leave a Reply', 'axismundi')
	let activeReplyItemId = '';
	let defaultReplyTitleText = DEFAULT_REPLY_TITLE;

	const makeSvg = (width, height) => {
		const svg = document.createElementNS(SVG_NS, 'svg');
		svg.setAttribute('class', 'ax-comment-connectors');
		svg.setAttribute('aria-hidden', 'true');
		svg.setAttribute('focusable', 'false');
		svg.setAttribute('width', String(width));
		svg.setAttribute('height', String(height));
		svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
		return svg;
	};

	const makePath = (d, targetId) => {
		const path = document.createElementNS(SVG_NS, 'path');
		path.setAttribute('d', d);
		path.setAttribute('class', 'ax-comment-connector');
		if (targetId) {
			path.dataset.connectorTarget = targetId;
		}
		return path;
	};

	const roundedConnector = (railX, startY, childX, childY) => {
		const direction = childX >= railX ? 1 : -1;
		const radius = Math.min(10, Math.abs(childX - railX) / 2, Math.abs(childY - startY) / 2);
		const curveStartY = childY - radius;
		const curveEndX = railX + radius * direction;

		if (radius <= 0) {
			return `M ${railX} ${startY} V ${childY} H ${childX}`;
		}

		return [
			`M ${railX} ${startY}`,
			`V ${curveStartY}`,
			`Q ${railX} ${childY} ${curveEndX} ${childY}`,
			`H ${childX}`,
		].join(' ');
	};

	const directChildren = (ol) => Array.from(ol.children).filter((child) => child.tagName === 'LI');
	const itemFor = (li) => li.querySelector(ITEM_SELECTOR);
	const avatarFor = (li) => itemFor(li)?.querySelector('.wp-block-avatar') || null;

	const ensureId = (element) => {
		if (!element.id) {
			element.id = `ax-comment-item-${Math.random().toString(36).slice(2, 10)}`;
		}
		return element.id;
	};

	const renderTemplate = (template) => {
		template.querySelector(':scope > .ax-comment-connectors')?.remove();

		const templateRect = template.getBoundingClientRect();
		const width = Math.ceil(templateRect.width);
		const height = Math.max(template.scrollHeight, Math.ceil(templateRect.height));
		const svg = makeSvg(width, height);
		let pathCount = 0;

		for (const list of template.querySelectorAll('ol')) {
			const parentLi = list.parentElement?.closest('li');
			const parentAvatar = parentLi ? avatarFor(parentLi) : null;
			if (!parentAvatar) {
				continue;
			}

			const parentRect = parentAvatar.getBoundingClientRect();
			const railX = Math.round(parentRect.left - templateRect.left + parentRect.width / 2);
			const startY = Math.round(parentRect.bottom - templateRect.top);

			for (const childLi of directChildren(list)) {
				const childAvatar = avatarFor(childLi);
				if (!childAvatar) {
					continue;
				}

				const childRect = childAvatar.getBoundingClientRect();
				const childX = Math.round(childRect.left - templateRect.left);
				const childY = Math.round(childRect.top - templateRect.top + childRect.height / 2);

				if (childY <= startY) {
					continue;
				}

				const childItem = itemFor(childLi);
				const targetId = childItem ? ensureId(childItem) : '';
				svg.appendChild(makePath(roundedConnector(railX, startY, childX, childY), targetId));
				pathCount += 1;
			}
		}

		if (pathCount > 0) {
			template.classList.add('has-ax-comment-connectors');
			template.prepend(svg);
		} else {
			template.classList.remove('has-ax-comment-connectors');
		}
	};

	const renderAll = () => {
		document.querySelectorAll(TEMPLATE_SELECTOR).forEach(renderTemplate);
		if (activeReplyItemId) {
			const item = document.getElementById(activeReplyItemId);
			if (item) {
				activateReplyChain(item);
			}
		}
	};

	// --- reply chain highlight -------------------------------------------------
	const escapeSel = (value) => (window.CSS && CSS.escape ? CSS.escape(value) : value.replace(/["\\]/g, '\\$&'));
	const connectorPathsFor = (targetId) =>
		document.querySelectorAll(`.ax-comment-connector[data-connector-target="${escapeSel(targetId)}"]`);

	const chainItemsFor = (item) => {
		const items = [];
		let li = item.closest('li');
		while (li) {
			const current = itemFor(li);
			if (current?.id) {
				items.unshift(current);
			}
			const list = li.parentElement?.closest('ol');
			li = list?.parentElement?.closest('li') || null;
		}
		return items;
	};

	function activateReplyChain(item) {
		chainItemsFor(item).forEach((chainItem) => {
			connectorPathsFor(chainItem.id).forEach((path) => {
				path.classList.add('is-reply-active');
				path.parentElement?.appendChild(path);
			});
		});
	}

	const clearReplyHighlight = () => {
		activeReplyItemId = '';
		document.querySelectorAll('.ax-comment-item.is-reply-target').forEach((i) => i.classList.remove('is-reply-target'));
		document.querySelectorAll('.ax-comment-connector.is-reply-active').forEach((p) => p.classList.remove('is-reply-active'));
	};

	// --- reply title (Facebook-style, reusing core's cancel link) --------------
	const reformatReplyTitle = (authorName) => {
		const respond = document.getElementById('respond');
		const title = document.getElementById('reply-title');
		const cancel = document.getElementById('cancel-comment-reply-link');
		if (!respond || !title || !cancel) {
			return;
		}

		respond.classList.add('ax-replying');
		cancel.textContent = CANCEL;

		title.textContent = `${REPLYING_TO} `;
		const name = document.createElement('strong');
		name.className = 'ax-reply-title__name';
		name.textContent = authorName;
		const separator = document.createElement('span');
		separator.className = 'ax-reply-title__separator';
		separator.setAttribute('aria-hidden', 'true');
		separator.textContent = '·';
		title.append(name, separator, cancel); // re-attach core's cancel link
	};

	const restoreReplyTitle = () => {
		const respond = document.getElementById('respond');
		const title = document.getElementById('reply-title');
		const cancel = document.getElementById('cancel-comment-reply-link');
		if (!title || !cancel) {
			return;
		}

		respond?.classList.remove('ax-replying');
		title.textContent = `${defaultReplyTitleText} `;
		title.append(cancel);
	};

	// Clicking the active comment's reply link again toggles the reply off. Run in
	// the capture phase so we beat core and turn it into a cancel instead of a re-open.
	const onReplyToggleCapture = (event) => {
		const replyLink = event.target.closest('.ax-comments-thread .wp-block-comment-reply-link a');
		if (!replyLink) {
			return;
		}
		const li = replyLink.closest('li');
		const item = li ? itemFor(li) : null;
		if (item && item.classList.contains('is-reply-target')) {
			event.preventDefault();
			event.stopPropagation();
			document.getElementById('cancel-comment-reply-link')?.click();
		}
	};

	const onReplyClick = (event) => {
		const replyLink = event.target.closest('.ax-comments-thread .wp-block-comment-reply-link a');
		if (replyLink) {
			const li = replyLink.closest('li');
			const item = li ? itemFor(li) : null;
			const authorName = (li?.querySelector('.wp-block-comment-author-name')?.textContent || '').trim();
			// Let core move the form + set comment_parent first, then dress it up and
			// re-measure (the moved form changes the layout the connectors depend on).
			requestAnimationFrame(() => {
				clearReplyHighlight();
				if (item) {
					item.classList.add('is-reply-target');
					activeReplyItemId = ensureId(item);
				}
				reformatReplyTitle(authorName);
				rerender();
			});
			return;
		}

		if (event.target.closest('#cancel-comment-reply-link')) {
			// Core restores the title + moves the form back; we drop the highlight and
			// restore the title once more because our custom title nodes live inside
			// core's #reply-title and can otherwise survive the core cancel pass.
			requestAnimationFrame(() => {
				clearReplyHighlight();
				restoreReplyTitle();
				rerender();
			});
		}
	};

	let scheduledFrame = 0;
	const rerender = () => {
		if (scheduledFrame) {
			cancelAnimationFrame(scheduledFrame);
		}
		scheduledFrame = requestAnimationFrame(() => {
			scheduledFrame = 0;
			renderAll();
		});
	};

	const init = () => {
		if (!document.querySelector(TEMPLATE_SELECTOR)) {
			return;
		}

		const title = document.getElementById('reply-title');
		const cancel = document.getElementById('cancel-comment-reply-link');
		if (title && cancel) {
			defaultReplyTitleText = (title.textContent || '')
				.replace(cancel.textContent || '', '')
				.trim() || DEFAULT_REPLY_TITLE;
		}

		document.addEventListener('click', onReplyToggleCapture, true); // capture: toggle off before core
		document.addEventListener('click', onReplyClick); // bubble: runs after core
		rerender();

		window.addEventListener('resize', rerender, { passive: true });
		window.addEventListener('load', rerender, { once: true });

		if ('ResizeObserver' in window) {
			const observer = new ResizeObserver(rerender);
			document.querySelectorAll(TEMPLATE_SELECTOR).forEach((template) => observer.observe(template));
		}

		document.querySelectorAll(`${TEMPLATE_SELECTOR} .wp-block-avatar img`).forEach((img) => {
			if (!img.complete) {
				img.addEventListener('load', rerender, { once: true });
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
})();
