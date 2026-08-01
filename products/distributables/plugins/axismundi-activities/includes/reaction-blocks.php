<?php
/**
 * The two blocks a reader touches: the picker button, and the row of chips.
 *
 * Split on purpose. The button is an *action* and belongs among Reply, Like, and Repost
 * inside `interactions`; the chips are *state* and belong above them, where their changing
 * height cannot push the action row away from the reply controls below. Putting both in one
 * block forced a layout that had to be one or the other.
 *
 * Being two blocks means they have two contexts, so the summary they both describe lives
 * in shared Interactivity state keyed by object URI. A pick made in the button updates the
 * bar underneath it because both read the same entry, not because either knows about the
 * other.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where the picker gets the Unicode set, and how it is divided.
 *
 * The bundled `emoji-test.txt` extract rather than the REST route: the route pages at 100
 * and the picker wants the whole list, so it would take forty round trips to fill one
 * panel. The file is static, versioned by its own name, and public, which makes it exactly
 * the thing an HTTP cache is for — the REST route stays useful for consumers that want to
 * search or page.
 *
 * Groups come from the same file through the catalogue helper, so the strip cannot list a
 * heading the data does not contain.
 *
 * @return array{index_url:string,groups:array<string,string>}
 */
function axismundi_act_unicode_picker_source() : array {
	$base = dirname( __DIR__ ) . '/axismundi-activities.php';
	$file = dirname( __DIR__ ) . '/assets/unicode-rgi-17.0.json';
	$root = dirname( __DIR__ ) . '/assets/unicode-rgi-17.0';
	$urls = array();
	foreach ( function_exists( 'axismundi_act_unicode_emoji_groups' ) ? axismundi_act_unicode_emoji_groups() : array() as $group ) {
		$slug = strtolower( str_replace( '&', 'and', (string) $group ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $slug ), '-' );
		$file = $root . '/' . $slug . '.json';
		if ( is_readable( $file ) ) {
			$urls[ (string) $group ] = plugins_url( 'assets/unicode-rgi-17.0/' . $slug . '.json', $base );
		}
	}
	return array(
		'index_url' => is_readable( dirname( __DIR__ ) . '/assets/unicode-rgi-17.0.json' ) ? plugins_url( 'assets/unicode-rgi-17.0.json', $base ) : '',
		'groups'    => $urls,
	);
}

/** Shared script module and stylesheet handles, registered once for both blocks. */
function axismundi_act_register_reaction_assets() : void {
	$base = dirname( __DIR__ ) . '/axismundi-activities.php';
	$js   = dirname( __DIR__ ) . '/assets/reactions.js';
	$css  = dirname( __DIR__ ) . '/assets/reactions.css';
	if ( function_exists( 'wp_register_script_module' ) && is_readable( $js ) ) {
		wp_register_script_module(
			'axismundi-activities-reactions',
			plugins_url( 'assets/reactions.js', $base ),
			array( '@wordpress/interactivity' ),
			(string) filemtime( $js )
		);
	}
	if ( is_readable( $css ) ) {
		wp_register_style( 'axismundi-activities-reactions', plugins_url( 'assets/reactions.css', $base ), array(), (string) filemtime( $css ) );
	}
}
add_action( 'init', 'axismundi_act_register_reaction_assets', 5 );

/**
 * Seed the state both blocks read, once per request.
 *
 * The summary for an object is written here the first time either block renders it, so a
 * page carrying a button and a bar for the same object states it once. Endpoints and the
 * nonce are page-wide rather than per block for the same reason: repeating them in every
 * card's context on a feed is a lot of identical bytes.
 *
 * @param string $object_uri Object being described.
 * @param array<string,mixed>|null $summary Already-computed summary, if the caller has one.
 * @return array<string,mixed> The summary now in state.
 */
