=== Axismundi Note ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.0.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, federation, note, fediverse

Note-owned local object container: the ax_note post type and its federation envelope.

== Description ==

Axismundi Note owns the storage substrate for locally authored Note objects. It
registers the private `ax_note` custom post type and a `wp_ax_notes` envelope
table that holds the authored federation fields with no Core Post home:
visibility, language, in-reply-to and context URIs, sensitivity, a content
warning, and an explicit mention list.

The post type remains private and is editable through the Classic Editor. An
exact canonical `?ax_note={uuid}` request can project public and quiet-public
Notes as ActivityStreams JSON-LD; followers-only and mentioned-only Notes fail
closed for anonymous requests. Human-readable object pages and the Create,
Update, and Delete lifecycle remain later increments.

A permanent post deletion converts the envelope to a tombstone instead of
dropping it, so the canonical object UUID and author attribution survive for a
later Delete Activity and Tombstone projection.

== Changelog ==

= 0.0.1 =
* Register the private ax_note post type, forced to the Classic Editor.
* Install the verified wp_ax_notes federation envelope store with a tombstone state.
* Add a Classic Editor authoring UI and a lenient read/write envelope API whose mention read is the ordered union of the explicit list and body-derived anchors.
