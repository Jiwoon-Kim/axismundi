<?php
/**
 * Question answers: the poll-vote ledger as its own admin screen.
 *
 * A Question's options carry counts, and those counts are a projection — they are what is left after
 * undone, ignored and deleted votes have been folded away. This screen shows the rows those numbers
 * were made from, which is the only place a disagreement between a tally and its ledger can be seen.
 *
 * Separate from the Notes list on purpose. That list is one row per authored Object; this is one row
 * per Actor's answer, and the two do not share a subject. `Questions` there is a filter over Objects
 * and cannot stand in for the response ledger, the same way Mastodon keeps `poll_votes` beside
 * `polls` and Misskey keeps `poll_vote` beside the Note's poll rather than counting in place.
 *
 * What this adds over those two: a vote here is evidence of a federated Activity, so the row carries
 * the Activity URI and a status — `active`, `undone`, `ignored`, `deleted` — instead of existing or
 * not existing. A vote that was withdrawn is not a missing row; it is a row that says so.
 *
 * The scope is local authoritative Questions only, and the screen says so rather than implying it
 * holds every answer in the fediverse. `ax_poll_votes` records answers to Questions this site owns;
 * a remote Question's results arrive as counts inside a cached Object and are a projection of
 * somebody else's ledger, which this site cannot audit and must not appear to.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The poll-vote ledger table.
 */
class Axismundi_Note_Answers_Table extends WP_List_Table {

	/**
	 * Counts per vote status for the current question filter.
	 *
	 * @var array<string,int>
	 */
	public $status_counts = array();

