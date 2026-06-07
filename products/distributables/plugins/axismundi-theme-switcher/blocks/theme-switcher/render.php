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

$axismundi_modes = array(
	'auto'  => array( 'icon' => 'contrast', 'label' => __( 'Auto', 'axismundi-theme-switcher' ) ),
	'light' => array( 'icon' => 'light_mode', 'label' => __( 'Light', 'axismundi-theme-switcher' ) ),
	'dark'  => array( 'icon' => 'dark_mode', 'label' => __( 'Dark', 'axismundi-theme-switcher' ) ),
);

$axismundi_current = 'auto';
if ( isset( $_COOKIE['axismundi_theme'] ) ) {
	$axismundi_cookie = sanitize_key( wp_unslash( $_COOKIE['axismundi_theme'] ) );
	if ( isset( $axismundi_modes[ $axismundi_cookie ] ) ) {
		$axismundi_current = $axismundi_cookie;
	}
}

$axismundi_class_name = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
$axismundi_is_cycle   = false !== strpos( ' ' . $axismundi_class_name . ' ', ' is-style-theme-cycle ' );

$axismundi_wrapper = get_block_wrapper_attributes(
	array(
		'role'                => 'group',
		'aria-label'          => __( 'Color scheme', 'axismundi-theme-switcher' ),
		'data-wp-interactive' => 'axismundi/theme-switcher',
	)
);
?>
<div <?php echo $axismundi_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $axismundi_is_cycle ) : ?>
		<button
			type="button"
			class="axismundi-theme-switcher__button axismundi-theme-switcher__cycle ax-icon-button is-standard has-state-layer t-theme-cycle"
			data-theme-cycle="true"
			data-wp-on--click="actions.cycleScheme"
			data-wp-bind--aria-label="state.cycleAriaLabel"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: current colour scheme. */ __( 'Color scheme: %s. Activate to cycle.', 'axismundi-theme-switcher' ), $axismundi_modes[ $axismundi_current ]['label'] ) ); ?>"
		>
			<span class="material-symbols-outlined" aria-hidden="true" data-wp-text="state.currentIcon"><?php echo esc_html( $axismundi_modes[ $axismundi_current ]['icon'] ); ?></span>
			<span class="screen-reader-text" data-wp-text="state.currentLabel"><?php echo esc_html( $axismundi_modes[ $axismundi_current ]['label'] ); ?></span>
		</button>
	<?php else : ?>
		<?php foreach ( $axismundi_modes as $axismundi_mode => $axismundi_m ) : ?>
			<button
				type="button"
				class="axismundi-theme-switcher__button wp-element-button"
				data-theme-mode="<?php echo esc_attr( $axismundi_mode ); ?>"
				<?php echo wp_interactivity_data_wp_context( array( 'mode' => $axismundi_mode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				data-wp-on--click="actions.setScheme"
				data-wp-bind--aria-pressed="state.isActive"
				aria-pressed="<?php echo $axismundi_mode === $axismundi_current ? 'true' : 'false'; ?>"
			>
				<span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( $axismundi_m['icon'] ); ?></span>
				<span class="axismundi-theme-switcher__label"><?php echo esc_html( $axismundi_m['label'] ); ?></span>
			</button>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
