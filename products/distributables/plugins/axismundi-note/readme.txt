=== Axismundi Note ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.1.0
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

The post type remains private and uses a restricted block editor with one
structured REST-backed document panel. An
exact canonical `?ax_note={uuid}` request can project public and quiet-public
Notes as ActivityStreams JSON-LD; followers-only and mentioned-only Notes fail
closed for anonymous requests. The same URI has a plugin-owned human-readable
block template: active public Notes return 200, concealed or unknown Notes return
404, and deleted Notes return a privacy-minimal 410 Tombstone. Active public
views expose Like and Boost controls.

Publishing records one immutable Create with the complete committed Note Object.
Later representation changes record Update only when that snapshot changes;
withdrawal records a privacy-minimal URI-only Delete addressed to the preceding
lifecycle audience. Repeated callbacks converge on the same ledger event, and a
post-Delete republication begins a new Create generation. ActivityPub Bridge may
deliver those committed Activities, but Note itself performs no network request.

When Axismundi Media Library runs in Independent mode, the same document panel
selects ordered attachments through its relation store. Note keeps no duplicate
media metadata: Object Projections reuses Media Library's FEP-1311 renditions,
alternative text, visibility, and sensitive-content authority.

A permanent post deletion converts the envelope to a tombstone instead of
dropping it, so the canonical object UUID and author attribution survive for a
later Delete Activity and Tombstone projection.

== Changelog ==

= 0.1.0 =
* Direct notes are read as conversations, owned by Note. Per-person read state
  lives with the conversation rather than with any notification about it, so
  reading a message on one device is reading it everywhere.
* A shared topic no longer becomes a shared conversation: an inherited context
  is honoured only when it names a direct Note, never when it is a forum topic.
* Tells the people a note named. A public note that names somebody is a mention;
  a note only its recipients can read is a message. Neither notification carries
  what was said, and who was named is read from the note rather than re-parsed
  out of its text.
* Shapes the Notes list like Comments and splits the answer ledger out; points
  the editor at the canonical Object document.

Earlier releases are listed in changelog.txt.