	/** Construct. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ax_answer',
				'plural'   => 'ax_answers',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() : array {
		return array(
			'question' => __( 'Question', 'axismundi-note' ),
			'voter'    => __( 'Voter', 'axismundi-note' ),
			'choice'   => __( 'Answer', 'axismundi-note' ),
			'status'   => __( 'Status', 'axismundi-note' ),
			'activity' => __( 'Activity', 'axismundi-note' ),
			'recorded' => __( 'Recorded', 'axismundi-note' ),
		);
	}

	/**
	 * The requested question filter, or 0 for all.
	 *
	 * @return int
	 */
	private function question_filter() : int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		return isset( $_GET['ax_question'] ) ? absint( wp_unslash( $_GET['ax_question'] ) ) : 0;
	}

	/**
	 * The requested status filter, or '' for all.
	 *
	 * @return string
	 */
	private function status_filter() : string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$status = isset( $_GET['ax_vote_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_vote_status'] ) ) : '';
		return in_array( $status, array( 'active', 'undone', 'ignored', 'deleted' ), true ) ? $status : '';
	}

	/**
	 * Load one page of the ledger.
	 *
	 * @return void
	 */
	public function prepare_items() : void {
		global $wpdb;
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$votes    = axismundi_note_poll_votes_table();
		$per_page = 50;
		$page     = max( 1, (int) $this->get_pagenum() );
		$question = $this->question_filter();
		$status   = $this->status_filter();

		$where = array( '1=1' );
		$args  = array();
		if ( $question > 0 ) {
			$where[] = 'v.question_id = %d';
			$args[]  = $question;
		}
		if ( '' !== $status ) {
			$where[] = 'v.vote_status = %s';
			$args[]  = $status;
		}
		$clause = implode( ' AND ', $where );

		// Status counts follow the question filter but not the status filter, so the links keep
		// showing what else is there once one of them is selected.
		$count_where = $question > 0 ? 'v.question_id = %d' : '1=1';
		$count_args  = $question > 0 ? array( $question ) : array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin ledger screen over a custom table.
		$rows = (array) $wpdb->get_results( empty( $count_args ) ? "SELECT vote_status AS k, COUNT(*) AS c FROM {$votes} v WHERE {$count_where} GROUP BY vote_status" : $wpdb->prepare( "SELECT vote_status AS k, COUNT(*) AS c FROM {$votes} v WHERE {$count_where} GROUP BY vote_status", $count_args ), ARRAY_A );
		$this->status_counts = array();
		foreach ( $rows as $row ) {
			$this->status_counts[ (string) $row['k'] ] = (int) $row['c'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin ledger screen over a custom table.
		$total = (int) $wpdb->get_var( empty( $args ) ? "SELECT COUNT(*) FROM {$votes} v WHERE {$clause}" : $wpdb->prepare( "SELECT COUNT(*) FROM {$votes} v WHERE {$clause}", $args ) );

		$page_args = array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin ledger screen over a custom table.
		$this->items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT v.* FROM {$votes} v WHERE {$clause} ORDER BY v.created_at DESC, v.id DESC LIMIT %d OFFSET %d", $page_args ), ARRAY_A );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Status links across the top.
	 *
	 * @return array<string,string>
	 */
	protected function get_views() : array {
		$base     = admin_url( 'edit.php?post_type=' . AXISMUNDI_NOTE_POST_TYPE . '&page=ax-note-answers' );
		$question = $this->question_filter();
		if ( $question > 0 ) {
			$base = add_query_arg( 'ax_question', $question, $base );
		}
		$active = $this->status_filter();
		$total  = array_sum( $this->status_counts );
		$views  = array(
			'all' => '<a href="' . esc_url( $base ) . '"' . ( '' === $active ? ' class="current" aria-current="page"' : '' ) . '>'
				. esc_html__( 'All', 'axismundi-note' ) . ' <span class="count">(' . esc_html( number_format_i18n( $total ) ) . ')</span></a>',
		);
		$labels = array(
			'active'  => __( 'Counted', 'axismundi-note' ),
			'undone'  => __( 'Withdrawn', 'axismundi-note' ),
			'ignored' => __( 'Ignored', 'axismundi-note' ),
			'deleted' => __( 'Deleted', 'axismundi-note' ),
		);
		foreach ( $labels as $key => $label ) {
			$count = (int) ( $this->status_counts[ $key ] ?? 0 );
			if ( $count < 1 && $key !== $active ) {
				continue;
			}
			$views[ $key ] = '<a href="' . esc_url( add_query_arg( 'ax_vote_status', $key, $base ) ) . '"' . ( $key === $active ? ' class="current" aria-current="page"' : '' ) . '>'
				. esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
		}
		return $views;
	}

	/**
	 * The question selector above the table.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) : void {
		global $wpdb;
		if ( 'top' !== $which ) {
			return;
		}
		$questions = axismundi_note_questions_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin ledger screen over a custom table.
		$rows    = (array) $wpdb->get_results( "SELECT id, note_post_id FROM {$questions} ORDER BY id DESC LIMIT 200", ARRAY_A );
		$current = $this->question_filter();
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="ax_question">' . esc_html__( 'Filter by Question', 'axismundi-note' ) . '</label>';
		echo '<select name="ax_question" id="ax_question">';
		echo '<option value="0">' . esc_html__( 'All Questions', 'axismundi-note' ) . '</option>';
		foreach ( $rows as $row ) {
			$post_id = (int) $row['note_post_id'];
			$label   = trim( wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 8 ) );
			if ( '' === $label ) {
				/* translators: %d: Note post ID. */
				$label = sprintf( __( 'Question #%d', 'axismundi-note' ), (int) $row['id'] );
			}
			echo '<option value="' . esc_attr( (string) (int) $row['id'] ) . '"' . selected( $current, (int) $row['id'], false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'axismundi-note' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Default cell.
	 *
	 * @param array<string,mixed> $item   Ledger row.
	 * @param string              $column Column key.
	 * @return string
	 */
	public function column_default( $item, $column ) : string {
		if ( 'choice' === $column ) {
			return esc_html( (string) $item['option_name'] );
		}
		if ( 'recorded' === $column ) {
			$created = (string) $item['created_at'];
			$updated = (string) $item['updated_at'];
			$out     = esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created ) );
			if ( $updated !== $created ) {
				/* translators: %s: last update time. */
				$out .= '<br /><span class="ax-note-row__muted">' . esc_html( sprintf( __( 'updated %s', 'axismundi-note' ), mysql2date( get_option( 'date_format' ), $updated ) ) ) . '</span>';
			}
			return $out;
		}
		return '';
	}

	/**
	 * The Question this answer belongs to.
	 *
	 * @param array<string,mixed> $item Ledger row.
	 * @return string
	 */
	public function column_question( $item ) : string {
		global $wpdb;
		$questions = axismundi_note_questions_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin ledger screen over a custom table.
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT note_post_id FROM {$questions} WHERE id = %d", (int) $item['question_id'] ) );
		if ( $post_id < 1 ) {
			/* translators: %d: question row ID. */
			return '<span class="ax-note-row__muted">' . esc_html( sprintf( __( 'Question #%d', 'axismundi-note' ), (int) $item['question_id'] ) ) . '</span>';
		}
		$excerpt = trim( wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 10 ) );
		if ( '' === $excerpt ) {
			$excerpt = __( '(no text)', 'axismundi-note' );
		}
		$edit = get_edit_post_link( $post_id );
		$out  = '' !== (string) $edit
			? '<a href="' . esc_url( (string) $edit ) . '">' . esc_html( $excerpt ) . '</a>'
			: esc_html( $excerpt );
		$envelope = function_exists( 'axismundi_note_get' ) ? axismundi_note_get( $post_id ) : null;
		$uuid     = is_array( $envelope ) ? (string) ( $envelope['local_uuid'] ?? '' ) : '';
		if ( '' !== $uuid && function_exists( 'axismundi_note_object_uri' ) ) {
			$out .= '<br /><a class="ax-note-row__muted" href="' . esc_url( axismundi_note_object_uri( $uuid ) ) . '">' . esc_html__( 'View Object', 'axismundi-note' ) . '</a>';
		}
		return $out;
	}

	/**
	 * The Actor who answered.
	 *
	 * @param array<string,mixed> $item Ledger row.
	 * @return string
	 */
	public function column_voter( $item ) : string {
		$uri = (string) $item['voter_actor_uri'];
		if ( '' === $uri ) {
			return '<span class="ax-note-row__muted">' . esc_html__( 'Unknown', 'axismundi-note' ) . '</span>';
		}
		if ( function_exists( 'axismundi_actors_get_by_uri' ) ) {
			$actor = axismundi_actors_get_by_uri( $uri );
			if ( $actor instanceof Axismundi_Actor ) {
				$name   = $actor->get_display_name();
				$name   = '' !== $name ? $name : $actor->get_preferred_username();
				$handle = function_exists( 'axismundi_actors_mention_handle' ) ? (string) axismundi_actors_mention_handle( $actor ) : '';
				return '<strong>' . esc_html( $name ) . '</strong>'
					. ( '' !== $handle ? '<br /><span class="ax-note-row__muted">' . esc_html( $handle ) . '</span>' : '' );
			}
		}
		// A remote voter whose Actor was never cached is still a real answer; the URI is what the
		// ledger holds and is the honest thing to show.
		return '<span class="ax-note-row__muted">' . esc_html( $uri ) . '</span>';
	}

	/**
	 * Why this row does or does not count.
	 *
	 * @param array<string,mixed> $item Ledger row.
	 * @return string
	 */
	public function column_status( $item ) : string {
		$status = (string) $item['vote_status'];
		$labels = array(
			'active'  => __( 'Counted', 'axismundi-note' ),
			'undone'  => __( 'Withdrawn', 'axismundi-note' ),
			'ignored' => __( 'Ignored', 'axismundi-note' ),
			'deleted' => __( 'Deleted', 'axismundi-note' ),
		);
		$label = $labels[ $status ] ?? $status;
		$class = 'active' === $status ? 'ax-note-row__counted' : 'ax-note-row__muted';
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * The federated Activity this answer came from.
	 *
	 * @param array<string,mixed> $item Ledger row.
	 * @return string
	 */
	public function column_activity( $item ) : string {
		$uri = trim( (string) $item['vote_activity_uri'] );
		if ( '' === $uri ) {
			return '<span class="ax-note-row__muted">' . esc_html__( 'local', 'axismundi-note' ) . '</span>';
		}
		return '<a href="' . esc_url( $uri ) . '"><span class="ax-note-row__muted">' . esc_html( wp_html_excerpt( $uri, 48, '&hellip;' ) ) . '</span></a>';
	}

	/** @return string */
	public function no_items() : string {
		return esc_html__( 'No answers recorded yet.', 'axismundi-note' );
	}
}

