/**
 * Motion figures — play on request, never on a loop.
 *
 * The dots travel on the tokens each row documents, so this script only
 * toggles a class; every duration and curve stays in CSS where a var() can
 * reach it. A reference page that animates forever is hard to read and is a
 * reduced-motion problem, so the reader presses play.
 */
(function () {
	'use strict';

	function bind(button) {
		var figure = document.getElementById(button.getAttribute('aria-controls'));
		if (!figure) {
			return;
		}
		button.addEventListener('click', function () {
			figure.classList.toggle('is-playing');
			button.textContent = figure.classList.contains('is-playing')
				? '되돌리기'
				: '재생';
		});
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-sg-motion-play]'),
			bind
		);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
