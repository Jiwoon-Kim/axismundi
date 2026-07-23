=== Axismundi Note ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.0.25
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

= 0.0.25 =
* Remove the Note-specific Actor-feed object renderer. Object Projections now
  resolves and renders any feed object (local Note or cached remote) through the
  shared view model, gated by the same visibility predicate this plugin already
  registers, so a boosted object renders as well as an authored one. Note keeps
  only its domain adapters and owns no feed rendering.

= 0.0.24 =
* Advertise the Object Projections-owned replies collection from active local Notes and Questions; direct public textual replies are available as canonical URI pages while private and unresolved replies remain undisclosed.

= 0.0.23 =
* Let a Note's author (or an administrator) preview their own not-yet-public document at its canonical route and from the private list's row action, instead of it 404ing even for its own author. Robots directives keep any such preview out of search indexes; public visibility for everyone else is unchanged.

= 0.0.22 =
* Route local 410 Tombstones through Object Projections' dedicated privacy-minimal Tombstone template.

= 0.0.21 =
* Reuse the Object Projections-owned editable single-Object template and expose local Actor identity, avatar, and Quote context through the neutral Object view model.

= 0.0.20 =
* Allow a federated Question to become a Note through the editor, emitting a same-URI Update with a Note snapshot.

= 0.0.19 =
* Keep local vote redirects on the exact canonical Question URL and show the
  one-time result without a route-breaking query argument.

= 0.0.18 =
* Add a canonical View link to the private Note list for Notes that the public
  document route can safely expose.

= 0.0.17 =
* Let a locked Question update its closing time without resubmitting frozen poll options or voting mode.

= 0.0.16 =
* Let an unfederated Question return to an ordinary Note through the Question editor, while preserving the federation lock after first publication.

= 0.0.15 =
* Normalize Question option names at the editor REST boundary, disable frozen Question fields, and deduplicate manipulated vote-form selections.

= 0.0.14 =
* Add the Question editor panel and a nonce-protected local vote form that creates constrained vote Notes through the normal federation lifecycle.

= 0.0.13 =
* Derive local Question tallies from strict Activity-ledger vote observations, including oneOf/anyOf duplicate rules and Undo/Delete removal.
* Keep confirmed votes out of the textual reply thread while preserving ordinary replies to Questions.

= 0.0.12 =
* Render a public Create(Note) on an Actor Activity feed through the current
  Note source and the shared Object Projections view model, rather than through
  an inline Activity snapshot. Feed cards reuse Note media, sensitive-content,
  and Question rendering without emitting personalized interaction controls.

= 0.0.11 =
* Close a race where a Question's mode/options freeze could be bypassed: a save
  that read the row as unlocked can no longer overwrite mode or options if a
  concurrent lock commits before its own write runs. The freeze boundary is now
  a `SELECT ... FOR UPDATE` row lock evaluated inside the write transaction,
  not the earlier non-transactional read.
* Treat a Question whose scheduled `closes_at` has already passed as closed --
  in the JSON-LD `closed` member and the read-only Poll block alike -- even
  when nothing ever recorded an explicit `closed_at`.

= 0.0.10 =
* Project a Question Note as ActivityStreams `type: Question` with a name-only
  `oneOf`/`anyOf` option array, `votersCount`, and `endTime`/`closed` -- an ordinary
  Note is completely unaffected. All tallies are structurally correct zero
  placeholders; vote recording is a later increment.
* Add the shared, tally-agnostic poll view (`axismundi_note_question_view()`) the
  JSON-LD projection and the HTML view model both read, so a Question's `type`,
  options, and closing state stay in lockstep between the two.

= 0.0.9 =
* Add Question, a sibling object type on the same private ax_note post: structured
  `wp_ax_questions`/`wp_ax_question_options` storage (never post_content or a block
  attribute), a plain-text option list unique and exact-match within one Question,
  and mode/options that freeze at the same first-federation moment attribution does.
  A Note already federated as an ordinary Note cannot retroactively become a Question,
  and an under-provisioned Question is held rather than federated incomplete. Storage
  and the freeze contract only in this release; JSON-LD projection, the read-only Poll
  block, and the vote classifier are later increments.

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