/**
 * Register the screen beneath Notes.
 *
 * @return void
 */
function axismundi_note_register_answers_screen() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_NOTE_POST_TYPE,
		__( 'Local question answers', 'axismundi-note' ),
		__( 'Local question answers', 'axismundi-note' ),
		// Answers name other people's Actors, so reading them is a moderation capability rather
		// than an authoring one.
		'edit_others_posts',
		'ax-note-answers',
		'axismundi_note_render_answers_screen'
	);
}
add_action( 'admin_menu', 'axismundi_note_register_answers_screen' );

/**
 * Render the screen.
 *
 * @return void
 */
function axismundi_note_render_answers_screen() : void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to read Question answers.', 'axismundi-note' ) );
	}
	$table = new Axismundi_Note_Answers_Table();
	$table->prepare_items();
	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">' . esc_html__( 'Local question answers', 'axismundi-note' ) . '</h1>';
	echo '<p class="description">' . esc_html__( 'Every answer recorded against a Question this site is authoritative for, including the ones its published tallies leave out. The results of a remote Question are a projection of the cached Object and are not held here.', 'axismundi-note' ) . '</p>';
	$table->views();
	echo '<form method="get">';
	echo '<input type="hidden" name="post_type" value="' . esc_attr( AXISMUNDI_NOTE_POST_TYPE ) . '" />';
	echo '<input type="hidden" name="page" value="ax-note-answers" />';
	$table->display();
	echo '</form>';
	echo '</div>';
}
