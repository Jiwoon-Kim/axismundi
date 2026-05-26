<?php
/**
 * Title: Axismundi dev controls
 * Slug: axismundi-pilot/dev-controls
 * Categories: axismundi-dev
 * Inserter: true
 * Description: Theme mode toggle for development use.
 */
?>
<!-- wp:html -->
<div
	class="ax-theme-switcher"
	role="radiogroup"
	aria-label="<?php esc_attr_e( 'Theme', 'axismundi-pilot' ); ?>"
	data-wp-interactive="axismundi-pilot/color-scheme"
	data-wp-watch="callbacks.updateTheme"
>
	<button
		type="button"
		class="ax-theme-btn"
		role="radio"
		data-wp-context='{"mode":"light"}'
		data-wp-on--click="actions.setScheme"
		data-wp-bind--aria-checked="state.isActive"
	>Light</button>
	<button
		type="button"
		class="ax-theme-btn"
		role="radio"
		data-wp-context='{"mode":"dark"}'
		data-wp-on--click="actions.setScheme"
		data-wp-bind--aria-checked="state.isActive"
	>Dark</button>
	<button
		type="button"
		class="ax-theme-btn"
		role="radio"
		data-wp-context='{"mode":"auto"}'
		data-wp-on--click="actions.setScheme"
		data-wp-bind--aria-checked="state.isActive"
	>Auto</button>
</div>
<!-- /wp:html -->