function axismundi_act_seed_reaction_state( string $object_uri, ?array $summary = null ) : array {
	static $seeded = array();
	static $shared = false;

	$actor          = axismundi_act_current_reaction_actor();
	$unicode_picker = axismundi_act_unicode_picker_source();
	if ( ! $shared ) {
		wp_interactivity_state(
			'axismundi/reactions',
			array(
				'summaries'         => array(),
				'catalogue'         => array(),
				'customSearch'      => array(),
				'catalogueLoaded'   => false,
				'openFor'           => '',
				'pendingFor'        => '',
				'searchTimer'       => 0,
				'error'             => '',
				'endpoint'          => rest_url( 'axismundi/v1/reactions' ),
				// `federated=false` asks for the whole picker-visible set, including emoji
				// this site withholds from publication. Those are usable at home, and the
				// send path is what declines to let one travel -- so the picker shows them
				// and marks them, rather than pretending they do not exist.
				'catalogueEndpoint' => rest_url( 'axismundi/v1/emoji/local' ) . '?federated=false&per_page=' . AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE,
				/*
				 * So the client can compute the reaction key an emoji of ours will get before
				 * the server answers. Without it an optimistic chip has to invent a
				 * placeholder key and swap it a moment later, which makes the new chip flicker
				 * and lose its keyed identity in `data-wp-each`.
				 */
				'localAuthority'    => function_exists( 'axismundi_emoji_local_authority' ) ? axismundi_emoji_local_authority() : '',
				'unicodeIndexSource' => $unicode_picker['index_url'],
				'unicodeGroupSources' => (object) $unicode_picker['groups'],
				'unicodeGroups'     => array_keys( $unicode_picker['groups'] ),
				'unicode'           => array(), // Search index, loaded only after the reader searches.
				'unicodeByGroup'    => (object) array(),
				'unicodeIndexLoaded' => false,
				'unicodeLoadedGroups' => array(),
				// Which Unicode groups have had their grids built. The whole set is nearly
				// four thousand buttons, and WordPress's emoji fallback replaces every glyph
				// the browser cannot draw with an image request -- so the Flags group alone
				// would fetch hundreds of files the moment the picker opened. Groups fill in
				// as they come near the viewport instead.
				'expandedSections'  => array( 'recent' ),
				'activeSection'     => 'recent',
				'search'            => '',
				'isSearching'       => false,
				'recent'            => array(),
				'canReact'          => $actor instanceof Axismundi_Actor,
				'nonce'             => $actor instanceof Axismundi_Actor ? wp_create_nonce( 'wp_rest' ) : '',
				'i18n'              => array(
					'addSame'          => __( 'Add this reaction', 'axismundi-activities' ),
					'removeReaction'   => __( 'Remove your reaction', 'axismundi-activities' ),
					'catalogueError'   => __( 'The emoji list could not be loaded.', 'axismundi-activities' ),
					'mutationError'    => __( 'The reaction could not be saved.', 'axismundi-activities' ),
					'localOnlyBlocked' => __( 'This emoji is not published beyond this site.', 'axismundi-activities' ),
					'uncategorized'    => __( 'Other', 'axismundi-activities' ),
				),
			)
		);
		$shared = true;
	}

	if ( ! isset( $seeded[ $object_uri ] ) ) {
		$seeded[ $object_uri ] = is_array( $summary ) ? $summary : axismundi_act_object_reaction_summary( $object_uri, $actor );
		$state                 = wp_interactivity_state( 'axismundi/reactions' );
		$summaries             = (array) ( $state['summaries'] ?? array() );
		$summaries[ $object_uri ] = $seeded[ $object_uri ];
		wp_interactivity_state( 'axismundi/reactions', array( 'summaries' => $summaries ) );
	}
	return $seeded[ $object_uri ];
}

/**
 * Whether this reader could add the reaction a chip already carries.
 *
 * Joining somebody's reaction means sending it, and sending a custom one means declaring
 * the emoji — which this site can only do for its own. A chip carrying `:misskey@hoto.moe:`
 * is therefore readable but not joinable, and saying so up front is better than a button
 * that looks live and returns an error. Without this the shortcode would be posted anyway
 * and, if a same-named local emoji existed, would quietly add a reaction under a
 * *different* key than the chip that was clicked.
 *
 * @param array<string,mixed> $chip       One chip from the summary.
 * @param bool                $is_local   Whether the object stays on this site.
 * @return bool
 */
