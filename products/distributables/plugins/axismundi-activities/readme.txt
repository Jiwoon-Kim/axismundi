=== Axismundi Activities ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors
Stable tag: 0.0.24
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, federation, social

Records ActivityStreams activities and derives social relationship state without owning
network transport, notifications, or delivery.

== Description ==

Axismundi Activities is the URI-first activity ledger and social relationship layer for
Axismundi. Actors owns identities, Object Projections owns object representations and remote
object cache retention, Notifications will own read state and recipient presentation, and
Federation will own HTTP inbox/outbox transport, signatures, and remote delivery.

Axismundi Actors is a required dependency and remains the authority for every actor URI.

Version 0.0.18 implements the immutable URI-keyed Activity ledger, Follow/Block relation
state, local and cached-remote Follow controls, URI-keyed Like/Undo and Announce/Undo,
FEP-044f QuoteRequest decisions, and read-only administrator inspection. It
also records one local outbound Create when a projectable Core Post is first published.
It creates no public Activity route, cron event, network request, inbox, notification, or
delivery queue. Media upload remains intentionally silent.

It also exposes a public-safe Outbox query contract for representation plugins. The
authoritative payload remains lossless while blind recipients and non-public Activities are
excluded from public projections.

== Changelog ==

= 0.0.24 =
* Let an object-owning product render a public ledger entry through its own
  canonical view-model path. Activities continues to select only public
  outbound Activity rows and has no dependency on a particular object product.

= 0.0.23 =
* Add the server-rendered `axismundi/actor-activity-feed` block. It projects
  only public outbound ledger entries for a public local Actor, never a second
  content archive or a disclosure of blind recipients.

= 0.0.22 =
* Allow consumers to resolve one exact outbound QuoteRequest generation while
  retaining the latest-request lookup for reconciliation and inspection.

= 0.0.21 =
* Record idempotent outbound and local FEP-044f QuoteRequests with finalized inline
  instruments, and reconcile the immutable first valid Accept or Reject from the ledger.
* Expose an exact request lookup and wake-up signal so held authored Objects can resume
  after approval without treating the signal as authority.

= 0.0.20 =
* Add one shared, fail-closed audience resolver for public, quiet-public, followers,
  and mentioned-only Objects, and snapshot the resolved to/cc members onto Core Post Create.
* Wait for block-editor REST metadata before recording the immutable initial Create.
* Add a domain-neutral lifecycle recorder that converges duplicate callbacks while recording
  embedded-object Create and Update Activities plus audience-preserving URI-only Delete.

= 0.0.19 =
* Embed the minimal FEP-044f QuoteRequest object in Accept and Reject decisions instead of
  reducing the protocol object to its URI.

= 0.0.18 =
* Revoke a standing QuoteAuthorization without deleting its identity and record one durable,
  idempotent outbound Delete addressed to the quote author.
* Keep the Delete privacy-minimal: its object is only the authorization URI, and neither the
  quoting Object nor quoted Object is embedded in the revocation Activity.
* Retry the same ledger projection on repeated or racing revocation calls without firing the
  authorization lifecycle hook or delivery queue twice.

= 0.0.17 =
* Store and process FEP-044f QuoteRequest Activities after their inbound ledger commit.
* Resolve the quoted local Object and its explicit policy through Object Projections, while
  keeping the Activities state machine independent of WordPress Post metadata.
* Accept `anyone` and accepted followers, reject `me`, non-followers, and unset policy for
  remote requesters, and preserve the first decision across replay or later policy changes.
* Issue one QuoteAuthorization for an accepted request and return its URI in `Accept.result`;
  denied requests produce an addressed Reject and no authorization.
* Reject contradictory inlined quote-post attribution or target claims without erasing the
  immutable inbound request.

= 0.0.16 =
* Expose a public-safe accepted-follower count derived from the relationship ledger,
  using the URI hash and exact URI together without exposing follower identities.

= 0.0.15 =
* DB v6 — add the FEP-044f QuoteAuthorization store. Consent state belongs to this ledger and
  is deliberately separate from the observed fact that one Object quotes another: withholding,
  rejecting, or revoking an authorization never erases a quote that exists.
* Mint each authorization its own immutable identity at /?ax_quote_authorization={uuid}. A
  query URI rather than a path, so proving consent never depends on permalink state. Object
  Projections owns the representation and route.
* One QuoteRequest issues at most one authorization: a re-delivered request returns the
  decision already made instead of minting a second identity for the same consent.
* Revocation withdraws the authorization and keeps the row, so a URI a peer already holds
  resolves to "revoked" rather than to nothing, and its UUID is never reassigned. Replaying
  the original request does not re-grant it; a new grant needs a new request.
* Verify the table, its unique indexes, and the engine before recording the schema version.

= 0.0.14 =
* DB v5 — store the ActivityStreams `instrument` member as an indexed URI and hash. This is
  a general member rather than a Quote alias: a FEP-044f QuoteRequest names the quoted
  Object in `object` and the independent Object doing the quoting in `instrument`, and
  `target` keeps its collection destination meaning for Add, Remove, and Move.
* Reduce an embedded instrument to its canonical id while keeping the original in the
  immutable payload, and treat a source event whose replay names a different instrument as a
  conflict rather than the same Activity.
* Verify the new column and index before recording the schema version, so a site whose
  migration failed retries instead of recording a version it never reached.

= 0.0.13 =
* Add idempotent personal Announce and matching Undo cycles. Announce references the canonical
  Object URI; Undo references the Announce Activity URI.
