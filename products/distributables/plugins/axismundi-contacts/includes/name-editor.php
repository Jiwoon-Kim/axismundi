<?php
/**
 * Editing a name, and the other writings of it (dev screen).
 *
 * The primary name is `Card.name`; everything else is a localization. There is deliberately no
 * localization for the Card's own language sitting beside the primary name -- that would be the same
 * fact in two editable places, and whichever one somebody typed into last would win by accident.
 *
 * Two rules from the storage model are the screen's job to keep:
 *
 * A written-out name stays written out. Nothing here turns `Trump` into a given name, because
 * deciding which half of `Jiwoon Kim` is a surname is a guess and a guess in a stored field stops
 * looking like one. Components appear only where somebody typed them.
 *
 * Editing a name touches the name. A localization may carry paths for fields this screen knows
 * nothing about -- `nicknames/n1/name` is an ordinary one -- and rewriting the whole patch would
 * throw them away, so the writer replaces only what is about the name.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** The component kinds this screen edits, and the order they are read in. */
const AXISMUNDI_CONTACTS_NAME_KINDS = array( 'title', 'given', 'given2', 'surname', 'surname2', 'credential' );

/**
 * The two layouts offered, as the sequence of kinds each reads in.
 *
 * @return array<string,array{label:string,order:string[]}>
 */
function axismundi_contacts_name_orders() : array {
	return array(
		'given-family' => array(
			'label' => __( 'Given name first', 'axismundi-contacts' ),
			'order' => array( 'title', 'given', 'given2', 'surname', 'surname2', 'credential' ),
		),
		'family-given' => array(
			'label' => __( 'Family name first', 'axismundi-contacts' ),
			'order' => array( 'title', 'surname', 'surname2', 'given', 'given2', 'credential' ),
		),
	);
}

/**
 * What each component is called on screen.
 *
 * @return array<string,string>
 */
function axismundi_contacts_name_kind_labels() : array {
	return array(
		'title'      => __( 'Title', 'axismundi-contacts' ),
		'given'      => __( 'Given name', 'axismundi-contacts' ),
		'given2'     => __( 'Middle name', 'axismundi-contacts' ),
		'surname'    => __( 'Family name', 'axismundi-contacts' ),
		'surname2'   => __( 'Second family name', 'axismundi-contacts' ),
		'credential' => __( 'Credential', 'axismundi-contacts' ),
	);
}

/**
 * One name's components, by kind.
 *
 * @param array<string,mixed> $name Name object.
 * @return array<string,string>
 */
function axismundi_contacts_name_values( array $name ) : array {
	$values = array();
	foreach ( (array) ( $name['components'] ?? array() ) as $component ) {
		if ( is_array( $component ) ) {
			$values[ (string) ( $component['kind'] ?? '' ) ] = (string) ( $component['value'] ?? '' );
		}
	}
	return $values;
}

/**
 * Which of the offered layouts a name already reads as.
 *
 * Derived from the components rather than stored, for the same reason an entry's label is: storing
 * the answer as well as the values it produced would be two records of one fact, and a Card arriving
 * from another client has no stored answer to read.
 *
 * @param array<string,mixed> $name Name object.
 * @return string
 */
function axismundi_contacts_name_order( array $name ) : string {
	$kinds = array();
	foreach ( (array) ( $name['components'] ?? array() ) as $component ) {
		$kind = is_array( $component ) ? (string) ( $component['kind'] ?? '' ) : '';
		if ( in_array( $kind, array( 'given', 'surname' ), true ) ) {
			$kinds[] = $kind;
		}
	}
	return array( 'surname', 'given' ) === $kinds ? 'family-given' : 'given-family';
}

/**
 * The detail fields for one name.
 *
 * @param string              $prefix Form field prefix.
 * @param array<string,mixed> $name   Name object.
 * @param bool                $open   Whether to start expanded.
 * @return void
 */
