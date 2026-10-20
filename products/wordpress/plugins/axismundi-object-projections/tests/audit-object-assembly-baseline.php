<?php
/** Read-only live-fixture hashes: pre-split evidence plus the approved 2026-10-20 media policy. */
defined( 'ABSPATH' ) || exit( 1 );
$post = get_page_by_path( 'vqa-demo-post-1', OBJECT, 'post' );
if ( ! $post instanceof WP_Post ) {
	throw new RuntimeException( 'The published baseline Article is missing.' );
}
$baseline = array();
foreach ( array( 'article' => $post, 'image' => get_post( 166 ), 'audio' => get_post( 25 ) ) as $name => $source ) {
	$object = axismundi_op_transform_object( $source );
	if ( ! is_array( $object ) ) {
		throw new RuntimeException( 'The published baseline projection is unavailable: ' . $name );
	}
	$baseline[ $name ] = hash( 'sha256', wp_json_encode( $object ) );
	if ( 'article' !== $name ) {
		$descriptor = axismundi_op_attachment_descriptor( $source->ID );
		if ( ! is_array( $descriptor ) || empty( $descriptor['id'] ) ) {
			throw new RuntimeException( 'A baseline embedded attachment descriptor is missing.' );
		}
		$baseline[ $name . '-embedded' ] = hash( 'sha256', wp_json_encode( $descriptor ) );
	}
	$baseline[ $name . '-view' ] = hash( 'sha256', wp_json_encode( axismundi_op_object_view_model( $source ) ) );
	if ( 'article' === $name ) {
		$baseline['attachments'] = hash( 'sha256', wp_json_encode( $object['attachment'] ?? array() ) );
	}
}
$pre_policy = array(
	'article' => '69cc691bd81bfa568b0149a1738cf832dd426198c23137fcf0577647ec16a1ad',
	'article-view' => '6114cdb2cf794fae5cdaa152ab68a0b0766065ca05ef47da21704abbef2e0a3e',
	'attachments' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945',
	'image' => 'deead6e32f57e438de0ba60a3d14dac6aaa0d63fa0e2e7280d31c92e234dda93',
	'image-embedded' => 'dfbc7d4d60946d5af03a9ca4405704442a4be05657c19d774ecd510e8089407e',
	'image-view' => '1f57445849e66e37cc254ff2e45e6a4020baabe5eafec851a61563e454a6cac2',
	'audio' => 'd1ba8c2906daa6d7541d04bc849fbe6cb106f06d7d2c90f6dad1602ad7b9a9f3',
	'audio-embedded' => '6a4e80ab4ed3bff65980abd15ba5cae9460c1a69979419f428fc56fb44bb66a9',
	'audio-view' => '26a3d8e2cedb9096dc1038a4bd8619da4db20772a52ef0772e497e4f624d7442',
);
// Preserve the earlier evidence; scalar page URLs and the audio cover deliberately
// change standalone/view bytes. Embedded media URLs and identities stay unchanged.
$media_policy = array_replace( $pre_policy, array(
	'article' => '86ed73e366779e528d503fb22f90cafe7276f2b4525f1eea9bf5a7af3a2293b0',
	'image' => 'c8a77781c55486de48b34a1120134bef31910bb3fc2e337c13d5ab672e66cebf',
	'image-view' => '40c042e7043f55106873ea972bf929b9fc64d91e4ec997be717e7fccae5a3409',
	'audio' => '8980efc489bc26caff62839811d6efb03cd3f0e05dea7639d2f7aff276815e6d',
	'audio-view' => 'cec22153f8cb965237997d7aef68bf006f9c318fe9f3728e06d330220b4f17cc',
) );
// The owner edited the live Article after the media-policy capture: a trailing
// empty paragraph, updated timestamp, Actor attribution/addressing and canQuote
// now differ. Do not revert that authored data or attribute it to tag-index probes.
$expected = array_replace( $media_policy, array(
	'article' => '0686d44f255d09c79401be5224433509ba6f546d89414189d45d2b7665d5f324',
	'article-view' => 'def6829c984ebd1d945f10aeb00f8b6c9fb610ad3df6c4672e505da8a97f94bf',
) );
// Owner-approved 2026-10-21: only standalone Image and its view gain the full
// source Link and top-level source dimensions. Embedded wire bytes stay pinned.
$expected = array_replace( $expected, array(
	'image' => '7e1140a064a707d70f50f762c510278469110338e2e7b1fe6c4f67da6ea04f85',
	'image-view' => 'b3efcc62e59eb97156f097b52886c6e6f1e26c1a3eca642ad9d0848c0d59b2fe',
) );
if ( '2026-10-20 11:59:06' !== $post->post_modified_gmt ) {
	printf( "Fixture source changed: Article modified_gmt=%s; expected 2026-10-20 11:59:06. Inspect source data before rebaselining.\n", $post->post_modified_gmt );
}
$failures = 0;
foreach ( $expected as $name => $hash ) {
	$holds = hash_equals( $hash, $baseline[ $name ] ?? '' );
	$failures += $holds ? 0 : 1;
	if ( ! $holds ) {
		printf( "%s current SHA-256: %s\n", $name, $baseline[ $name ] ?? 'missing' );
	}
	printf( "[%s] [project choice] %s bytes match the approved media-policy fixture\n", $holds ? 'PASS' : 'FAIL', $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit.
}
printf( "\n== %d checks, %d failed ==\n", count( $expected ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit.
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures ? 1 : 0 );
}
