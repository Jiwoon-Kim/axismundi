<?php
/** Source-reader/type-assembly boundary regression (dev-only). */
defined( 'ABSPATH' ) || exit( 1 );
$results = array();
function ax_assembly_assert( array &$results, string $label, bool $holds ) : void {
	$results[] = $holds;
	printf( "[%s] %s\n", $holds ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit.
}
$assemblies_before = axismundi_op_object_assemblies();
try {
	$calls = 0;
	axismundi_op_register_object_assembly( 'Article', static function ( array $facts ) use ( &$calls ) : array {
		++$calls;
		return axismundi_op_assemble_object_view_facts( $facts );
	} );
	$post = get_page_by_path( 'vqa-demo-post-1', OBJECT, 'post' );
	if ( ! $post instanceof WP_Post ) {
		throw new RuntimeException( 'The baseline Article fixture is missing.' );
	}
	$object = axismundi_op_transform_object( $post );
	$remote = new Axismundi_Op_Remote_Source( array(
		'object_uri' => $object['id'], 'object_type' => 'Article',
		'object_status' => 'active', 'attributed_to_uri' => $object['attributedTo'] ?? '',
		'payload' => $object, 'human_url' => get_permalink( $post ),
		'published_at' => $post->post_date_gmt, 'remote_updated_at' => $post->post_modified_gmt,
		'is_sensitive' => ! empty( $object['sensitive'] ),
	) );
	$local_facts = axismundi_op_read_object_source( $post );
	$remote_facts = axismundi_op_read_object_source( $remote );
	ax_assembly_assert( $results, '[project choice] source acquisition returns typed facts before executing any assembly', 0 === $calls && 'Article' === ( $local_facts['type'] ?? '' ) && 'Article' === ( $remote_facts['type'] ?? '' ) );
	$local_model = axismundi_op_object_view_model( $post );
	$remote_model = axismundi_op_object_view_model( $remote );
	ax_assembly_assert( $results, '[project choice] local and cached Article readers both invoke the same type assembly', 2 === $calls );
	foreach ( array( 'type', 'title', 'content_html', 'summary', 'sensitive', 'image', 'media', 'presentation', 'author', 'published', 'updated' ) as $field ) {
		ax_assembly_assert( $results, '[project choice] equivalent local and observed Article facts agree on ' . $field, is_array( $local_model ) && is_array( $remote_model ) && ( $local_model[ $field ] ?? null ) === ( $remote_model[ $field ] ?? null ) );
	}
	ax_assembly_assert( $results, '[project choice] assembly does not bind block request enrichment', ! isset( $local_model['_enriched'] ) && ! isset( $remote_model['_enriched'] ) );
	$minimal = axismundi_op_assemble_object_facts( array( 'type' => 'Note', 'content_html' => '', 'content_warning' => '' ) );
	foreach ( axismundi_op_object_view_model_defaults() as $key => $default ) {
		ax_assembly_assert( $results, '[project choice] an unspecified ' . $key . ' retains its contract default', is_array( $minimal ) && array_key_exists( $key, $minimal ) && $default === $minimal[ $key ] );
	}
	axismundi_op_register_object_assembly( 'Document', 'axismundi_op_assemble_object_view_facts' );
	$inherited = axismundi_op_resolve_object_assembly( 'Image' );
	ax_assembly_assert( $results, '[project choice; AS2 hierarchy] Image inherits its nearest Document assembly', is_array( $inherited ) && 'Document' === $inherited['type'] );
	axismundi_op_register_object_assembly( 'Activity', 'axismundi_op_assemble_object_view_facts' );
	$question = axismundi_op_resolve_object_assembly( 'Question' );
	ax_assembly_assert( $results, '[project choice; AS2 hierarchy] Question inherits an Activity specialization through its registered parents', is_array( $question ) && 'Activity' === $question['type'] );
	$duplicate = axismundi_op_register_object_assembly( 'Article', '__return_empty_array' );
	ax_assembly_assert( $results, '[project choice] duplicate type registrations preserve the existing assembly', is_wp_error( $duplicate ) );
	$error_codes = array();
	$observe_error = static function ( WP_Error $error ) use ( &$error_codes ) : void { $error_codes[] = $error->get_error_code(); };
	axismundi_op_register_object_assembly( 'Note', static function ( array $facts ) : array { throw new RuntimeException( 'fixture' ); } );
	add_action( 'axismundi_op_object_assembly_error', $observe_error );
	try {
		$failed_model = axismundi_op_normalize_object_view_model( array( 'type' => 'Note' ) );
	} finally {
		remove_action( 'axismundi_op_object_assembly_error', $observe_error );
	}
	ax_assembly_assert( $results, '[project choice] a throwing type assembly cannot produce a partial model', array() === $failed_model );
	ax_assembly_assert( $results, '[project choice] compatibility assembly failures emit their explicit diagnostic', array( 'ax_op_assembly_threw' ) === $error_codes );
} finally {
	$GLOBALS['axismundi_op_object_assemblies'] = $assemblies_before;
}
$failures = count( array_filter( $results, static fn( bool $result ) : bool => ! $result ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit.
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures ? 1 : 0 );
}
