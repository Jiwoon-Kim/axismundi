<?php
/**
 * Theme switcher block — server render.
 *
 * SSR reads the axismundi_theme cookie as a best-effort initial active state.
 * The plugin head script is authoritative for early data-theme application, and
 * the Interactivity store re-syncs the UI after hydration.
 *
 * @package AxismundiThemeSwitcher
 */

defined( 'ABSPATH' ) || exit;

$axismundi_theme_switcher_modes = array(
	'auto'  => array( 'icon' => 'contrast', 'label' => __( 'Auto', 'axismundi-theme-switcher' ) ),
	'light' => array( 'icon' => 'light_mode', 'label' => __( 'Light', 'axismundi-theme-switcher' ) ),
	'dark'  => array( 'icon' => 'dark_mode', 'label' => __( 'Dark', 'axismundi-theme-switcher' ) ),
);

$axismundi_theme_switcher_current = 'auto';
if ( isset( $_COOKIE['axismundi_theme'] ) ) {
	$axismundi_theme_switcher_cookie = sanitize_key( wp_unslash( $_COOKIE['axismundi_theme'] ) );
	if ( isset( $axismundi_theme_switcher_modes[ $axismundi_theme_switcher_cookie ] ) ) {
		$axismundi_theme_switcher_current = $axismundi_theme_switcher_cookie;
	}
}

/*
 * Which component to render. `icon` is one button cycling through the modes;
 * `group` is three. That is a different control, not a restyle -- a different
 * number of buttons with a different keyboard model -- so it is an attribute
 * rather than a block style.
 *
 * Before 0.1.7 the choice rode on the `is-style-theme-cycle` class and this file
 * matched the string. Content saved then still carries that class and no
 * attribute, so it is still honoured when `component` is unset.
 */
$axismundi_theme_switcher_component = isset( $attributes['component'] ) ? (string) $attributes['component'] : '';

if ( '' === $axismundi_theme_switcher_component ) {
	$axismundi_theme_switcher_class_name = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
	$axismundi_theme_switcher_component  = false !== strpos( ' ' . $axismundi_theme_switcher_class_name . ' ', ' is-style-theme-cycle ' ) ? 'icon' : 'group';
}

$axismundi_theme_switcher_is_cycle = 'icon' === $axismundi_theme_switcher_component;

/*
 * Group segments can drop their visible label. The label text is still rendered,
 * as screen-reader text, so it stays each button's accessible name and there is
 * one source for the string. core/navigation does the same thing the other way
 * round: its toggle carries an `aria-label` only when the visible text is
 * replaced by an icon.
 */
$axismundi_theme_switcher_show_labels = ! isset( $attributes['showLabels'] ) || (bool) $attributes['showLabels'];
$axismundi_theme_switcher_label_class = $axismundi_theme_switcher_show_labels
	? 'axismundi-theme-switcher__label'
	: 'axismundi-theme-switcher__label screen-reader-text';

$axismundi_theme_switcher_wrapper_attrs = array(
	'role'                => 'group',
	'aria-label'          => __( 'Color scheme', 'axismundi-theme-switcher' ),
	'data-component'      => $axismundi_theme_switcher_component,
	'data-wp-interactive' => 'axismundi/theme-switcher',
);

// Only the group has a label to show or hide, so only the group says so.
if ( ! $axismundi_theme_switcher_is_cycle ) {
	$axismundi_theme_switcher_wrapper_attrs['data-labels'] = $axismundi_theme_switcher_show_labels ? 'visible' : 'hidden';
}

$axismundi_theme_switcher_wrapper = get_block_wrapper_attributes( $axismundi_theme_switcher_wrapper_attrs );
?>
<div <?php echo $axismundi_theme_switcher_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $axismundi_theme_switcher_is_cycle ) : ?>
		<button
			type="button"
			class="axismundi-theme-switcher__button axismundi-theme-switcher__cycle"
			data-theme-cycle="true"
			data-wp-on--click="actions.cycleScheme"
			data-wp-bind--aria-label="state.cycleAriaLabel"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: current colour scheme. */ __( 'Color scheme: %s. Activate to cycle.', 'axismundi-theme-switcher' ), $axismundi_theme_switcher_modes[ $axismundi_theme_switcher_current ]['label'] ) ); ?>"
		>
			<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false" data-wp-text="state.currentIcon"><?php echo esc_html( $axismundi_theme_switcher_modes[ $axismundi_theme_switcher_current ]['icon'] ); ?></span>
			<span class="screen-reader-text" data-wp-text="state.currentLabel"><?php echo esc_html( $axismundi_theme_switcher_modes[ $axismundi_theme_switcher_current ]['label'] ); ?></span>
		</button>
	<?php else : ?>
		<?php foreach ( $axismundi_theme_switcher_modes as $axismundi_theme_switcher_mode => $axismundi_theme_switcher_m ) : ?>
			<button
				type="button"
				class="axismundi-theme-switcher__button wp-element-button"
				data-theme-mode="<?php echo esc_attr( $axismundi_theme_switcher_mode ); ?>"
				<?php echo wp_interactivity_data_wp_context( array( 'mode' => $axismundi_theme_switcher_mode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				data-wp-on--click="actions.setScheme"
				data-wp-bind--aria-pressed="state.isActive"
				aria-pressed="<?php echo $axismundi_theme_switcher_mode === $axismundi_theme_switcher_current ? 'true' : 'false'; ?>"
			>
				<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false"><?php echo esc_html( $axismundi_theme_switcher_m['icon'] ); ?></span>
				<span class="<?php echo esc_attr( $axismundi_theme_switcher_label_class ); ?>"><?php echo esc_html( $axismundi_theme_switcher_m['label'] ); ?></span>
			</button>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
