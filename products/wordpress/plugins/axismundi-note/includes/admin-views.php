<?php
/**
 * Filter links across the top of the Notes list, as Comments offers its status views.
 *
 * Notes have no approval queue, so the axes that matter here are different: which Object form a Note
 * takes, and who may see it. Core's own status links (All, Published, Draft, Trash) are left in
 * place above these — they answer a different question, and replacing them would lose Trash.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post ids for one Object form.
 *
 * The three sets are derived from each other rather than queried independently, which is what keeps
 * them exclusive: a row that somehow carried both a poll and a quote target would otherwise appear
 * under two views and be counted twice. Trashed Notes are excluded throughout, so a count never
 * disagrees with the list its own link leads to.
 *
 * @param string $form note|question|quote.
 * @return int[]
 */
function axismundi_note_admin_form_ids( string $form ) : array {
	global $wpdb;
	if ( ! function_exists( 'axismundi_note_ready' ) || ! axismundi_note_ready() ) {
		return array();
	}
	$table = axismundi_note_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin filter over a custom table.
	$quoted = array_map( 'intval', (array) $wpdb->get_col( "SELECT n.post_id FROM {$table} n INNER JOIN {$wpdb->posts} p ON p.ID = n.post_id WHERE p.post_status <> 'trash' AND n.quote_target_uri IS NOT NULL AND n.quote_target_uri <> ''" ) );
	if ( 'quote' === $form ) {
		return $quoted;
	}
	$asked = array();
	if ( function_exists( 'axismundi_note_questions_table' ) ) {
		$questions = axismundi_note_questions_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin filter over a custom table.
		$asked = array_map( 'intval', (array) $wpdb->get_col( "SELECT q.note_post_id FROM {$questions} q INNER JOIN {$wpdb->posts} p ON p.ID = q.note_post_id WHERE p.post_status <> 'trash'" ) );
	}
	if ( 'question' === $form ) {
		return array_values( array_diff( $asked, $quoted ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin filter over a custom table.
	$all = array_map( 'intval', (array) $wpdb->get_col( "SELECT n.post_id FROM {$table} n INNER JOIN {$wpdb->posts} p ON p.ID = n.post_id WHERE p.post_status <> 'trash'" ) );
	return array_values( array_diff( $all, $quoted, $asked ) );
}

/**
 * The Object-form and visibility views.
 *
 * A view with no rows is dropped rather than shown as a zero, except when it is the one currently
 * selected — a link that vanishes the moment you follow it leaves no way back to the full list.
 *
 * @param array<string,string> $views Existing views.
 * @return array<string,string>
 */
function axismundi_note_admin_views( array $views ) : array {
	global $wpdb;
	if ( ! function_exists( 'axismundi_note_ready' ) || ! axismundi_note_ready() ) {
		return $views;
	}
	$table = axismundi_note_table();
	$base  = admin_url( 'edit.php?post_type=' . AXISMUNDI_NOTE_POST_TYPE );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin counts over a custom table.
	$rows       = (array) $wpdb->get_results( "SELECT n.visibility AS k, COUNT(*) AS c FROM {$table} n INNER JOIN {$wpdb->posts} p ON p.ID = n.post_id WHERE p.post_status <> 'trash' GROUP BY n.visibility", ARRAY_A );
	$visibility = array();
	foreach ( $rows as $row ) {
		$visibility[ (string) $row['k'] ] = (int) $row['c'];
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
	$active_form = isset( $_GET['ax_form'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_form'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
	$active_tier = isset( $_GET['ax_visibility'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_visibility'] ) ) : '';

	$link = static function ( string $url, string $label, int $count, bool $current ) : string {
		return '<a href="' . esc_url( $url ) . '"' . ( $current ? ' class="current" aria-current="page"' : '' ) . '>'
			. esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
	};

	$forms = array(
		'note'     => __( 'Notes', 'axismundi-note' ),
		'question' => __( 'Questions', 'axismundi-note' ),
		'quote'    => __( 'Quotes', 'axismundi-note' ),
	);
	foreach ( $forms as $form => $label ) {
		$count = count( axismundi_note_admin_form_ids( $form ) );
		if ( $count < 1 && $form !== $active_form ) {
			continue;
		}
		$views[ 'ax_form_' . $form ] = $link( add_query_arg( 'ax_form', $form, $base ), $label, $count, $form === $active_form );
	}

	$tiers = array(
		'public'    => __( 'Public', 'axismundi-note' ),
		'unlisted'  => __( 'Unlisted', 'axismundi-note' ),
		'followers' => __( 'Followers', 'axismundi-note' ),
		'mentioned' => __( 'Mentioned', 'axismundi-note' ),
	);
	foreach ( $tiers as $tier => $label ) {
		$count = (int) ( $visibility[ $tier ] ?? 0 );
		if ( $count < 1 && $tier !== $active_tier ) {
			continue;
		}
		$views[ 'ax_visibility_' . $tier ] = $link( add_query_arg( 'ax_visibility', $tier, $base ), $label, $count, $tier === $active_tier );
	}
	return $views;
}
add_filter( 'views_edit-' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_admin_views' );

/**
 * Apply the selected views to the list query.
 *
 * Narrowing by post id rather than joining the envelope table: `WP_Query` owns this query, and a
 * join filtered in from here would have to survive every other clause the screen adds. An empty
 * selection becomes `post__in => array( 0 )` so an empty view shows an empty list rather than
 * silently showing every Note.
 *
 * @param WP_Query $query Admin list query.
 * @return void
 */
function axismundi_note_admin_filter_views( WP_Query $query ) : void {
	global $wpdb;
	if ( ! is_admin() || ! $query->is_main_query() || AXISMUNDI_NOTE_POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
	$form = isset( $_GET['ax_form'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_form'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
	$tier = isset( $_GET['ax_visibility'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_visibility'] ) ) : '';
	if ( '' === $form && '' === $tier ) {
		return;
	}
	if ( ! function_exists( 'axismundi_note_ready' ) || ! axismundi_note_ready() ) {
		return;
	}
	$ids = null;
	if ( in_array( $tier, array( 'public', 'unlisted', 'followers', 'mentioned' ), true ) ) {
		$table = axismundi_note_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin filter over a custom table.
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE visibility = %s", $tier ) ) );
	}
	if ( in_array( $form, array( 'note', 'question', 'quote' ), true ) ) {
		$form_ids = axismundi_note_admin_form_ids( $form );
		$ids      = null === $ids ? $form_ids : array_values( array_intersect( $ids, $form_ids ) );
	}
	if ( null !== $ids ) {
		$query->set( 'post__in', empty( $ids ) ? array( 0 ) : $ids );
	}
}
add_action( 'pre_get_posts', 'axismundi_note_admin_filter_views' );
