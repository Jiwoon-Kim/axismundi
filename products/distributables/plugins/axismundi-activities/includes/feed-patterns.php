<?php
/**
 * Starting points for the card a feed repeats.
 *
 * Patterns rather than settings, because these answer a different question than `density` does.
 * `density` is a reader's runtime choice — the same list read two ways, carried in the address, and
 * changing nothing about which entries appear. A pattern is an author's starting composition for
 * one template, inserted once and then edited freely.
 *
 * Deliberately not wired to each other. Choosing the compact pattern must not make `?density=compact`
 * load a different saved card: the first page is rendered from the template while the continuation
 * comes from a REST request with no template at all, and the moment those two could resolve to
 * different cards the feed would change shape as a reader scrolled.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * The card composition each starting point inserts.
 *
 * @return array<string,array{title:string,description:string,content:string}>
 */
function axismundi_act_feed_card_patterns() : array {
	$interactions = '<!-- wp:axismundi/interactions -->'
		. '<!-- wp:axismundi/interaction {"type":"reply"} /-->'
		. '<!-- wp:axismundi/interaction {"type":"like"} /-->'
		. '<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true} /-->'
		. '<!-- wp:axismundi/interaction {"type":"reaction"} /-->'
		. '<!-- /wp:axismundi/interactions -->';

	return array(
		'axismundi/feed-card' => array(
			'title'       => __( 'Feed entry — card', 'axismundi-activities' ),
			'description' => __( 'The full entry: author, body, media, tags, reactions and every control.', 'axismundi-activities' ),
			'content'     => '<!-- wp:axismundi/object-status /-->'
				. '<!-- wp:axismundi/object-card-body /-->'
				. '<!-- wp:axismundi/object-hashtags {"className":"is-style-tags"} /-->'
				. '<!-- wp:axismundi/reaction-bar /-->'
				. $interactions,
		),
		/*
		 * Shorter by leaving blocks out rather than by hiding them. A card whose body is present
		 * but invisible still costs the work of rendering it, and still says the entry has a body
		 * to anything reading the markup rather than looking at the page.
		 */
		'axismundi/feed-compact' => array(
			'title'       => __( 'Feed entry — compact', 'axismundi-activities' ),
			'description' => __( 'One line per entry: author and title, with reply and vote only. No media, summary or read-more.', 'axismundi-activities' ),
			'content'     => '<!-- wp:axismundi/object-status /-->'
				. '<!-- wp:axismundi/object-title /-->'
				. '<!-- wp:axismundi/interactions -->'
				. '<!-- wp:axismundi/interaction {"type":"reply","size":"xs"} /-->'
				. '<!-- wp:axismundi/interaction {"type":"like","size":"xs"} /-->'
				. '<!-- /wp:axismundi/interactions -->',
		),
	);
}

/**
 * Register both starting points, and the category they live in.
 *
 * Scoped to the feed item template through `blockTypes`, so they are offered where they make sense
 * and not in the middle of a page — the blocks inside them read an Object that only exists while
 * the feed is rendering one.
 *
 * @return void
 */
function axismundi_act_register_feed_patterns() : void {
	if ( ! function_exists( 'register_block_pattern' ) || ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}
	register_block_pattern_category(
		'axismundi-feed',
		array( 'label' => __( 'Axismundi feed', 'axismundi-activities' ) )
	);
	foreach ( axismundi_act_feed_card_patterns() as $name => $pattern ) {
		register_block_pattern(
			$name,
			array(
				'title'       => $pattern['title'],
				'description' => $pattern['description'],
				'categories'  => array( 'axismundi-feed' ),
				'blockTypes'  => array( 'axismundi/feed-item-template' ),
				'content'     => $pattern['content'],
			)
		);
	}
}
add_action( 'init', 'axismundi_act_register_feed_patterns', 20 );
