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
		aria-label="<?php esc_attr_e( 'Light', 'axismundi-pilot' ); ?>"
		data-wp-context='{"mode":"light"}'
		data-wp-on--click="actions.setScheme"
		data-wp-bind--aria-checked="state.isActive"
	><span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true">light_mode</span><span class="ax-theme-btn__label"><?php esc_html_e( 'Light', 'axismundi-pilot' ); ?></span></button>
	<button
		type="button"
		class="ax-theme-btn"
		role="radio"
		aria-label="<?php esc_attr_e( 'Dark', 'axismundi-pilot' ); ?>"
		data-wp-context='{"mode":"dark"}'
		data-wp-on--click="actions.setScheme"
		data-wp-bind--aria-checked="state.isActive"
	><span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true">dark_mode</span><span class="ax-theme-btn__label"><?php esc_html_e( 'Dark', 'axismundi-pilot' ); ?></span></button>
	<button
		type="button"
		class="ax-theme-btn"
		role="radio"
		aria-label="<?php esc_attr_e( 'Auto', 'axismundi-pilot' ); ?>"
		data-wp-context='{"mode":"auto"}'
		data-wp-on--click="actions.setScheme"
		data-wp-bind--aria-checked="state.isActive"
	><span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true">brightness_auto</span><span class="ax-theme-btn__label"><?php esc_html_e( 'Auto', 'axismundi-pilot' ); ?></span></button>
</div>
<!-- /wp:html -->
