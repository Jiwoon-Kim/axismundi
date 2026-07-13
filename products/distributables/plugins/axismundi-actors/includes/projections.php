<?php
/**
 * Phase 3 in-memory projection registry.
 *
 * Domain plugins own their archive queries and register only navigation metadata
 * and callbacks. Nothing in this registry is persisted.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,array<string,mixed>> */
$GLOBALS['axismundi_actors_projections'] = array();

/** @var int Stable request-local registration sequence. */
$GLOBALS['axismundi_actors_projection_sequence'] = 0;

/**
 * Register one actor projection.
 *
 * @param string              $id Stable projection slug.
 * @param array<string,mixed> $args label, url_callback, visible_callback,
 *                                  count_callback, priority.
 * @return true|WP_Error
 */
function axismundi_actors_register_projection( string $id, array $args ) {
	$key = sanitize_key( $id );
	if ( '' === $key || $key !== $id ) {
		return new WP_Error( 'ax_actors_projection_id', __( 'Projection IDs must be non-empty lowercase slugs.', 'axismundi-actors' ) );
	}
	$label        = isset( $args['label'] ) ? trim( (string) $args['label'] ) : '';
	$url_callback = $args['url_callback'] ?? null;
	if ( '' === $label || ! is_callable( $url_callback ) ) {
		return new WP_Error( 'ax_actors_projection_args', __( 'A projection requires a label and callable URL callback.', 'axismundi-actors' ) );
	}
	foreach ( array( 'visible_callback', 'count_callback' ) as $callback_key ) {
		if ( isset( $args[ $callback_key ] ) && ! is_callable( $args[ $callback_key ] ) ) {
			return new WP_Error( 'ax_actors_projection_callback', __( 'Optional projection callbacks must be callable.', 'axismundi-actors' ) );
		}
	}

	$registry = &$GLOBALS['axismundi_actors_projections'];
	if ( isset( $registry[ $key ] ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: projection ID. */
				esc_html__( 'Projection "%s" is already registered; the later definition replaces it.', 'axismundi-actors' ),
				esc_html( $key )
			),
			'0.0.5'
		);
	}

	++$GLOBALS['axismundi_actors_projection_sequence'];
	$registry[ $key ] = array(
		'id'               => $key,
		'label'            => $label,
		'url_callback'     => $url_callback,
		'visible_callback' => $args['visible_callback'] ?? null,
		'count_callback'   => $args['count_callback'] ?? null,
		'priority'         => isset( $args['priority'] ) ? (int) $args['priority'] : 10,
		'order'            => (int) $GLOBALS['axismundi_actors_projection_sequence'],
	);
	return true;
}

/**
 * Rebuild the request-local registry in deterministic order.
 *
 * Actors ships **no built-in projection** — the actor profile's primary surface is
 * an activity feed owned by Axismundi Activities, and Articles / Notes / Media are
 * registered by their own domain plugins (docs/PROJECTIONS.md §4). With no plugin
 * registered, an actor renders header-only.
 *
 * @return void
 */
function axismundi_actors_load_projections() : void {
	$GLOBALS['axismundi_actors_projections']       = array();
	$GLOBALS['axismundi_actors_projection_sequence'] = 0;
	do_action( 'axismundi_actors_register_projections' );
}
add_action( 'init', 'axismundi_actors_load_projections', 30 );

/**
 * Resolve visible navigation entries for one actor and viewer.
 *
 * @param Axismundi_Actor $actor Actor.
 * @param int|null        $viewer_id Viewer; defaults to current user.
 * @return array<int,array{id:string,label:string,url:string,count:int|null,priority:int}>
 */
function axismundi_actors_get_projections( Axismundi_Actor $actor, ?int $viewer_id = null ) : array {
	$viewer_id = null === $viewer_id ? get_current_user_id() : $viewer_id;
	if ( ! axismundi_actors_can_view( $actor, $viewer_id ) ) {
		return array();
	}

	$definitions = array_values( $GLOBALS['axismundi_actors_projections'] ?? array() );
	usort(
		$definitions,
		static function ( array $a, array $b ) : int {
			return ( (int) $a['priority'] <=> (int) $b['priority'] ) ?: ( (int) $a['order'] <=> (int) $b['order'] );
		}
	);

	$resolved = array();
	foreach ( $definitions as $definition ) {
		try {
			if ( is_callable( $definition['visible_callback'] ) && ! (bool) call_user_func( $definition['visible_callback'], $actor, $viewer_id ) ) {
				continue;
			}
			$url = (string) call_user_func( $definition['url_callback'], $actor );
			if ( '' === trim( $url ) ) {
				continue;
			}
			$count = null;
			if ( is_callable( $definition['count_callback'] ) ) {
				$value = call_user_func( $definition['count_callback'], $actor );
				$count = null === $value ? null : max( 0, (int) $value );
			}
			$resolved[] = array(
				'id'       => (string) $definition['id'],
				'label'    => (string) $definition['label'],
				'url'      => $url,
				'count'    => $count,
				'priority' => (int) $definition['priority'],
			);
		} catch ( Throwable $error ) {
			/**
			 * Fires when a third-party projection callback cannot be resolved.
			 *
			 * @param Throwable       $error Callback error.
			 * @param array           $definition Projection definition.
			 * @param Axismundi_Actor $actor Actor being rendered.
			 */
			do_action( 'axismundi_actors_projection_error', $error, $definition, $actor );
		}
	}
	return $resolved;
}
