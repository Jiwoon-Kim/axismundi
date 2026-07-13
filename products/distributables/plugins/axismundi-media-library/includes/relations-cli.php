<?php
/**
 * Phase 3c relation reindex command.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Used-in relation maintenance commands.
 */
class Axismundi_Media_Relations_CLI {

	/**
	 * Rebuild relation providers for one post or every local post object.
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Reindex one post/attachment/template object.
	 *
	 * [--all]
	 * : Reindex every non-revision post object in batches.
	 *
	 * [--dry-run]
	 * : Collect and deduplicate providers without changing the relation table.
	 *
	 * [--yes]
	 * : Required with a mutating --all run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp axismundi media relations reindex --post=123 --dry-run
	 *     wp axismundi media relations reindex --post=123
	 *     wp axismundi media relations reindex --all --dry-run
	 *     wp axismundi media relations reindex --all --yes
	 *
	 * @param array<int,string>    $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function reindex( $args, $assoc_args ) : void {
		$post_id = isset( $assoc_args['post'] ) ? absint( $assoc_args['post'] ) : 0;
		$all     = isset( $assoc_args['all'] );
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( ( $post_id > 0 && $all ) || ( 0 === $post_id && ! $all ) ) {
			WP_CLI::error( 'Choose exactly one target: --post=<id> or --all.' );
		}
		if ( $all && ! $dry_run && ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::error( 'A mutating --all reindex requires --yes.' );
		}
		if ( $post_id > 0 && ! get_post( $post_id ) ) {
			WP_CLI::error( 'The requested post does not exist.' );
		}

		$ids = $post_id > 0 ? array( $post_id ) : $this->all_subject_ids();
		if ( empty( $ids ) ) {
			WP_CLI::success( 'No relation subjects found.' );
			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( $dry_run ? 'Inspecting relations' : 'Reindexing relations', count( $ids ) );
		$totals   = array( 'subjects' => 0, 'rows' => 0, 'cleared' => 0, 'skipped' => 0, 'errors' => 0 );
		foreach ( $ids as $id ) {
			$report = axismundi_media_relations_reindex_post( (int) $id, $dry_run );
			++$totals['subjects'];
			$totals['rows'] += (int) $report['written'];
			if ( 'cleared' === $report['status'] ) {
				++$totals['cleared'];
			} elseif ( 'skipped' === $report['status'] ) {
				++$totals['skipped'];
			}
			foreach ( $report['errors'] as $provider => $message ) {
				++$totals['errors'];
				WP_CLI::warning( sprintf( 'Post %1$d, provider %2$s: %3$s', (int) $id, $provider, $message ) );
			}
			$progress->tick();
		}
		$progress->finish();

		$verb = $dry_run ? 'would write/clear' : 'wrote/cleared';
		WP_CLI::log(
			sprintf(
				'Subjects: %1$d; rows %2$s: %3$d; cleared subjects: %4$d; skipped: %5$d; provider errors: %6$d.',
				$totals['subjects'],
				$verb,
				$totals['rows'],
				$totals['cleared'],
				$totals['skipped'],
				$totals['errors']
			)
		);
		if ( $totals['errors'] > 0 ) {
			WP_CLI::error( 'Relation reindex completed with provider errors.' );
		}
		WP_CLI::success( $dry_run ? 'Relation dry-run complete; no data changed.' : 'Relation reindex complete.' );
	}

	/**
	 * Fetch all local post-object IDs in bounded batches. Revisions are never
	 * relation subjects; every other registered post type is eligible, including
	 * Attachments and block templates.
	 *
	 * @return int[]
	 */
	private function all_subject_ids() : array {
		$post_types = array_values( array_diff( get_post_types( array(), 'names' ), array( 'revision' ) ) );
		$statuses   = array_values( get_post_stati( array(), 'names' ) );
		$ids        = array();
		$page       = 1;
		do {
			$query = new WP_Query(
				array(
					'post_type'              => $post_types,
					'post_status'            => $statuses,
					'posts_per_page'         => 200,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$ids = array_merge( $ids, array_map( 'intval', $query->posts ) );
			++$page;
		} while ( $page <= (int) $query->max_num_pages );
		return $ids;
	}
}

WP_CLI::add_command( 'axismundi media relations', 'Axismundi_Media_Relations_CLI' );

/**
 * Guarded legacy post_parent migration commands.
 */
class Axismundi_Media_Legacy_Parent_CLI {

	/**
	 * Preview or record immutable parent snapshots. This never detaches media.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be snapshotted without writing.
	 *
	 * [--yes]
	 * : Record snapshots.
	 *
	 * @param array<int,string>    $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function snapshot( $args, $assoc_args ) : void {
		$dry = $this->operation_mode( $assoc_args );
		$r   = axismundi_media_legacy_parent_snapshot_all( $dry );
		WP_CLI::log( sprintf( 'Candidates: %1$d; would snapshot: %2$d; snapshotted: %3$d; existing: %4$d; conflicts: %5$d; errors: %6$d.', $r['candidates'], $r['would_snapshot'], $r['snapshotted'], $r['existing'], $r['conflicts'], $r['errors'] ) );
		$this->finish( $dry, $r['errors'], 'Legacy-parent snapshot' );
	}

	/**
	 * Preview or detach Attachments whose current parent exactly matches a snapshot.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report the detach plan without changing post_parent.
	 *
	 * [--yes]
	 * : Detach after a clean snapshot preflight.
	 *
	 * @param array<int,string>    $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function detach( $args, $assoc_args ) : void {
		$dry = $this->operation_mode( $assoc_args );
		if ( ! axismundi_media_is_independent() ) {
			WP_CLI::error( 'Detach requires Independent media mode.' );
		}
		$preflight = axismundi_media_legacy_parent_detach_all( true );
		if ( ! $dry && ( $preflight['unsnapshotted'] > 0 || $preflight['conflicts'] > 0 || $preflight['errors'] > 0 ) ) {
			WP_CLI::error( 'Detach preflight has unsnapshotted or conflicting relationships. Run snapshot and resolve conflicts first.' );
		}
		$r = $dry ? $preflight : axismundi_media_legacy_parent_detach_all( false );
		WP_CLI::log( sprintf( 'Candidates: %1$d; would detach: %2$d; detached: %3$d; unsnapshotted: %4$d; conflicts: %5$d; errors: %6$d.', $r['candidates'], $r['would_detach'], $r['detached'], $r['unsnapshotted'], $r['conflicts'], $r['errors'] ) );
		$this->finish( $dry, $r['errors'], 'Legacy-parent detach' );
	}

	/**
	 * Preview or restore snapshots. Only currently detached Attachments are changed.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be restored.
	 *
	 * [--yes]
	 * : Restore safe snapshots.
	 *
	 * @param array<int,string>    $args       Positional arguments (unused).
	 * @param array<string,string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function rollback( $args, $assoc_args ) : void {
		$dry = $this->operation_mode( $assoc_args );
		$r   = axismundi_media_legacy_parent_rollback_all( $dry );
		WP_CLI::log( sprintf( 'Snapshots: %1$d; would restore: %2$d; restored: %3$d; already restored: %4$d; conflicts: %5$d; missing: %6$d; errors: %7$d.', $r['snapshots'], $r['would_restore'], $r['restored'], $r['already_restored'], $r['conflicts'], $r['missing'], $r['errors'] ) );
		$this->finish( $dry, $r['errors'], 'Legacy-parent rollback' );
	}

	/**
	 * Require exactly one explicit operation mode.
	 *
	 * @param array<string,string> $assoc_args CLI args.
	 * @return bool True for dry-run.
	 */
	private function operation_mode( array $assoc_args ) : bool {
		$dry = isset( $assoc_args['dry-run'] );
		$yes = isset( $assoc_args['yes'] );
		if ( $dry === $yes ) {
			WP_CLI::error( 'Choose exactly one: --dry-run or --yes.' );
		}
		return $dry;
	}

	/**
	 * Finish one command with a truthful mutation/dry-run message.
	 *
	 * @param bool   $dry    Dry-run.
	 * @param int    $errors Error count.
	 * @param string $label  Operation label.
	 * @return void
	 */
	private function finish( bool $dry, int $errors, string $label ) : void {
		if ( $errors > 0 ) {
			WP_CLI::error( $label . ' completed with errors.' );
		}
		WP_CLI::success( $label . ( $dry ? ' dry-run complete; no data changed.' : ' complete.' ) );
	}
}

WP_CLI::add_command( 'axismundi media relations legacy-parent', 'Axismundi_Media_Legacy_Parent_CLI' );