* Fail closed unless Object Projections proves public or quiet-public visibility from a local
  projection or cached observation, without a render-time network request.
* Address public Announces to Public and the original author so the origin server can apply
  the ActivityPub shares side effect, while the Bridge expands Public to follower inboxes.
* Add a nonce-protected Interactivity API Boost block and interaction-lease synchronization.
* Expose a count-only Object shares OrderedCollection without enumerating Actors or Activities.

= 0.0.12 =
* Add idempotent Like and Undo workflows keyed by canonical object URI. Undo always targets
  the Like Activity URI and preserves its explicit remote audience for transport adapters.
* Derive authoritative Like state and distinct-Actor counts from the immutable ledger, with
  no second counter store, and expose a public-safe query for Object Projections.
* Add a nonce-protected `axismundi/like-button` block using the WordPress Interactivity API.
  Optimistic UI state rolls back on failure and accepts the server response as final.
  Logged-in renders set a page-cache bypass before exposing user state or a REST nonce.
* Acquire and release Object Projections `interaction` leases for cached remote objects.

= 0.0.11 =
* Disambiguate remote relationships as `@handle@instance` while keeping local handles short.
* Add Follow back, Unfollow, and Activity-backed follower removal controls to the Follows screen.
* Replace imported outbound snapshots through an explicit Re-follow Activity instead of
  fabricating an Undo for unavailable legacy history.
* Let a newly received Follow URI supersede an accepted cycle and Accept that exact Activity.

= 0.0.10 =
* Auto-accept verified inbound remote Follow Activities when the local Actor does not
  require approval, recording an outbound Accept addressed to the remote Actor.
* Derive remote response direction from the committed inbound relation instead of requiring
  a second remote Actor cache lookup.
* Let a new inbound Follow supersede an imported legacy snapshot before auto-accepting it.

= 0.0.9 =
* Add outbound Follow and Undo controls for cached remote Actors on their cached profile
  and administrator detail screen.
* Address remote Follow, Accept, and Reject Activities explicitly to the remote Actor so a
  transport adapter can resolve its inbox without adding HTTP to this plugin.
* Keep imported legacy Follow snapshots read-only when their original Activity URI is not
  available, rather than inventing an invalid Undo.

= 0.0.8 =
* Add DB v4 relation provenance for accepted and pending legacy Follow snapshots without
  inventing Activity rows.
* Keep `legacy_pending` outside following projections and let real Follow/Accept/Reject/Undo
  Activities take permanent precedence over imported snapshots.
* Expose an idempotent snapshot import API for compatibility adapters.

= 0.0.7 =
* Add public-safe Actor Outbox queries for Object Projections without adding an HTTP route.
* Recognize full and compact ActivityStreams Public audience forms, exclude non-outbound or
  ineffective rows, and strip bto/bcc only from projection copies.
* Keep the authoritative immutable Activity payload unchanged.

= 0.0.6 =
* Add verified DB v3 source-event identities so retries and concurrent WordPress save
  requests converge on one immutable Activity.
* Consume Object Projections Core Post publish candidates and record one URI-referenced
  outbound Create. Publish edits and unpublish/re-publish do not duplicate Create; a later
  effective Delete begins a new lifecycle generation.
* Keep password posts and media uploads silent, perform no transport, and defer Reply until
  the Axismundi Notes CPT establishes the canonical local Note model.

= 0.0.5 =
* Require Contributor-level `edit_posts` access for local Follow controls and management.
  Subscribers remain read-only even if an older Actor record exists.
* Add nonce-protected local Follow state and actions to the administrator Users table.
  Keep cached remote Actors display-only until an official ActivityPub transport adapter exists.
* Show pending Follow requests sent by the current Actor with a cancellation action.

= 0.0.4 =
* Add local-only Follow, request cancellation, Unfollow, Accept, and Reject workflows for
  activated public Person actors. Auto-accept undeclared policies by default while honoring
  an Actor's explicit `manually_approves_followers` policy.
* Add Follow controls to local Actor profiles and a self-service `Follows` admin screen
  for approval policy, pending requests, followers, and following.
* Keep every workflow offline, reject remote Actors, and add an Actors-owned policy setter
  instead of writing the identity repository directly.

= 0.0.3 =
* Add verified DB v2 `wp_ax_activity_relations` materialization for Follow, Accept, Reject,
  Undo, and Block in the same transaction as the immutable Activity ledger.
* Derive followers/following from accepted Follow edges, enforce transition Actor authority,
  and reconcile an Accept or Reject that arrives before its Follow.
* Add the read-only `Tools > Activity Log` administrator inspector for recent Activities,
  immutable payloads, and current social relation state.

= 0.0.2 =
* Add the verified InnoDB `wp_ax_activities` repository with UUID local Activity URIs,
  exact URI/hash identity, bounded immutable payloads, normalized audience, and Actor/Object
  reverse lookups. Keep prefix tenancy and omit `blog_id`.
* Require every Actor URI to resolve through Axismundi Actors and reject direction/origin
  conflicts. Preserve remote inbound Activity ids exactly.
* Add idempotent replay, payload identity-conflict protection, post-commit recorded hooks,
  and same-Actor Undo effectiveness including out-of-order and Undo-of-Undo reconciliation.

= 0.0.1 =
* Lock Activity, relation, lifecycle, logical collection, media no-Create, lease, and
  prefix-tenancy contracts in docs without creating runtime state.