function axismundi_act_reaction_chip_joinable( array $chip, bool $is_local ) : bool {
	if ( 'custom' !== (string) $chip['kind'] ) {
		return true;
	}
	$declaration = axismundi_act_local_emoji_declaration( (string) $chip['label'] );
	if ( null === $declaration ) {
		return false;
	}
	return $declaration['federates'] || $is_local;
}

/** Decorate a summary's chips with what this reader may do to them. */
function axismundi_act_reaction_chips_for_view( array $summary, string $object_uri ) : array {
	$is_local = axismundi_act_object_is_local( $object_uri );
	return array_map(
		static function ( array $chip ) use ( $is_local ) : array {
			$chip['joinable'] = axismundi_act_reaction_chip_joinable( $chip, $is_local );
			// `alt` reproduces the shortcode verbatim so a screen reader, a copy-paste, and a
			// picture-less fallback all carry the same string the sender wrote.
			$chip['imageUrl'] = is_array( $chip['image'] ) ? (string) $chip['image']['url'] : '';
			/*
			 * A boolean, not the URL. `hidden` is a boolean attribute, so binding a string to
			 * it hides on any value including the empty one — which hid the label of every
			 * Unicode chip, the exact case that has no image and needs its text.
			 */
			$chip['hasImage'] = '' !== $chip['imageUrl'];
			/*
			 * A Unicode chip draws a character where a custom one draws a picture, and the two
			 * have to be the same size or the row reads as broken. Marked here rather than
			 * inferred from a missing URL, because a custom emoji we may not show also has no
			 * URL — and that one really is text, its shortcode, and should be set as text.
			 */
			$chip['isGlyph'] = 'unicode' === (string) $chip['kind'];
			return $chip;
		},
		(array) $summary['chips']
	);
}

/** Resolve the object this block instance describes. */
function axismundi_act_reaction_block_object_uri( array $attributes, WP_Block $block ) : string {
	return function_exists( 'axismundi_act_like_block_object_uri' ) ? axismundi_act_like_block_object_uri( $attributes, $block ) : '';
}

/**
 * The chip row.
 *
 * Renders nothing at all when there are no reactions and the reader cannot make one: an
 * empty bar under every post is furniture, not information. A reader who *can* react still
 * gets the empty element, because their own click has to have somewhere to land.
 */
