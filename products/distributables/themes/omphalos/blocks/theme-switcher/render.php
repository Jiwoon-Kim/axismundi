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
	'auto'  => array( 'icon' => 'brightness_medium', 'label' => __( 'Auto', 'omphalos' ) ),
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

$omphalos_wrapper = get_block_wrapper_attributes(
	array(
		'role'                => 'group',
		'aria-label'          => __( 'Color scheme', 'omphalos' ),
		'data-wp-interactive' => 'omphalos/theme-switcher',
	)
);
?>
<div <?php echo $omphalos_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
</div>
