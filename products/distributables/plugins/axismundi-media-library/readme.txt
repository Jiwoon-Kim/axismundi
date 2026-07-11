=== Axismundi Media Library ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: media, attachments

Promote WordPress attachments to independent, visibility-controlled media objects.

== Description ==

Axismundi Media Library promotes the WordPress attachment into an independent,
publishable media object with its own owner, visibility, and — in later phases —
virtual folders, rights, sensitivity, saved references, and federation.

Activating the plugin changes no existing media. **Core mode** leaves WordPress
attachments untouched. **Independent mode** detaches new uploads (post_parent 0)
and enforces per-item visibility — public, unlisted, or private — across the
attachment page, the REST API (single and collection), and the media picker.
Ownership is the WordPress author (post_author); permission reuses core
capabilities.

The full specification lives in the plugin's docs/ directory (SPEC, SECURITY,
ROUTING, COMPATIBILITY, DATA-MODEL, PHASES). Media archives (/media/) arrive in
Phase 1b.

== Installation ==

1. Upload and activate Axismundi Media Library.
2. Open Settings > Media Library (Axismundi). Activation changes no existing media.
3. Choose Independent mode to enable independent-attachment visibility.

== Changelog ==

= 0.0.3 =
* Ownership is now the WordPress author (post_author) as the single source of
  truth; removed the separate owner meta. Permission reuses core edit_post /
  edit_others_posts.
* Fix: partial attachment saves no longer reset listed/searchable (hidden field
  sentinel).
* Fix: reactivating while Independent mode is on re-acquires the attachment-pages
  option.
* New uploads set post_parent 0 before the INSERT (atomic), via
  wp_insert_attachment_data.
* Media modal now hides other users' unlisted media (matches SECURITY.md).
* Refresh admin and readme copy to reflect that Independent mode changes behaviour.

= 0.0.2 =
* Phase 1a: Independent-mode media visibility (public / unlisted / private) with
  legacy-public fallback, and per-surface access guards — HTML single, REST
  single (404), REST collection, and the media modal. New uploads become
  independent (post_parent 0, owner, defaults). Canonical single page
  /?attachment_id={id}. Attachment edit fields for visibility/listed/searchable.
  Media archives (/media/) arrive in Phase 1b.

= 0.0.1 =
* Phase 0 scaffold: non-destructive activation, a Core / Independent
  relationship-mode setting (recorded, no effect until Phase 1), and a read-only
  attachment parent-relationship scan.

== Copyright ==

Axismundi Media Library, Copyright 2026 KIM JIWOON.
Distributed under the terms of the GNU General Public License, version 3 or later.
