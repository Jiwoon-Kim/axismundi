<?php
/**
 * Account Header server render.
 *
 * A layout container only: it resolves one Actor and renders whatever its
 * nested Actor blocks produced. It owns no Actor-specific markup itself.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_account_header_actor = axismundi_actors_resolve_block_actor( (string) ( $attributes['actorId'] ?? '' ) );
if ( ! $axismundi_account_header_actor ) {
	return;
}
$axismundi_account_header_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-account-header' ) );
?>
<div <?php echo $axismundi_account_header_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner Actor blocks render and escape their own markup. ?>
</div>
