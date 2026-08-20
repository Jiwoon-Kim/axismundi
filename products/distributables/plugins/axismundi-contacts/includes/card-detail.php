<?php
/**
 * Looking at a contact, which is not the same as editing one.
 *
 * The list used to open the edit form. Most of the time somebody clicking a name wants to read it --
 * what the number is, what they wrote down about this person -- and an edit form answers that
 * question while putting every value one keystroke from being changed, with no record that anything
 * was looked at rather than touched.
 *
 * So a click opens this, and editing is somewhere else and says so. The media library draws the same
 * line for the same reason: `upload.php?item=` shows an attachment, `post.php?action=edit` changes
 * one.
 *
 * For the Card an Actor publishes about itself this screen carries one more thing: what a stranger
 * receives. That is a different document from the one above it -- the part its owner selected -- and
 * the only way to be sure of it is to be shown it. Selecting is done on the edit screen; this shows
 * the result and changes nothing.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * One value of a card, as a line somebody reads.
 *
 * @param array<string,mixed> $entry Entry.
 * @param string              $key   Which value carries the text.
 * @return string
 */
function axismundi_contacts_entry_text( array $entry, string $key ) : string {
	if ( 'addresses' === $key ) {
		$parts = array();
		foreach ( (array) ( $entry['components'] ?? array() ) as $component ) {
			if ( is_array( $component ) && '' !== trim( (string) ( $component['value'] ?? '' ) ) ) {
				$parts[] = (string) $component['value'];
			}
		}
		$separator = isset( $entry['defaultSeparator'] ) && is_string( $entry['defaultSeparator'] ) ? $entry['defaultSeparator'] : ' ';
		return '' !== trim( (string) ( $entry['full'] ?? '' ) ) ? (string) $entry['full'] : implode( $separator, $parts );
	}
	foreach ( array( 'address', 'number', 'uri', 'name', 'note', 'value', 'user' ) as $candidate ) {
		if ( isset( $entry[ $candidate ] ) && is_string( $entry[ $candidate ] ) ) {
			return (string) $entry[ $candidate ];
		}
	}
	return '';
}

/**
 * The properties this screen reads out, and what to call them.
 *
 * @return array<string,string>
 */
function axismundi_contacts_detail_sections() : array {
	return array(
		'emails'              => __( 'Email', 'axismundi-contacts' ),
		'phones'              => __( 'Phone', 'axismundi-contacts' ),
		'addresses'           => __( 'Address', 'axismundi-contacts' ),
		'onlineServices'      => __( 'Online accounts', 'axismundi-contacts' ),
		'links'               => __( 'Links', 'axismundi-contacts' ),
		'organizations'       => __( 'Organisations', 'axismundi-contacts' ),
		'titles'              => __( 'Titles', 'axismundi-contacts' ),
		'calendars'           => __( 'Calendars', 'axismundi-contacts' ),
		'schedulingAddresses' => __( 'Scheduling', 'axismundi-contacts' ),
		'anniversaries'       => __( 'Anniversaries', 'axismundi-contacts' ),
		'notes'               => __( 'Notes', 'axismundi-contacts' ),
	);
}

/**
 * One entry's line, with what it is called and whether a stranger sees it.
 *
 * @param array<string,mixed> $entry     Entry.
 * @param string              $property  Property it belongs to.
 * @param bool                $published Whether it is published.
 * @return void
 */
function axismundi_contacts_detail_row( array $entry, string $property, bool $published ) : void {
	$text  = axismundi_contacts_entry_text( $entry, $property );
	$label = trim( (string) ( $entry['label'] ?? '' ) );
	if ( 'anniversaries' === $property && '' === $text ) {
		$date = (array) ( $entry['date'] ?? array() );
		$text = sprintf(
			'%s-%02d-%02d',
			isset( $date['year'] ) ? (string) (int) $date['year'] : '____',
			(int) ( $date['month'] ?? 0 ),
			(int) ( $date['day'] ?? 0 )
		);
	}
	if ( '' === trim( $text ) ) {
		return;
	}
	?>
	<li class="ax-contacts-detail__value">
		<span class="ax-contacts-detail__text"><?php echo esc_html( $text ); ?></span>
		<?php if ( '' !== $label ) : ?>
			<span class="ax-contacts-detail__label"><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
		<?php if ( $published ) : ?>
			<span class="ax-contacts-detail__published"><?php esc_html_e( 'Published', 'axismundi-contacts' ); ?></span>
		<?php endif; ?>
	</li>
	<?php
}

