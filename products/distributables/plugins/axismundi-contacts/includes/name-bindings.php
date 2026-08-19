<?php
/**
 * Which of my names each language sees (dev screen).
 *
 * Two axes that must not be collapsed into one control. The locale is who is looking; the source is
 * which writing of the name they are shown. Pointing `en-US` at `ko-Latn` says English readers see
 * the romanisation -- it does not create a `ko-Latn` slot on the Actor, and choosing a source never
 * invents a locale.
 *
 * The value of each option is the source it identifies rather than the string it currently produces.
 * `Jiwoon Kim` may sit under `ko-Latn` and under `en` at once, and a control keyed on the text could
 * not tell which one somebody picked -- so a later correction would follow the wrong one.
 *
 * Choosing a source binds; choosing `custom` and typing does not. Those are the plugin's two write
 * paths, and the screen keeps them apart so that nothing has to guess afterwards which had happened.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** The option value that means somebody typed this one themselves. */
const AXISMUNDI_CONTACTS_BINDING_CUSTOM = 'custom';

/**
 * What to call one of the card's names in a list of choices.
 *
 * @param string $tag Source tag, '' for the card's primary name.
 * @return string
 */
function axismundi_contacts_source_label( string $tag ) : string {
	return '' === $tag ? __( 'Main name', 'axismundi-contacts' ) : $tag;
}

/**
 * One locale's control: which writing it shows, or a name typed for it.
 *
 * @param int                  $actor_id Actor identity.
 * @param string               $locale   Actor locale, or '' for the default name.
 * @param array<string,string> $offered  Source tag => name.
 * @param string               $index    Form row index.
 * @return void
 */