function axismundi_contacts_name_details( string $prefix, array $name, bool $open = false ) : void {
	$values = axismundi_contacts_name_values( $name );
	$order  = axismundi_contacts_name_order( $name );
	$labels = axismundi_contacts_name_kind_labels();
	?>
	<details<?php echo $open ? ' open' : ''; ?> class="ax-contacts-name-details">
		<summary><?php esc_html_e( 'Name details', 'axismundi-contacts' ); ?></summary>
		<p>
			<label>
				<?php esc_html_e( 'Reading order', 'axismundi-contacts' ); ?>
				<select name="<?php echo esc_attr( $prefix ); ?>[order]">
					<?php foreach ( axismundi_contacts_name_orders() as $key => $layout ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $order ); ?>><?php echo esc_html( $layout['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<?php foreach ( AXISMUNDI_CONTACTS_NAME_KINDS as $kind ) : ?>
			<p>
				<label>
					<?php echo esc_html( $labels[ $kind ] ); ?><br>
					<input type="text" name="<?php echo esc_attr( $prefix ); ?>[<?php echo esc_attr( $kind ); ?>]" value="<?php echo esc_attr( (string) ( $values[ $kind ] ?? '' ) ); ?>" class="regular-text">
				</label>
			</p>
		<?php endforeach; ?>
	</details>
	<?php
}

/**
 * Build a name from what was submitted for one prefix.
 *
 * @param string              $prefix   Form field prefix.
 * @param array<string,mixed> $existing Name as stored, so anything this screen does not edit stays.
 * @return array<string,mixed> Name object, or an empty array when nothing was given.
 */
function axismundi_contacts_name_from_request( string $prefix, array $existing = array() ) : array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
	$sent = isset( $_POST[ $prefix ] ) && is_array( $_POST[ $prefix ] ) ? wp_unslash( $_POST[ $prefix ] ) : array();
	$full = sanitize_text_field( (string) ( $sent['full'] ?? '' ) );
	$key  = (string) ( $sent['order'] ?? 'given-family' );
	$plan = axismundi_contacts_name_orders()[ $key ] ?? axismundi_contacts_name_orders()['given-family'];

	$components = array();
	foreach ( $plan['order'] as $kind ) {
		$value = sanitize_text_field( (string) ( $sent[ $kind ] ?? '' ) );
		if ( '' !== $value ) {
			$components[] = array( '@type' => 'NameComponent', 'kind' => $kind, 'value' => $value );
		}
	}
	if ( '' === $full && array() === $components ) {
		return array();
	}
	$name = $existing;
	$name['@type'] = 'Name';
	if ( '' !== $full ) {
		$name['full'] = $full;
	} else {
		unset( $name['full'] );
	}
	if ( array() !== $components ) {
		$name['components'] = $components;
		// Said to be ordered because they are: the sequence above is the one somebody chose.
		$name['isOrdered'] = true;
	} else {
		/*
		 * A name given as a written-out string keeps no components. Nothing is inferred from `full`
		 * here, which is the same rule the store keeps: this screen may not decide which half of
		 * `Jiwoon Kim` is the surname either.
		 */
		unset( $name['components'], $name['isOrdered'] );
	}
	return $name;
}

/**
 * The other writings of this name, and the control that adds one.
 *
 * Script-specific tags are shown here as ordinary rows when a Card already carries them. Nothing on
 * this side creates one -- somebody who typed a Korean name and an English name gets `ko-KR` and
 * `en-US` -- but a `ko-Latn` that arrived in an import is real data and is edited like any other.
 *
 * @param array<string,mixed> $card Card document.
 * @return void
 */
function axismundi_contacts_localized_name_rows( array $card ) : void {
	$tags = axismundi_contacts_localized_name_tags( $card );
	sort( $tags );
	?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Other writings', 'axismundi-contacts' ); ?></th>
		<td>
			<p class="description"><?php esc_html_e( 'The same person written in another language or script. A romanisation and a name somebody uses in another language are different things, so each is kept on its own.', 'axismundi-contacts' ); ?></p>
			<?php foreach ( $tags as $index => $tag ) : ?>
				<?php $name = axismundi_contacts_localized_name( $card, $tag ); ?>
				<div class="ax-contacts-localized">
					<input type="hidden" name="localized[<?php echo esc_attr( (string) $index ); ?>][tag]" value="<?php echo esc_attr( $tag ); ?>">
					<p>
						<code><?php echo esc_html( $tag ); ?></code>
						<input type="text" name="localized[<?php echo esc_attr( (string) $index ); ?>][full]" value="<?php echo esc_attr( (string) ( $name['full'] ?? '' ) ); ?>" class="regular-text">
						<label>
							<input type="checkbox" name="localized[<?php echo esc_attr( (string) $index ); ?>][remove]" value="1">
							<?php esc_html_e( 'Remove', 'axismundi-contacts' ); ?>
						</label>
					</p>
					<?php axismundi_contacts_name_details( 'localized_detail_' . $index, $name ); ?>
				</div>
			<?php endforeach; ?>
			<p>
				<label>
					<?php esc_html_e( 'Add a writing', 'axismundi-contacts' ); ?>
					<input type="text" name="localized_new_tag" value="" placeholder="en-US" size="10" list="ax-contacts-tags">
					<datalist id="ax-contacts-tags">
						<?php foreach ( array( 'ko-KR', 'en-US', 'ja-JP', 'zh-CN' ) as $suggestion ) : ?>
							<option value="<?php echo esc_attr( $suggestion ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
					<input type="text" name="localized_new_full" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Name in that language', 'axismundi-contacts' ); ?>">
				</label>
			</p>
		</td>
	</tr>
	<?php
}

/**
 * Apply what was submitted for the other writings.
 *
 * @param array<string,mixed> $card Card document.
 * @return array<string,mixed>
 */
function axismundi_contacts_localized_names_from_request( array $card ) : array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
	$rows = isset( $_POST['localized'] ) && is_array( $_POST['localized'] ) ? wp_unslash( $_POST['localized'] ) : array();
	foreach ( $rows as $index => $row ) {
		$tag = sanitize_text_field( (string) ( $row['tag'] ?? '' ) );
		if ( '' === $tag ) {
			continue;
		}
		if ( ! empty( $row['remove'] ) ) {
			// Only the name goes. A localization of something else under the same tag is not this
			// screen's to delete, and the tag itself survives if any of it is left.
			$card = axismundi_contacts_set_localized_name( $card, $tag, array() );
			continue;
		}
		$name         = axismundi_contacts_name_from_request( 'localized_detail_' . $index, axismundi_contacts_localized_name( $card, $tag ) );
		$name['full'] = sanitize_text_field( (string) ( $row['full'] ?? '' ) );
		if ( '' === $name['full'] ) {
			unset( $name['full'] );
		}
		$card = axismundi_contacts_set_localized_name( $card, $tag, array() === array_diff_key( $name, array( '@type' => true ) ) ? array() : $name );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
	$new_tag = isset( $_POST['localized_new_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['localized_new_tag'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
	$new_full = isset( $_POST['localized_new_full'] ) ? sanitize_text_field( wp_unslash( $_POST['localized_new_full'] ) ) : '';
	if ( '' !== $new_tag && '' !== $new_full ) {
		$card = axismundi_contacts_set_localized_name( $card, $new_tag, array( 'full' => $new_full ) );
	}
	return $card;
}