/**
 * Read one contact.
 *
 * @param int  $card_id  Card.
 * @param int  $group_id Group being browsed, so Back returns to it.
 * @param int  $self_id  The Actor's own profile Card, or 0.
 * @param int  $actor_id Acting Actor.
 * @return void
 */
function axismundi_contacts_card_detail( int $card_id, int $group_id, int $self_id, int $actor_id ) : void {
	$row = axismundi_contacts_get_card( $card_id );
	if ( array() === $row ) {
		echo '<h1>' . esc_html__( 'Contact', 'axismundi-contacts' ) . '</h1>';
		echo '<p>' . esc_html__( 'That contact does not exist.', 'axismundi-contacts' ) . '</p>';
		return;
	}
	$card      = axismundi_contacts_card_document( $card_id );
	$is_self   = $card_id === $self_id && $card_id > 0;
	$published = $is_self ? axismundi_contacts_published_pointers( $actor_id ) : array();
	$name      = trim( (string) ( $card['name']['full'] ?? '' ) );
	?>
	<p><a href="<?php echo esc_url( axismundi_contacts_screen_url( -1, $group_id ) ); ?>">&larr; <?php esc_html_e( 'Back to contacts', 'axismundi-contacts' ); ?></a></p>
	<h1 class="wp-heading-inline"><?php echo esc_html( '' !== $name ? $name : __( '(no name)', 'axismundi-contacts' ) ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( axismundi_contacts_edit_url( $card_id, $group_id ) ); ?>"><?php esc_html_e( 'Edit', 'axismundi-contacts' ); ?></a>
	<hr class="wp-header-end">

	<div class="ax-contacts-detail">
		<section class="ax-contacts-detail__facts">
			<?php foreach ( axismundi_contacts_detail_sections() as $property => $label ) : ?>
				<?php
				$entries = (array) ( $card[ $property ] ?? array() );
				if ( array() === $entries ) {
					continue;
				}
				?>
				<h2><?php echo esc_html( $label ); ?></h2>
				<ul class="ax-contacts-detail__values">
					<?php foreach ( $entries as $key => $entry ) : ?>
						<?php
						if ( ! is_array( $entry ) ) {
							continue;
						}
						axismundi_contacts_detail_row( $entry, $property, in_array( $property . '/' . $key, $published, true ) );
						?>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</section>

		<?php if ( $is_self ) : ?>
			<?php axismundi_contacts_public_preview( $actor_id, $card ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * What a stranger receives, shown next to what is stored.
 *
 * The two are different documents and the difference is the whole point, so it is shown rather than
 * described. A person looking at their own card should be able to answer "is my phone number on the
 * internet" by looking, not by reasoning about a settings page.
 *
 * @param int                 $actor_id Acting Actor.
 * @param array<string,mixed> $card     Stored Card.
 * @return void
 */
function axismundi_contacts_public_preview( int $actor_id, array $card ) : void {
	$sharing   = axismundi_contacts_profile_sharing( $actor_id );
	$published = axismundi_contacts_published_pointers( $actor_id );
	$projected = axismundi_contacts_public_projection( $card, $published );
	?>
	<aside class="ax-contacts-detail__public">
		<h2><?php esc_html_e( 'What strangers receive', 'axismundi-contacts' ); ?></h2>
		<?php if ( 'public' !== $sharing ) : ?>
			<p class="description">
				<?php esc_html_e( 'This card is not shared publicly, so nobody receives any of it. The selection below is what would be published if it were.', 'axismundi-contacts' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the public address of this card. */
					esc_html__( 'Anybody may fetch this at %s.', 'axismundi-contacts' ),
					'<code>' . esc_html( (string) axismundi_contacts_public_profile_link( $actor_id ) ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<pre class="ax-contacts-detail__json"><?php echo esc_html( (string) wp_json_encode( $projected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		<p class="description">
			<?php esc_html_e( 'Chosen on the edit screen, one entry at a time. Nothing is published because it is on the card.', 'axismundi-contacts' ); ?>
		</p>
	</aside>
	<?php
}
