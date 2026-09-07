<?php
$r = wp_safe_remote_get( 'https://calendar.google.com/calendar/ical/ko.south_korea%23holiday%40group.v.calendar.google.com/public/basic.ics', array( 'timeout' => 15 ) );
printf( "fetch: %s\n", is_wp_error( $r ) ? $r->get_error_code() . ' / ' . $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $r ) );
global $wpdb;
$t = axismundi_cal_calendars_table();
foreach ( (array) $wpdb->get_results( "SELECT id, name, slug FROM {$t} WHERE kind = 'system' ORDER BY id ASC", ARRAY_A ) as $row ) {
	printf( "  #%d %-22s %-30s items=%d\n", $row['id'], $row['name'], $row['slug'], (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . axismundi_cal_system_items_table() . " WHERE calendar_id = %d", (int) $row['id'] ) ) );
}
