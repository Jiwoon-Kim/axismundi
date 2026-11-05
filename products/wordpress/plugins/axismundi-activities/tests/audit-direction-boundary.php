<?php
/** Temporary product regression alarm, not an ActivityStreams validity rule. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM %i WHERE direction NOT IN ('inbound', 'outbound')",
	axismundi_act_activities_table()
) );
if ( null === $count || '' !== $wpdb->last_error ) {
	throw new RuntimeException( 'Could not inspect the local/public Activity boundary.' );
}
if ( 0 !== (int) $count ) {
	throw new RuntimeException( 'The Activity ledger contains a legacy or invalid direction.' );
}
echo "PASS: 1 direction boundary checks\n";
