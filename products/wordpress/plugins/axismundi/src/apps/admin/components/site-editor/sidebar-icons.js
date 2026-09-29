/*
 * The installed component package uses newer outline chevrons, while the
 * Gutenberg build running in this WordPress instance emits these filled
 * chevrons. Keep the local port visually and structurally version-aligned
 * with that host build.
 */
export const chevronLeft = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M14.6 7l-1.2-1L8 12l5.4 6 1.2-1-4.6-5z" />
	</svg>
);

export const chevronRight = (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M9.4 7l1.2-1 5.4 6-5.4 6-1.2-1 4.6-5z" />
	</svg>
);
