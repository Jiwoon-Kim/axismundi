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

The post type is intentionally non-public in this release. It is editable in
wp-admin through the Classic Editor but is not publicly queryable and has no
public rewrite, so a followers-only or mentioned-only body cannot leak through a
Core permalink before the fail-closed content-negotiation route exists. The
JSON-LD transformer and the Create, Update, and Delete lifecycle are owned by
Object Projections and Activities in later increments.

A permanent post deletion converts the envelope to a tombstone instead of
dropping it, so the canonical object UUID and author attribution survive for a
later Delete Activity and Tombstone projection.

== Changelog ==

= 0.0.1 =
* Register the private ax_note post type, forced to the Classic Editor.
* Install the verified wp_ax_notes federation envelope store with a tombstone state.
* Add a Classic Editor authoring UI and a lenient read/write envelope API whose mention read is the ordered union of the explicit list and body-derived anchors.
