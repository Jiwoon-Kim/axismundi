<?php
/**
 * Theme switcher block — server render (front end).
 *
 * Active state is BEST-EFFORT for uncached renders: it reads the omphalos_theme
 * cookie to seed aria-pressed. The authoritative scheme application is the head
 * script (inc/theme-switcher.php); under full-page caching this SSR active state
 * can be stale, so the Phase 3 Interactivity view module re-syncs aria-pressed on
 * hydration. The editor canvas uses edit.js (static, auto active), not this file.
 *
 * @package Omphalos
 */

defined( 'ABSPATH' ) || exit;

$omphalos_modes = array(
	'auto'  => array( 'icon' => 'contrast', 'label' => __( 'Auto', 'omphalos' ) ),
	'light' => array( 'icon' => 'light_mode', 'label' => __( 'Light', 'omphalos' ) ),
	'dark'  => array( 'icon' => 'dark_mode', 'label' => __( 'Dark', 'omphalos' ) ),
);

// Whitelisted cookie → initial active mode (default auto).
$omphalos_current = 'auto';
if ( isset( $_COOKIE['omphalos_theme'] ) ) {
	$omphalos_cookie = sanitize_key( wp_unslash( $_COOKIE['omphalos_theme'] ) );
	if ( isset( $omphalos_modes[ $omphalos_cookie ] ) ) {
		$omphalos_current = $omphalos_cookie;
	}
}

$omphalos_class_name = isset( $attributes['className'] ) ? (string) $attributes['className'] : '';
$omphalos_is_cycle   = false !== strpos( ' ' . $omphalos_class_name . ' ', ' is-style-theme-cycle ' );

$omphalos_wrapper = get_block_wrapper_attributes(
	array(
		'role'                => 'group',
		'aria-label'          => __( 'Color scheme', 'omphalos' ),
		'data-wp-interactive' => 'omphalos/theme-switcher',
	)
);
?>
<div <?php echo $omphalos_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $omphalos_is_cycle ) : ?>
		<button
			type="button"
			class="omphalos-theme-switcher__button omphalos-theme-switcher__cycle ax-icon-button is-standard has-state-layer t-theme-cycle"
			data-theme-cycle="true"
			data-wp-on--click="actions.cycleScheme"
			data-wp-bind--aria-label="state.cycleAriaLabel"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: current colour scheme. */ __( 'Color scheme: %s. Activate to cycle.', 'omphalos' ), $omphalos_modes[ $omphalos_current ]['label'] ) ); ?>"
		>
			<span class="material-symbols-outlined" aria-hidden="true" data-wp-text="state.currentIcon"><?php echo esc_html( $omphalos_modes[ $omphalos_current ]['icon'] ); ?></span>
			<span class="screen-reader-text" data-wp-text="state.currentLabel"><?php echo esc_html( $omphalos_modes[ $omphalos_current ]['label'] ); ?></span>
		</button>
	<?php else : ?>
		<?php foreach ( $omphalos_modes as $omphalos_mode => $omphalos_m ) : ?>
			<button
				type="button"
				class="omphalos-theme-switcher__button wp-element-button"
				data-theme-mode="<?php echo esc_attr( $omphalos_mode ); ?>"
				<?php echo wp_interactivity_data_wp_context( array( 'mode' => $omphalos_mode ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				data-wp-on--click="actions.setScheme"
				data-wp-bind--aria-pressed="state.isActive"
				aria-pressed="<?php echo $omphalos_mode === $omphalos_current ? 'true' : 'false'; ?>"
			>
				<span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( $omphalos_m['icon'] ); ?></span>
				<span class="omphalos-theme-switcher__label"><?php echo esc_html( $omphalos_m['label'] ); ?></span>
			</button>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
