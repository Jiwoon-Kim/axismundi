<?php
/**
 * The `ax_event` post type: where an Event is authored.
 *
 * Public and permalinked, unlike `ax_note`. A Note is private because most Notes are not addressed
 * to everyone and the Object route owns visibility; an Event is a thing people are invited to, and
 * a page they cannot link to is not an invitation.
 *
 * `activitypub` support is deliberately not declared. The official plugin federates the post types
 * that ask it to, and an Event that federated as an ordinary Note would arrive at peers stripped of
 * its start time, location and participation — the properties that make it an Event. This plugin
 * projects it as an Event through Object Projections instead, so there is exactly one projector.
 *
 * @package AxismundiEvent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the post type.
 *
 * @return void
 */
function axismundi_event_register_post_type() : void {
	register_post_type(
		AXISMUNDI_EVENT_POST_TYPE,
		array(
			'labels'              => array(
				'name'          => __( 'Events', 'axismundi-event' ),
				'singular_name' => __( 'Event', 'axismundi-event' ),
				'add_new_item'  => __( 'Add Event', 'axismundi-event' ),
				'edit_item'     => __( 'Edit Event', 'axismundi-event' ),
				'menu_name'     => __( 'Events', 'axismundi-event' ),
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'menu_icon'           => 'dashicons-calendar-alt',
			'menu_position'       => 26,
			'rewrite'             => array( 'slug' => 'event', 'with_front' => false ),
			'exclude_from_search' => false,
			// An Event carries a title, a body, an author and a lead image, and nothing here needs
			// comments: replies arrive as Objects in the thread graph, not as `wp_comments` rows.
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'revisions', 'custom-fields' ),
		)
	);
}
add_action( 'init', 'axismundi_event_register_post_type', 8 );