function axismundi_act_render_reaction_bar( array $attributes, string $content, WP_Block $block ) : string {
	$object_uri = axismundi_act_reaction_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return '';
	}
	$summary = axismundi_act_seed_reaction_state( $object_uri );
	$chips   = axismundi_act_reaction_chips_for_view( $summary, $object_uri );
	/*
	 * An empty bar under every post is furniture, so a reader who cannot react gets nothing.
	 * A reader who *can* gets the empty element, because their first reaction has to have
	 * somewhere to land: without it the pick succeeds on the server and appears nowhere
	 * until the page is reloaded, which reads as the click having failed.
	 */
	if ( array() === $chips && ! axismundi_act_current_reaction_actor() instanceof Axismundi_Actor ) {
		return '';
	}
	axismundi_act_no_cache_like_state();
	wp_enqueue_style( 'axismundi-activities-reactions' );
	$context = array( 'objectUri' => $object_uri, 'chips' => $chips );

	ob_start();
	?>
	<div
		<?php echo get_block_wrapper_attributes( array( 'class' => 'axismundi-reaction-bar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-interactive="axismundi/reactions"
		<?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-watch="callbacks.syncFromStore"
	>
		<?php
		/*
		 * A chip is a `core/button` in the theme's own terms, so it inherits the M3 surface
		 * from `styles.elements.button` and the variant colour from the theme's style
		 * variations. Nothing here restates a colour: borrowing the classes and then
		 * repainting them would put a second, drifting copy of the palette in a plugin.
		 *
		 * A reaction the reader has sent is the theme's `tonal` variant — filled, and the
		 * strongest thing in the row. One they have not is left unvariated and given the
		 * outline treatment by this plugin's stylesheet, for a reason worth stating: the
		 * outline variant lives in `theme.json`'s `core/button` block styles, and WordPress
		 * only emits those on pages that actually contain a `core/button`. Measured on an
		 * Actor profile, `is-style-outline` produced a *filled primary* button — louder than
		 * the selected state it was meant to recede from. Tonal survives only because the
		 * theme also ships it in an always-loaded stylesheet.
		 */
		?>
		<template data-wp-each--item="context.chips" data-wp-each-key="context.item.key">
			<div
				class="wp-block-button axismundi-reaction-bar__item"
				data-wp-class--is-style-tonal="context.item.mine"
			>
				<button
					type="button"
					class="wp-block-button__link wp-element-button axismundi-reaction-bar__chip"
					data-wp-on--click="actions.toggleChip"
					data-wp-bind--disabled="state.isChipDisabled"
					data-wp-bind--aria-pressed="context.item.mine"
					data-wp-bind--title="state.chipLabel"
					data-wp-bind--aria-label="state.chipLabel"
				>
					<img class="axismundi-reaction-bar__image" data-wp-bind--src="context.item.imageUrl" data-wp-bind--alt="context.item.label" data-wp-bind--hidden="!context.item.hasImage" draggable="false" decoding="async" alt="">
					<span class="axismundi-reaction-bar__shortcode" hidden data-wp-class--is-glyph="context.item.isGlyph" data-wp-text="context.item.label" data-wp-bind--hidden="context.item.hasImage"></span>
					<span class="axismundi-reaction-bar__count" data-wp-text="context.item.count"></span>
				</button>
			</div>
		</template>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * The picker button.
 *
 * Sits with the other actions. Owns the popover and nothing else — it does not render
 * chips, so a template may place it without a bar, or a bar without it.
 */
function axismundi_act_render_reaction_button( array $attributes, string $content, WP_Block $block ) : string {
	$object_uri = axismundi_act_reaction_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return '';
	}
	$actor = axismundi_act_current_reaction_actor();
	/*
	 * A visitor who cannot react still sees the control, disabled and saying why. Removing
	 * it instead was a mistake: Reply and Like both stay put and explain themselves, so the
	 * one gap in the row reads as a broken feature rather than as a closed door, and an
	 * action row that changes length depending on who is looking is harder to scan.
	 */
	$can_react = $actor instanceof Axismundi_Actor;
	axismundi_act_seed_reaction_state( $object_uri );
	axismundi_act_no_cache_like_state();
	wp_enqueue_style( 'axismundi-activities-reactions' );
	// Same three-way wording the Like button uses, so the reason is stated in the same
	// terms wherever a reader meets it.
	$label = $can_react
		? __( 'Add reaction', 'axismundi-activities' )
		: ( is_user_logged_in()
			? __( 'Activate a public Actor profile to react.', 'axismundi-activities' )
			: __( 'Log in to react.', 'axismundi-activities' ) );

	/*
	 * Unique to this control, not to the Object it points at.
	 *
	 * A feed can show one Object twice — an original and a boost of it — and both cards carry one
	 * `objectUri` between them. Keyed on that, opening either picker opened both, because the
	 * condition was true in both places at once. The identity that matters is the control the
	 * reader clicked.
	 */
	$picker_id = wp_unique_id( 'ax-rx-' );

	ob_start();
	?>
	<div
		<?php echo get_block_wrapper_attributes( array( 'class' => 'axismundi-reaction-button' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-interactive="axismundi/reactions"
		<?php echo wp_interactivity_data_wp_context( array( 'objectUri' => $object_uri, 'pickerId' => $picker_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		data-wp-watch="callbacks.pickerLifecycle"
	>
		<button
			type="button"
			class="axismundi-reaction-button__trigger"
			data-wp-on--click="actions.togglePicker"
			data-wp-bind--aria-expanded="state.isOpen"
			aria-haspopup="dialog"
			aria-label="<?php echo esc_attr( $label ); ?>"
			title="<?php echo esc_attr( $label ); ?>"
			<?php disabled( ! $can_react ); ?>
		>
			<span class="material-symbols-outlined" aria-hidden="true">add_reaction</span>
		</button>
		<?php if ( $can_react ) : ?>
		<?php
		/*
		 * One scrolling page, not a set of panels.
		 *
		 * Every emoji picker a reader already knows works this way -- the Windows panel,
		 * Mastodon, Misskey -- and they are right: switching panels hides the thing you were
		 * about to compare against, and a reader browsing for a reaction is comparing. So the
		 * strip along the top is a *jump*, not a tab. It moves the scroll position and
		 * reflects it back; headings themselves control whether their tiles mount, so a
		 * reader who never opens a category never pays to render its grid.
		 *
		 * That also settles the roles. These are not tabs and must not claim to be: there is
		 * no panel each one owns, and a screen reader told otherwise would look for one.
		 * A toolbar of buttons with `aria-current` says what is actually true.
		 *
		 * A dialog rather than a menu, for the same care: `role="menu"` promises an up/down
		 * command model, and this holds a search field, a strip, and a grid.
		 */
		/*
		 * Unique to this control, not to the Object it points at.
		 *
		 * A feed can show one Object twice — an original and a boost of it — and an id derived
		 * from the Object put the same `id` on two elements in one document, which is invalid
		 * and leaves anything pointing at it aiming for whichever came first. It is also what
		 * made opening either picker open both.
		 */
		$uid      = $picker_id;
		$unicode  = axismundi_act_unicode_picker_source();
		?>
		<div class="axismundi-reaction-button__picker" role="dialog" aria-label="<?php echo esc_attr( $label ); ?>" hidden data-wp-bind--hidden="state.isPickerHidden" data-wp-class--is-open="state.isOpen">
			<div class="axismundi-reaction-picker__search">
				<?php // Placed by the field's own `grid-template-columns: auto 1fr`, so it needs no class of its own. ?>
				<span class="material-symbols-outlined" aria-hidden="true">search</span>
				<input
					type="search"
					class="axismundi-reaction-picker__input"
					data-wp-on--input="actions.search"
					placeholder="<?php esc_attr_e( 'Search emoji', 'axismundi-activities' ); ?>"
					aria-label="<?php esc_attr_e( 'Search emoji', 'axismundi-activities' ); ?>"
				>
				<?php
				/*
				 * Linear, indeterminate, and along the bottom edge of the field it belongs to
				 * — the way a text field shows its own activity, rather than as a separate
				 * bar that appears between two components and shifts everything below it.
				 * A spinner over the grid would collapse the results on every keystroke and
				 * bounce the popover's height; this leaves them where they are while they are
				 * replaced. No `aria-valuenow`: there is no proportion to report.
				 */
				?>
				<div class="axismundi-reaction-picker__loading" data-wp-bind--hidden="!state.isSearching">
					<div class="ax-progress-linear is-indeterminate" role="progressbar" aria-label="<?php esc_attr_e( 'Searching emoji', 'axismundi-activities' ); ?>"></div>
				</div>
			</div>

			<div class="axismundi-reaction-picker__strip" role="toolbar" aria-label="<?php esc_attr_e( 'Jump to emoji category', 'axismundi-activities' ); ?>" data-wp-bind--hidden="state.isFiltering">
				<button type="button" class="axismundi-reaction-picker__jump" data-jump="recent" data-wp-on--click="actions.jumpTo" data-wp-bind--aria-current="state.isSectionActive" data-wp-class--is-active="state.isSectionActive" title="<?php esc_attr_e( 'Recent', 'axismundi-activities' ); ?>">
					<span class="material-symbols-outlined" aria-hidden="true">history</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Recent', 'axismundi-activities' ); ?></span>
				</button>
				<button type="button" class="axismundi-reaction-picker__jump" data-jump="custom" data-wp-on--click="actions.jumpTo" data-wp-bind--aria-current="state.isSectionActive" data-wp-class--is-active="state.isSectionActive" title="<?php esc_attr_e( 'Custom emoji', 'axismundi-activities' ); ?>">
					<span class="material-symbols-outlined" aria-hidden="true">mood</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Custom emoji', 'axismundi-activities' ); ?></span>
				</button>
				<?php
				// Icons follow the `emoji-test.txt` groups in file order, so the strip and the
				// headings below it cannot disagree about what exists or in what sequence.
				$group_icons = array(
					'Smileys & Emotion' => 'sentiment_satisfied',
					'People & Body'     => 'emoji_people',
					'Animals & Nature'  => 'pets',
					'Food & Drink'      => 'restaurant',
					'Travel & Places'   => 'travel',
					'Activities'        => 'sports_soccer',
					'Objects'           => 'lightbulb',
					'Symbols'           => 'tag',
					'Flags'             => 'flag',
				);
				foreach ( array_keys( $unicode['groups'] ) as $group ) :
					?>
					<button type="button" class="axismundi-reaction-picker__jump" data-jump="<?php echo esc_attr( 'uni:' . $group ); ?>" data-wp-on--click="actions.jumpTo" data-wp-bind--aria-current="state.isSectionActive" data-wp-class--is-active="state.isSectionActive" title="<?php echo esc_attr( $group ); ?>">
						<span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( $group_icons[ $group ] ?? 'emoji_symbols' ); ?></span>
						<span class="screen-reader-text"><?php echo esc_html( $group ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="axismundi-reaction-picker__scroll" id="<?php echo esc_attr( $uid . '-scroll' ); ?>" data-wp-on--scroll="actions.trackScroll">
				<?php // Searching replaces the whole page with one list, because a reader who typed a word wants the matches, not the shelf they came from. ?>
				<section class="axismundi-reaction-picker__section" data-wp-bind--hidden="!state.isFiltering">
					<h3 class="axismundi-reaction-picker__category"><?php esc_html_e( 'Results', 'axismundi-activities' ); ?></h3>
					<div class="axismundi-reaction-picker__grid">
						<template data-wp-each--item="state.searchResults" data-wp-each-key="context.item.key">
							<button type="button" class="axismundi-reaction-button__emoji" data-wp-on--click="actions.pick" data-wp-bind--disabled="state.isEmojiBlocked" data-wp-bind--title="state.emojiLabel" data-wp-bind--aria-label="state.emojiLabel">
								<img data-wp-bind--src="context.item.url" data-wp-bind--hidden="!context.item.url" draggable="false" decoding="async" alt="" data-wp-bind--alt="context.item.shortcode">
								<span class="axismundi-reaction-button__glyph" data-wp-text="context.item.glyph" data-wp-bind--hidden="context.item.url"></span>
							</button>
						</template>
					</div>
					<p class="axismundi-reaction-picker__empty" data-wp-bind--hidden="!state.isSearchEmpty"><?php esc_html_e( 'No emoji found.', 'axismundi-activities' ); ?></p>
				</section>

				<section class="axismundi-reaction-picker__section" data-section="recent" data-wp-bind--hidden="state.isFiltering">
					<?php
					/*
					 * Recent and Custom do not collapse. Collapsing exists to keep four
					 * thousand Unicode tiles out of the DOM until they are wanted; these two
					 * are small, they are the reason the reader opened the picker, and a
					 * control that only ever hides what you came for is a control worth not
					 * having.
					 */
					?>
					<h3 class="axismundi-reaction-picker__category"><?php esc_html_e( 'Recent', 'axismundi-activities' ); ?></h3>
					<div class="axismundi-reaction-picker__grid">
						<template data-wp-each--item="state.recentItems" data-wp-each-key="context.item.key">
							<button type="button" class="axismundi-reaction-button__emoji" data-wp-on--click="actions.pick" data-wp-bind--disabled="state.isEmojiBlocked" data-wp-bind--title="state.emojiLabel" data-wp-bind--aria-label="state.emojiLabel">
								<img data-wp-bind--src="context.item.url" data-wp-bind--hidden="!context.item.url" draggable="false" decoding="async" alt="" data-wp-bind--alt="context.item.shortcode">
								<span class="axismundi-reaction-button__glyph" data-wp-text="context.item.glyph" data-wp-bind--hidden="context.item.url"></span>
							</button>
						</template>
					</div>
					<p class="axismundi-reaction-picker__empty" data-wp-bind--hidden="state.hasRecent"><?php esc_html_e( 'Emoji you use will appear here.', 'axismundi-activities' ); ?></p>
				</section>

				<section class="axismundi-reaction-picker__section" data-section="custom" data-wp-bind--hidden="state.isFiltering">
					<h3 class="axismundi-reaction-picker__category"><?php esc_html_e( 'Custom emoji', 'axismundi-activities' ); ?></h3>
					<template data-wp-each--group="state.customGroups" data-wp-each-key="context.group.category">
						<div class="axismundi-reaction-picker__subgroup">
							<h4 class="axismundi-reaction-picker__subcategory"><button type="button" class="axismundi-reaction-picker__subcategory-toggle" data-wp-bind--data-toggle="context.group.id" data-wp-on--click="actions.toggleSection" data-wp-bind--aria-expanded="state.isSectionExpanded"><span data-wp-text="context.group.label"></span><span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button></h4>
							<div class="axismundi-reaction-picker__grid">
								<template data-wp-each--item="context.group.items" data-wp-each-key="context.item.key">
									<button type="button" class="axismundi-reaction-button__emoji" data-wp-on--click="actions.pick" data-wp-bind--disabled="state.isEmojiBlocked" data-wp-bind--title="state.emojiLabel" data-wp-bind--aria-label="state.emojiLabel">
										<img data-wp-bind--src="context.item.url" draggable="false" decoding="async" alt="" data-wp-bind--alt="context.item.shortcode">
									</button>
								</template>
							</div>
						</div>
					</template>
					<p class="axismundi-reaction-picker__empty" data-wp-bind--hidden="!state.isCatalogueEmpty"><?php esc_html_e( 'This site has no custom emoji yet.', 'axismundi-activities' ); ?></p>
				</section>

				<template data-wp-each--group="state.unicodeSections" data-wp-each-key="context.group.id">
					<section class="axismundi-reaction-picker__section" data-wp-bind--data-section="context.group.id" data-wp-bind--hidden="state.isFiltering">
						<h3 class="axismundi-reaction-picker__category"><button type="button" class="axismundi-reaction-picker__category-toggle" data-wp-bind--data-toggle="context.group.id" data-wp-on--click="actions.toggleSection" data-wp-bind--aria-expanded="state.isSectionExpanded"><span data-wp-text="context.group.label"></span><span class="material-symbols-outlined" aria-hidden="true">expand_more</span></button></h3>
						<div class="axismundi-reaction-picker__grid">
							<template data-wp-each--item="context.group.items" data-wp-each-key="context.item.key">
								<button type="button" class="axismundi-reaction-button__emoji" data-wp-on--click="actions.pick" data-wp-bind--disabled="state.isEmojiBlocked" data-wp-bind--title="state.emojiLabel" data-wp-bind--aria-label="state.emojiLabel">
									<span class="axismundi-reaction-button__glyph" data-wp-text="context.item.glyph"></span>
								</button>
							</template>
						</div>
					</section>
				</template>
			</div>
		</div>
		<?php endif; ?>
		<span class="axismundi-reaction-button__status" data-wp-text="state.error" aria-live="polite"></span>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Register both blocks. */
function axismundi_act_register_reaction_blocks() : void {
	foreach ( array( 'reaction-bar' => 'axismundi_act_render_reaction_bar', 'reaction-button' => 'axismundi_act_render_reaction_button' ) as $dir => $callback ) {
		$path = dirname( __DIR__ ) . '/blocks/' . $dir;
		if ( is_readable( $path . '/block.json' ) ) {
			register_block_type( $path, array( 'render_callback' => $callback ) );
		}
	}
}
add_action( 'init', 'axismundi_act_register_reaction_blocks' );

/** Both blocks share one script module rather than shipping a copy each. */
function axismundi_act_enqueue_reaction_module( string $block_content, array $block ) : string {
	if ( in_array( $block['blockName'] ?? '', array( 'axismundi/reaction-bar', 'axismundi/reaction-button' ), true ) && '' !== trim( $block_content ) && function_exists( 'wp_enqueue_script_module' ) ) {
		wp_enqueue_script_module( 'axismundi-activities-reactions' );
	}
	return $block_content;
}
add_filter( 'render_block', 'axismundi_act_enqueue_reaction_module', 10, 2 );
