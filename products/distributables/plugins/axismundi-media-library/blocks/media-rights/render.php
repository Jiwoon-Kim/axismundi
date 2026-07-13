<?php
/**
 * Media Rights server render.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

$axismundi_media_rights_id = (int) ( $block->context['postId'] ?? 0 );
if ( ! $axismundi_media_rights_id && isset( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor preview context.
	$axismundi_media_rights_id = absint( $_GET['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
if ( $axismundi_media_rights_id <= 0 || 'attachment' !== get_post_type( $axismundi_media_rights_id ) ) {
	return;
}

global $axismundi_media_rights_rendered;
if ( $axismundi_media_rights_rendered ) {
	return;
}
$axismundi_media_rights_rendered = true;

$axismundi_media_rights_license         = axismundi_media_license_record( $axismundi_media_rights_id );
$axismundi_media_rights_attribution     = axismundi_media_attribution_formats( $axismundi_media_rights_id );
$axismundi_media_rights_creator         = trim( (string) get_post_meta( $axismundi_media_rights_id, '_ax_media_creator_name', true ) );
$axismundi_media_rights_creator_url     = (string) get_post_meta( $axismundi_media_rights_id, '_ax_media_creator_url', true );
$axismundi_media_rights_holder          = trim( (string) get_post_meta( $axismundi_media_rights_id, '_ax_media_copyright_holder_name', true ) );
$axismundi_media_rights_holder_url      = (string) get_post_meta( $axismundi_media_rights_id, '_ax_media_copyright_holder_url', true );
$axismundi_media_rights_notice          = trim( (string) get_post_meta( $axismundi_media_rights_id, '_ax_media_copyright_notice', true ) );
$axismundi_media_rights_source_url      = (string) get_post_meta( $axismundi_media_rights_id, '_ax_media_source_url', true );
$axismundi_media_rights_source_label    = $axismundi_media_rights_source_url ? (string) wp_parse_url( $axismundi_media_rights_source_url, PHP_URL_HOST ) : '';
$axismundi_media_rights_conditions      = $axismundi_media_rights_license['conditions'];
$axismundi_media_rights_condition_labels = array();

if ( $axismundi_media_rights_conditions['known'] ) {
	$axismundi_media_rights_condition_labels[] = $axismundi_media_rights_conditions['attribution'] ? __( 'Attribution required', 'axismundi-media-library' ) : __( 'Attribution not required', 'axismundi-media-library' );
	$axismundi_media_rights_condition_labels[] = $axismundi_media_rights_conditions['commercial'] ? __( 'Commercial use allowed', 'axismundi-media-library' ) : __( 'Noncommercial use only', 'axismundi-media-library' );
	$axismundi_media_rights_condition_labels[] = $axismundi_media_rights_conditions['derivatives'] ? __( 'Adaptations allowed', 'axismundi-media-library' ) : __( 'No adaptations', 'axismundi-media-library' );
	if ( $axismundi_media_rights_conditions['share_alike'] ) {
		$axismundi_media_rights_condition_labels[] = __( 'Share alike', 'axismundi-media-library' );
	}
}

$axismundi_media_rights_link = static function ( string $label, string $url ) : string {
	return '' !== $url
		? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'
		: esc_html( $label );
};
$axismundi_media_rights_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-media-rights' ) );
?>
<section <?php echo $axismundi_media_rights_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated wrapper attributes. ?>>
	<h2 class="ax-media-rights__heading"><?php esc_html_e( 'Rights & attribution', 'axismundi-media-library' ); ?></h2>
	<dl class="ax-media-rights__facts">
		<div><dt><?php esc_html_e( 'License', 'axismundi-media-library' ); ?></dt><dd><?php echo $axismundi_media_rights_link( $axismundi_media_rights_license['name'], $axismundi_media_rights_license['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?></dd></div>
		<?php if ( '' !== $axismundi_media_rights_creator ) : ?>
			<div><dt><?php esc_html_e( 'Creator', 'axismundi-media-library' ); ?></dt><dd><?php echo $axismundi_media_rights_link( $axismundi_media_rights_creator, $axismundi_media_rights_creator_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?></dd></div>
		<?php endif; ?>
		<?php if ( '' !== $axismundi_media_rights_holder ) : ?>
			<div><dt><?php esc_html_e( 'Copyright holder', 'axismundi-media-library' ); ?></dt><dd><?php echo $axismundi_media_rights_link( $axismundi_media_rights_holder, $axismundi_media_rights_holder_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped helper output. ?></dd></div>
		<?php endif; ?>
		<?php if ( '' !== $axismundi_media_rights_source_url ) : ?>
			<div><dt><?php esc_html_e( 'Source', 'axismundi-media-library' ); ?></dt><dd><a href="<?php echo esc_url( $axismundi_media_rights_source_url ); ?>"><?php echo esc_html( $axismundi_media_rights_source_label ?: __( 'View source', 'axismundi-media-library' ) ); ?></a></dd></div>
		<?php endif; ?>
	</dl>
	<?php if ( ! empty( $axismundi_media_rights_condition_labels ) ) : ?>
		<ul class="ax-media-rights__conditions" aria-label="<?php esc_attr_e( 'License conditions', 'axismundi-media-library' ); ?>">
			<?php foreach ( $axismundi_media_rights_condition_labels as $axismundi_media_rights_condition_label ) : ?>
				<li><?php echo esc_html( $axismundi_media_rights_condition_label ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( 'all-rights-reserved' === $axismundi_media_rights_license['code'] ) : ?>
		<p class="ax-media-rights__notice"><?php esc_html_e( 'No permission to reuse this work is granted by its license metadata.', 'axismundi-media-library' ); ?></p>
	<?php else : ?>
		<p class="ax-media-rights__notice"><?php esc_html_e( 'License terms are not specified. Verify the source before reuse.', 'axismundi-media-library' ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $axismundi_media_rights_notice ) : ?><p><?php echo esc_html( $axismundi_media_rights_notice ); ?></p><?php endif; ?>
	<?php if ( '' !== $axismundi_media_rights_attribution['plain'] ) : ?>
		<div class="ax-media-rights__attribution">
			<h3><?php esc_html_e( 'Attribution', 'axismundi-media-library' ); ?></h3>
			<p><?php echo esc_html( $axismundi_media_rights_attribution['plain'] ); ?></p>
			<div class="ax-media-rights__copy-actions">
				<button type="button" class="ax-media-rights__copy" data-copy-format="rich" data-copy-plain="<?php echo esc_attr( $axismundi_media_rights_attribution['plain'] ); ?>" data-copy-html="<?php echo esc_attr( $axismundi_media_rights_attribution['html'] ); ?>" data-copied-label="<?php esc_attr_e( 'Copied', 'axismundi-media-library' ); ?>" data-copy-failed-label="<?php esc_attr_e( 'Copy failed', 'axismundi-media-library' ); ?>"><?php esc_html_e( 'Copy attribution', 'axismundi-media-library' ); ?></button>
				<button type="button" class="ax-media-rights__copy" data-copy-format="markdown" data-copy-plain="<?php echo esc_attr( $axismundi_media_rights_attribution['markdown'] ); ?>" data-copied-label="<?php esc_attr_e( 'Copied', 'axismundi-media-library' ); ?>" data-copy-failed-label="<?php esc_attr_e( 'Copy failed', 'axismundi-media-library' ); ?>"><?php esc_html_e( 'Copy Markdown', 'axismundi-media-library' ); ?></button>
				<span class="ax-media-rights__status" aria-live="polite"></span>
			</div>
		</div>
	<?php endif; ?>
</section>
