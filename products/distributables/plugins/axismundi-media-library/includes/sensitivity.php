<?php
/**
 * Phase 4a — sensitive authority (state + who-set-it + lock).
 *
 * `_ax_media_sensitive` stays as a **derived, read-only effective boolean** that
 * serializers/UI consume (feeds, Media Collection, attachment page). The authority
 * lives in `_ax_media_sensitive_state` and decides who may change it:
 *   self_marked      → the owner may clear it
 *   automated_flagged→ the owner may appeal, NOT self-clear (moderator resolves)
 *   moderator_marked → the owner cannot clear it
 *   confirmed        → the owner cannot clear it
 * Legacy `_ax_media_sensitive = 1` with no state reads as self_marked, so existing
 * flags remain owner-clearable. DATA-MODEL §2.3, SECURITY §2.4, FEDERATED-MEDIA §6.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_SENSITIVE_STATE_META  = '_ax_media_sensitive_state';
const AXISMUNDI_MEDIA_SENSITIVE_SETBY_META  = '_ax_media_sensitive_set_by';
const AXISMUNDI_MEDIA_SENSITIVE_SETAT_META  = '_ax_media_sensitive_set_at';
const AXISMUNDI_MEDIA_SENSITIVE_LOCKED_META = '_ax_media_sensitive_locked';

/**
 * Valid sensitive states.
 *
 * @return string[]
 */
function axismundi_media_sensitive_states() : array {
	return array( 'none', 'self_marked', 'automated_flagged', 'moderator_marked', 'confirmed' );
}

/**
 * Grant the sensitivity capabilities from existing caps: moderators/editors get
 * moderate + override; any uploader may mark their own.
 *
 * @param array<string,bool> $allcaps All caps.
 * @return array<string,bool>
 */
function axismundi_media_sensitivity_caps( array $allcaps ) : array {
	if ( ! empty( $allcaps['edit_others_posts'] ) ) {
		$allcaps['moderate_media_sensitivity'] = true;
		$allcaps['override_media_sensitivity'] = true;
	}
	if ( ! empty( $allcaps['upload_files'] ) ) {
		$allcaps['mark_own_media_sensitive'] = true;
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'axismundi_media_sensitivity_caps', 10, 1 );

/**
 * The stored authority state, with legacy fallback (bare `_ax_media_sensitive = 1`
 * reads as self_marked).
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_sensitive_state( int $attachment_id ) : string {
	$state = (string) get_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_STATE_META, true );
	if ( in_array( $state, axismundi_media_sensitive_states(), true ) ) {
		return $state;
	}
	return '1' === (string) get_post_meta( $attachment_id, '_ax_media_sensitive', true ) ? 'self_marked' : 'none';
}

/**
 * Effective sensitivity (any state but none).
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function axismundi_media_is_sensitive( int $attachment_id ) : bool {
	return 'none' !== axismundi_media_sensitive_state( $attachment_id );
}

/**
 * Is the state locked against owner changes (moderator_marked / confirmed)?
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function axismundi_media_sensitive_locked( int $attachment_id ) : bool {
	return in_array( axismundi_media_sensitive_state( $attachment_id ), array( 'moderator_marked', 'confirmed' ), true );
}

/**
 * Can the user change this item's sensitive state to the given target? (No side
 * effects — the authority rules, reused by the UI and the setter.)
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $target        Desired state.
 * @param int    $user_id       Acting user.
 * @return true|WP_Error
 */
function axismundi_media_can_set_sensitive( int $attachment_id, string $target, int $user_id ) {
	if ( ! in_array( $target, axismundi_media_sensitive_states(), true ) ) {
		return new WP_Error( 'ax_media_sensitive_state', __( 'Invalid sensitivity state.', 'axismundi-media-library' ) );
	}
	// Moderators may set any state.
	if ( user_can( $user_id, 'moderate_media_sensitivity' ) ) {
		return true;
	}
	// Owners (edit_post) may only toggle between none and self_marked, and only while
	// the current state is none/self_marked.
	if ( ! user_can( $user_id, 'edit_post', $attachment_id ) ) {
		return new WP_Error( 'ax_media_sensitive_forbidden', __( 'You cannot change this item.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	$current = axismundi_media_sensitive_state( $attachment_id );
	if ( ! in_array( $current, array( 'none', 'self_marked' ), true ) ) {
		return new WP_Error(
			'automated_flagged' === $current ? 'ax_media_sensitive_appeal' : 'ax_media_sensitive_locked',
			'automated_flagged' === $current
				? __( 'This item was automatically flagged; you may appeal but cannot clear it.', 'axismundi-media-library' )
				: __( 'A moderator set this sensitivity; you cannot change it.', 'axismundi-media-library' ),
			array( 'status' => 403 )
		);
	}
	if ( ! in_array( $target, array( 'none', 'self_marked' ), true ) ) {
		return new WP_Error( 'ax_media_sensitive_moderator_only', __( 'Only a moderator can set that sensitivity state.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Set the sensitive authority state, enforcing who-may-change. Keeps the derived
 * `_ax_media_sensitive` boolean and the locked flag in sync.
 *
 * @param int      $attachment_id Attachment ID.
 * @param string   $target        Desired state.
 * @param int|null $user_id       Acting user (defaults to current).
 * @return true|WP_Error
 */
function axismundi_media_set_sensitive_state( int $attachment_id, string $target, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	$allowed = axismundi_media_can_set_sensitive( $attachment_id, $target, (int) $user_id );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}
	if ( axismundi_media_sensitive_state( $attachment_id ) === $target ) {
		return true; // idempotent, no re-stamp.
	}
	if ( 'none' === $target ) {
		delete_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_STATE_META );
		delete_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_SETBY_META );
		delete_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_SETAT_META );
		delete_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_LOCKED_META );
		update_post_meta( $attachment_id, '_ax_media_sensitive', '0' );
		return true;
	}
	update_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_STATE_META, $target );
	update_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_SETBY_META, (int) $user_id );
	update_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_SETAT_META, current_time( 'mysql', true ) );
	update_post_meta( $attachment_id, AXISMUNDI_MEDIA_SENSITIVE_LOCKED_META, in_array( $target, array( 'moderator_marked', 'confirmed' ), true ) ? '1' : '0' );
	update_post_meta( $attachment_id, '_ax_media_sensitive', '1' );
	return true;
}

/**
 * Human labels for sensitive states.
 *
 * @return array<string,string>
 */
function axismundi_media_sensitive_state_labels() : array {
	return array(
		'none'             => __( 'Not sensitive', 'axismundi-media-library' ),
		'self_marked'      => __( 'Marked by owner', 'axismundi-media-library' ),
		'automated_flagged' => __( 'Automatically flagged', 'axismundi-media-library' ),
		'moderator_marked' => __( 'Marked by a moderator', 'axismundi-media-library' ),
		'confirmed'        => __( 'Confirmed sensitive', 'axismundi-media-library' ),
	);
}
