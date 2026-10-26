<?php
/** Temporary product regression alarm, not an ActivityStreams validity rule. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM %i WHERE direction = 'local' AND has_public_audience = 1",
	axismundi_act_activities_table()
) );
if ( null === $count || '' !== $wpdb->last_error ) {
	throw new RuntimeException( 'Could not inspect the local/public Activity boundary.' );
}
if ( 0 !== (int) $count ) {
	throw new RuntimeException( 'Public local Activities require the direction/authorship migration; current outbox queries omit them.' );
}
echo "PASS: No public local Activities are hidden by the current outbox direction filter\n";
