<?php
/**
 * The core attachment profile: the Object one WordPress install projects without the
 * Media Library plugin. It covers the role-dependent `name` contract from the standalone
 * side (MEDIA-RENDITIONS.md section 5), where the Media Library audit covers the embedded
 * side and the same contract under the first-party adapter.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/post-article.php';

$ax_core_attachment_results = array();

function ax_core_attachment_assert( array &$results, string $label, bool $passed ) : void {
	$results[] = $passed;
	echo ( $passed ? '[PASS] ' : '[FAIL] ' ) . $label . "\n";
}

$ax_core_attachment_id = (int) wp_insert_attachment(
	array(
		'post_title'     => 'Holiday photo',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/jpeg',
		'post_excerpt'   => 'A caption.',
		'post_content'   => 'A longer description.',
	)
);
update_post_meta( $ax_core_attachment_id, '_wp_attached_file', 'probe/core-attachment.jpg' );
update_post_meta( $ax_core_attachment_id, '_wp_attachment_image_alt', 'Alt text for screen readers' );

try {
	$ax_core_attachment_object = axismundi_op_core_attachment_transform( get_post( $ax_core_attachment_id ) );
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'the core attachment profile names the object with its own title, never its alt text',
		is_array( $ax_core_attachment_object ) && 'Holiday photo' === ( $ax_core_attachment_object['name'] ?? '' )
	);
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'the WordPress caption becomes summary and the description becomes content',
		is_array( $ax_core_attachment_object )
			&& 'A caption.' === ( $ax_core_attachment_object['summary'] ?? '' )
			&& false !== strpos( (string) ( $ax_core_attachment_object['content'] ?? '' ), 'A longer description.' )
	);
	// Alt text is the embedded descriptor's property. On the standalone object there is no
	// settled ActivityStreams place for it, so it is omitted rather than put somewhere it
	// would be read as the object's name.
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'no alt text appears anywhere in the standalone payload',
		is_array( $ax_core_attachment_object )
			&& false === strpos( (string) wp_json_encode( $ax_core_attachment_object ), 'Alt text for screen readers' )
	);
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'uploading is storage, so the standalone object is attributed to nobody by default',
		is_array( $ax_core_attachment_object ) && ! isset( $ax_core_attachment_object['attributedTo'] )
	);
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'identity and media type stay present for an attachment nobody has published',
		is_array( $ax_core_attachment_object )
			&& axismundi_op_core_attachment_uri( get_post( $ax_core_attachment_id ) ) === ( $ax_core_attachment_object['id'] ?? '' )
			&& 'Image' === ( $ax_core_attachment_object['type'] ?? '' )
			&& 'image/jpeg' === ( $ax_core_attachment_object['mediaType'] ?? '' )
	);

	// Two statuses that look alike and are not. An attachment page is its own public
	// surface, so the ordinary `inherit` attachment of a private parent stays projectable;
	// an attachment stored with an explicitly private status is withheld.
	$ax_core_private_parent = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_author' => 1, 'post_title' => 'Private parent' ) );
	$ax_core_inherited = (int) wp_insert_attachment( array( 'post_title' => 'Inherited child', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_parent' => $ax_core_private_parent ) );
	update_post_meta( $ax_core_inherited, '_wp_attached_file', 'probe/inherited.jpg' );
	$ax_core_private = (int) wp_insert_attachment( array( 'post_title' => 'Private attachment', 'post_status' => 'private', 'post_mime_type' => 'image/jpeg', 'post_author' => 1 ) );
	update_post_meta( $ax_core_private, '_wp_attached_file', 'probe/private.jpg' );
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'the inherit attachment of a private parent remains a public Object',
		axismundi_op_core_attachment_visible( get_post( $ax_core_inherited ) )
	);
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'an attachment stored with an explicitly private status fails closed',
		! axismundi_op_core_attachment_visible( get_post( $ax_core_private ) )
			&& is_wp_error( axismundi_op_transform_object( get_post( $ax_core_private ) ) )
	);
	wp_delete_attachment( $ax_core_inherited, true );
	wp_delete_attachment( $ax_core_private, true );
	wp_delete_post( $ax_core_private_parent, true );

	wp_update_post( array( 'ID' => $ax_core_attachment_id, 'post_title' => '' ) );
	$ax_core_attachment_untitled = axismundi_op_core_attachment_transform( get_post( $ax_core_attachment_id ) );
	ax_core_attachment_assert(
		$ax_core_attachment_results,
		'an untitled attachment omits name rather than publishing a placeholder',
		is_array( $ax_core_attachment_untitled ) && ! isset( $ax_core_attachment_untitled['name'] )
	);
} finally {
	wp_delete_attachment( $ax_core_attachment_id, true );
}

$ax_core_attachment_failed = count( array_filter( $ax_core_attachment_results, static fn( $passed ) => ! $passed ) );
echo "\n== " . count( $ax_core_attachment_results ) . ' checks, ' . $ax_core_attachment_failed . " failed ==\n";
if ( $ax_core_attachment_failed > 0 ) {
	exit( 1 );
}