function axismundi_contacts_binding_control( int $actor_id, string $locale, array $offered, string $index ) : void {
	$field    = 'name';
	$language = '' !== $locale ? $locale : (string) axismundi_actors_get_by_identity( $actor_id )->get_default_language();
	$binding  = axismundi_actors_text_binding( $actor_id, $field, $language );
	$map      = axismundi_actors_get_text_map( $actor_id );
	$value    = (string) ( $map[ $language ]['name'] ?? '' );
	$bound    = AXISMUNDI_CONTACTS_NAME_SOURCE === $binding['source'];
	$broken   = $bound && ! array_key_exists( $binding['source_tag'], $offered );
	$selected = $bound ? $binding['source_tag'] : AXISMUNDI_CONTACTS_BINDING_CUSTOM;
	?>
	<input type="hidden" name="binding[<?php echo esc_attr( $index ); ?>][locale]" value="<?php echo esc_attr( $language ); ?>">
	<select name="binding[<?php echo esc_attr( $index ); ?>][source]">
		<?php foreach ( $offered as $tag => $name ) : ?>
			<option value="<?php echo esc_attr( 'tag:' . $tag ); ?>"<?php selected( ! $broken && $bound && $tag === $binding['source_tag'] ); ?>>
				<?php
				printf(
					/* translators: 1: name as written, 2: which writing of the name it is. */
					esc_html__( '%1$s — %2$s', 'axismundi-contacts' ),
					esc_html( $name ),
					esc_html( axismundi_contacts_source_label( (string) $tag ) )
				);
				?>
			</option>
		<?php endforeach; ?>
		<?php if ( $broken ) : ?>
			<?php
			/*
			 * Shown as itself rather than disguised as one of the choices above. The writing it followed
			 * is gone, and offering the value back as if it were still on the card would hide that.
			 */
			?>
			<option value="broken" selected><?php echo esc_html( $value ); ?> — <?php esc_html_e( 'source missing', 'axismundi-contacts' ); ?></option>
		<?php endif; ?>
		<option value="<?php echo esc_attr( AXISMUNDI_CONTACTS_BINDING_CUSTOM ); ?>"<?php selected( AXISMUNDI_CONTACTS_BINDING_CUSTOM === $selected && ! $broken ); ?>><?php esc_html_e( 'Type one…', 'axismundi-contacts' ); ?></option>
	</select>
	<input type="text" name="binding[<?php echo esc_attr( $index ); ?>][custom]" value="<?php echo esc_attr( $bound ? '' : $value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Name to show', 'axismundi-contacts' ); ?>">
	<?php if ( $broken ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: the writing this name followed. */
				esc_html__( 'This followed %s, which the card no longer has. The published name is unchanged until you choose again.', 'axismundi-contacts' ),
				'<code>' . esc_html( $binding['source_tag'] ) . '</code>'
			);
			?>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * The display-name section of the profile screen.
 *
 * @param int $actor_id Actor identity.
 * @return void
 */
function axismundi_contacts_binding_rows( int $actor_id ) : void {
	$offered = axismundi_contacts_name_representations( $actor_id );
	if ( array() === $offered ) {
		return;
	}
	$actor    = axismundi_actors_get_by_identity( $actor_id );
	$default  = (string) $actor->get_default_language();
	$map      = axismundi_actors_get_text_map( $actor_id );
	$locales  = array_values( array_diff( array_keys( $map ), array( $default ) ) );
	sort( $locales );
	?>
	<h2><?php esc_html_e( 'What each language shows', 'axismundi-contacts' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Which writing of your name a reader sees. A romanisation and a name you use in another language are different things, so this is a choice rather than something worked out from the language tags.', 'axismundi-contacts' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_contacts_save_bindings">
		<?php wp_nonce_field( 'ax_contacts_bindings_' . $actor_id ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Display name', 'axismundi-contacts' ); ?></th>
				<td><?php axismundi_contacts_binding_control( $actor_id, '', $offered, 'default' ); ?></td>
			</tr>
			<?php foreach ( $locales as $position => $locale ) : ?>
				<tr>
					<th scope="row"><code><?php echo esc_html( $locale ); ?></code></th>
					<td>
						<?php axismundi_contacts_binding_control( $actor_id, (string) $locale, $offered, (string) $position ); ?>
						<label>
							<input type="checkbox" name="binding[<?php echo esc_attr( (string) $position ); ?>][remove]" value="1">
							<?php esc_html_e( 'Remove this language', 'axismundi-contacts' ); ?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="ax-contacts-new-locale"><?php esc_html_e( 'Add a language', 'axismundi-contacts' ); ?></label></th>
				<td>
					<?php
					/*
					 * The locale is asked for on its own. A control that offered `ko-Latn` here would be
					 * mixing who is looking with which writing they see, and picking a writing would
					 * quietly create a language slot nobody asked for.
					 */
					?>
					<input type="text" id="ax-contacts-new-locale" name="new_locale" value="" size="10" placeholder="en-US">
					<select name="new_source">
						<?php foreach ( $offered as $tag => $name ) : ?>
							<option value="<?php echo esc_attr( 'tag:' . $tag ); ?>"><?php echo esc_html( $name . ' — ' . axismundi_contacts_source_label( (string) $tag ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save display names', 'axismundi-contacts' ) ); ?>
	</form>
	<?php
}

/**
 * Apply one row of the display-name form.
 *
 * @param int                 $actor_id Actor identity.
 * @param array<string,mixed> $row      Submitted row.
 * @return true|WP_Error
 */
function axismundi_contacts_apply_binding_row( int $actor_id, array $row ) {
	$locale = sanitize_text_field( (string) ( $row['locale'] ?? '' ) );
	if ( '' === $locale ) {
		return true;
	}
	if ( ! empty( $row['remove'] ) ) {
		// The row goes, and its binding with it: there is no longer a name here to explain.
		return axismundi_actors_set_text( $actor_id, 'name', $locale, '' );
	}
	$source = sanitize_text_field( (string) ( $row['source'] ?? '' ) );
	if ( 'broken' === $source ) {
		/*
		 * Left exactly as it was. Somebody saving this form without touching a broken row has not
		 * decided anything about it, and rewriting it as `custom` here would silently throw away the
		 * binding they still have to fix.
		 */
		return true;
	}
	if ( AXISMUNDI_CONTACTS_BINDING_CUSTOM === $source ) {
		// Typed, so it follows nothing from now on.
		return axismundi_actors_set_text( $actor_id, 'name', $locale, sanitize_text_field( (string) ( $row['custom'] ?? '' ) ) );
	}
	if ( ! str_starts_with( $source, 'tag:' ) ) {
		return true;
	}
	return axismundi_contacts_bind_actor_name( $actor_id, $locale, substr( $source, 4 ) );
}

/**
 * Save the display-name choices.
 *
 * @return void
 */
function axismundi_contacts_handle_save_bindings() : void {
	$current = axismundi_contacts_current_book();
	if ( '' !== $current['error'] ) {
		wp_die( esc_html( $current['error'] ), '', array( 'response' => 403 ) );
	}
	$actor_id = (int) $current['actor']->get_identity_id();
	check_admin_referer( 'ax_contacts_bindings_' . $actor_id );

	$result = true;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$rows = isset( $_POST['binding'] ) && is_array( $_POST['binding'] ) ? wp_unslash( $_POST['binding'] ) : array();
	foreach ( $rows as $row ) {
		$applied = axismundi_contacts_apply_binding_row( $actor_id, (array) $row );
		if ( is_wp_error( $applied ) ) {
			$result = $applied;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$new_locale = isset( $_POST['new_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['new_locale'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$new_source = isset( $_POST['new_source'] ) ? sanitize_text_field( wp_unslash( $_POST['new_source'] ) ) : '';
	if ( '' !== $new_locale && str_starts_with( $new_source, 'tag:' ) ) {
		$added = axismundi_contacts_bind_actor_name( $actor_id, $new_locale, substr( $new_source, 4 ) );
		if ( is_wp_error( $added ) ) {
			$result = $added;
		}
	}
	axismundi_contacts_redirect_to( $result, axismundi_contacts_profile_url() );
}
add_action( 'admin_post_axismundi_contacts_save_bindings', 'axismundi_contacts_handle_save_bindings' );
