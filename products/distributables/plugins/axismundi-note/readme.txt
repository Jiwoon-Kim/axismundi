=== Axismundi Note ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.0.8
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

= 0.0.8 =
* Resolve an arbitrary Note URI (not only the current request) for Object Projections'
  new URI-keyed thread-edge index, so a Note's own `inReplyTo` participates in the same
  unified local/remote reply and parent lookup other object types share without any
  Note-specific write code -- the index derives every edge from the Activities ledger.
* Add the reply-context and replies blocks to the single-Note template.

= 0.0.7 =
* Keep legacy unset Quote policy fail-closed while giving newly authored Notes
  an explicit public and anyone default.
* Mint a new outbound QuoteRequest generation when an author removes or changes
  a target, allowing an explicit retry after a terminal decision.

= 0.0.6 =
* Default newly authored Notes to public audience with anyone Quote approval.
* Add Quote target authoring and read-only pending, accepted, rejected, self, and invalid
  status to the Federation panel.
* Re-project quote aliases and verified QuoteAuthorization evidence from the immutable
  ledger, while pending, rejected, or invalid Quotes remain unavailable on public routes.

= 0.0.5 =
* Add the authored Who can quote this post? policy to the Note envelope, REST document
  panel, local QuoteRequest decision path, and conditional interactionPolicy projection.
* Gate outbound Quotes through self, local-other, and remote branches: self-quotes create
  immediately, while other targets require a matching QuoteAuthorization before Create.
* Reconcile missed approval wake-ups idempotently and fail closed on rejected, stale,
  tombstoned, unauthenticated, or mismatched Quote evidence.

= 0.0.4 =
* Store an authored outbound Quote target URI (schema v2) alongside the existing envelope, read and
  written through the same structured REST panel field.
* Add a no-fetch, three-way classification of a quote target's ownership -- the quoting Note's own
  author, a different local Actor, or a remote Actor -- as the foundation for the outbound Quote
  request/authorization lifecycle.

= 0.0.3 =
* Elevate an attachment's sensitive state to the containing federated Note so Mastodon and
  other receivers apply the content warning boundary, without mutating the authored Note
  envelope.

= 0.0.2 =
* Reject attachment selections that have no federatable media rendition instead of sending
  an HTML object page as if it were image data.
* Remove the unsupported Core category panel from the Note editor; Notes remain taxonomy-free.

= 0.0.1 =
* Register the private ax_note post type with a restricted block editor and structured envelope panel.
* Install the verified wp_ax_notes federation envelope store with a tombstone state.
* Add a strict read/write envelope API whose mention read is the ordered union of the explicit list and body-derived anchors.
* Project public Notes and deleted Tombstones as ActivityStreams JSON-LD and human-readable block-template pages.
* Add Like and Boost controls to active public Note pages without exposing private or deleted objects.
* Add ordered Media Library attachment selection without adding an envelope column, and reuse public FEP-1311 descriptors in JSON-LD and HTML.
* Record idempotent embedded-object Create/Update and URI-only Delete Activities from strict Note publication boundaries.
