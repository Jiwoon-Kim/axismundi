=== Axismundi Object Projections ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.32
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, jsonld, federation

Projects WordPress objects into ActivityStreams JSON-LD through a transformer registry and one renderer.

== Description ==

Axismundi Object Projections turns a WordPress object (post, attachment, archive, folder)
or Axismundi Actor into an ActivityStreams 2.0 object or collection, so its existing URL can answer
with JSON-LD under content negotiation. It owns representation only — a transformer registry,
object/collection URIs, and the single JSON-LD renderer.

It does not own an Activity ledger, Inbox writes, Follow/Like/Announce state, HTTP signatures, or delivery;
those belong to Axismundi Activities and the ActivityPub transport boundary. It does own public
read representations such as an Actor Outbox. It works standalone and treats
the official ActivityPub plugin as optional (see docs/COMPATIBILITY.md).

This release also ships standalone content negotiation on the existing WordPress URL,
the Core Post → Article transformer, and an optional first-party Axismundi Media Library
attachment adapter. Shared media folders use a stable UUID collection route; the Actor
Outbox uses a stable, read-only REST collection URI.
When the official ActivityPub plugin is active, the standalone negotiator turns itself off
so a future adapter can preserve that plugin's established object ids.

The remote-object repository stores URI-keyed, rebuildable observations for later
administrator inspection and Activities integration. A complete Object embedded in a
verified inbound Create is cached after the Activity ledger commit when its identity and
attribution exactly match the enclosing Activity. This path performs no network request and
exposes no public mirror route. Administrators may fetch and inspect metadata-only remote
objects under Tools > Remote Objects, including tags, mentions, audience declarations,
attachment descriptors, extension properties, and the complete escaped JSON payload;
remote media is never hotlinked or downloaded.

Administrators may also probe a remote ActivityStreams Collection and its same-host first
page without persisting the Collection, fetching its item URLs, or downloading binaries.

== Changelog ==

= 0.0.32 =
* Serialize media embedded in an Article or Note with one bounded scalar media URL for
  Mastodon and Misskey interoperability, while standalone media objects retain their
  complete FEP-1311 Link ladder and human-page Link.

= 0.0.31 =
* Serialize trusted virtual image renditions without inventing a byte size, allowing
  Jetpack Photon WebP derivatives to remain fetchable Note attachments while preserving
  the original-file exclusion and fail-closed media boundary.

= 0.0.30 =
* Omit embedded media descriptors that have only an HTML object page and no fetchable
  media rendition, preventing broken Mastodon previews and silently dropped Misskey files.

= 0.0.29 =
* Replace Core user autocomplete with canonical Actor mentions, validate saved mention anchors against the Actor registry, and derive Article recipients and Mention tags.
* Add fallback-only source and deterministic view-model extension seams used by domain-owned
  objects without introducing a dependency on those domains.
* Register the dynamic object-view block and preserve plugin template, theme override, and
  user-saved template precedence for human-readable object pages.
* Reuse public Media Library descriptors for ordered Note attachments while keeping the
  relation authority in Media Library.

= 0.0.28 =
* Add Core Post audience and Mention authoring controls to the block editor and Quick Edit.
* Project matching Article to/cc members and Mention tags, while returning 404 and withholding
  ActivityStreams discovery for followers-only and mentioned-only anonymous requests.

= 0.0.27 =
* Cache complete, self-consistent Objects embedded in verified inbound Create activities
  without a second network request, and declare the FEP-044f QuoteRequest context for
  Accept and Reject payloads that embed the request.

= 0.0.26 =
* Add the rebuildable DB v4 quote-relation projection for FEP-044f, Misskey aliases, FEP-e232 links, consent-independent public counts, and verified authorization revocation mapping.

= 0.0.25 =
* Dereference Activities-issued QuoteAuthorization identities as the exact FEP-044f stamp:
  author, interacting Object, and interaction target remain URI references and are never
  embedded.
* Serve only the canonical root query URI, fail closed for unknown or malformed identities,
  and bypass permalink rewrite dependence.
* Return a privacy-minimal `410 Gone` Tombstone after revocation with `Cache-Control: no-store`,
  preserving identity while exposing neither protected Object.

= 0.0.24 =
* Supply Activities with an exact local Article, author Actor, and explicit Quote policy
  through a fail-closed object-domain provider seam.
* Keep QuoteRequest decisions out of the representation plugin: the provider performs no
  ledger write, Follow lookup, authorization issue, or network request.

= 0.0.23 =
* Add an explicit `Who can quote this post?` policy to the block-editor Federation
  panel and Posts Quick Edit, preserving an unset state rather than inventing consent.
* Project authored `anyone`, `followers`, and `me` choices as FEP-044f
  `interactionPolicy.canQuote.automaticApproval`, with renderer-owned JSON-LD context.
* Keep the declaration advisory: no policy value fabricates a QuoteAuthorization or
  changes the Activities-owned consent state.

= 0.0.22 =
* Advertise an OP-owned UUID Followers URI on local Actor documents and serve a
  count-only ActivityStreams Collection only when Actor policy permits public disclosure.
* Restrict the Actor transport seam to inbox, endpoints, and publicKey so a Bridge cannot
  replace representation-owned social collection identities.

= 0.0.21 =
* Advertise and serve count-only Object shares OrderedCollections backed by effective,
  distinct-Actor Announce rows without exposing Actor or Activity membership.
* Provide a fail-closed Announce visibility decision for public local projections and cached
  remote observations that explicitly address ActivityStreams Public.
* Resolve the original Object author for Announce delivery without a network request.

= 0.0.20 =
* List a folder's child folders alongside its media, so a remote peer can navigate a shared
  folder instead of landing on a dead end. A parent holding a thousand items across eight
  children previously reported zero and offered nothing to open, because the count was of
  direct members only and nothing pointed at the children.
* Order children first, then media. Media order comes from the folder-add time a child
  folder has no value for, so the two cannot interleave; the resulting order is total and
  page boundaries stay stable where they cut across both kinds.
* Serialize a child as a shallow reference — identity, name, count, and its human URL —
  never with its own items inlined, so depth costs one request per level rather than
  recursing a tree into one document.
* Hide a child that would not federate on its own. An internal, private, or gated child is
  absent from the listing and from totalItems: a name is a disclosure.

= 0.0.19 =
* Fix /media/folder/{uuid} returning 404 after updating to 0.0.18. The routes were
  registered but never reached the stored rewrite table, and the version counter meant to
  install them had already burned itself, so the only remedy was saving permalinks by
  hand. The routes now install whenever they are found missing, which also covers a
  ZIP-replace update that never fires the activation hook and a host that discards the
  write. Retries are limited to once an hour, and sites on plain permalinks are untouched.

= 0.0.18 =
* Project an eligible Media Library folder as a UUID-keyed OrderedCollection with bounded
  OrderedCollectionPage responses, direct folder membership, owner-Actor attribution, and
  anonymous visibility filtering.
* Serve `/media/folder/{uuid}` independently of object content negotiation, with a plain
  query fallback for environments where pretty rewrites are unavailable.
* Add a metadata-only remote Collection probe under Tools > Remote Objects. It fetches at
  most the root and its same-host first page, never item URLs or media binaries, and stores
  no Collection cache row.

= 0.0.17 =
* Advertise exactly one media Link, capped at 1024, for media embedded in an Article
  attachment or preview. Nothing in the wider fediverse selects between multiple versions
  today, so extra Links were payload with no consumer; 1024 is also WordPress's own `large`
  default. The standalone Attachment keeps the full ladder for Axismundi peers, which is the
  case multiple versions exist for.
* Keep the rendition builder shared and narrow only the policy per role, so identity, type,
  MIME, and the media-first ordering never drift and a role can never widen what is federated.

= 0.0.16 =
* Merge the attachment's human page and nested media Link into one FEP-1311 `url` Link array:
  media Links first with mediaType/width/height/size, the text/html page last, so a naive
  url[0] consumer of an Image reads media rather than the page.
* Advertise only the derivatives Media Library already generated. An image with no derivative
  now emits the HTML Link alone: the original file is never advertised, and the previous
  full-size fallback is gone. Video, audio, and documents keep their existing single-file
  policy while no transcoding substrate exists.
* Name embedded media with its alt text and omit `name` when alt is empty, while the
  standalone Attachment keeps its own title. Identity, type, mediaType, and the ordered url[]
  stay identical across the standalone, Article attachment, and preview.attachment roles.
* Resolve the HTML representation by mediaType so an ordered url[] cannot make an alternate
  Link or a collection url point at a media file.

= 0.0.15 =
* Establish the projected Post as the temporary global context while running the normal
  `the_content` pipeline, then restore every affected caller global exactly.
* Add regression coverage for the existing FEP-b2b8 preview image attachment mapping.

= 0.0.14 =
* Add shared Core Post sensitivity and content-warning metadata with controls in the
  block editor document settings and Posts Quick Edit.
* Project sensitive Articles with a boolean `sensitive` member and the warning as
  FEP-b2b8 `dcterms:subject`, while retaining the manual Excerpt as `summary`.

= 0.0.13 =
* Align Core Post Article output with FEP-b2b8: a dedicated positive HTML allowlist,
  manual Excerpt summary, More/Excerpt Note preview, Link-valued human URL, generator,
  sensitivity, representative image, and rendered full content.
* Project public Media Library reverse-index references as Article attachments. Embedded
  local images, video, audio, and files are listed for prefetch without guessing IDs from
  arbitrary external hotlinks.
* Add a public Attachment `usedIn` OrderedCollection that lists distinct public Articles
  only. Keep private usage and private/locked media fail-closed.

= 0.0.12 =
* Add DB v3 multi-reason object leases keyed by canonical object URI, reason, and reference.
  Expiry maintenance skips every observation with an active lease.
* Add a count-only public Object `likes` OrderedCollection backed by the Activities distinct-
  Actor total. Do not enumerate liker identities or cap `totalItems` to a serialized page.
* Keep Actor `liked` publication deferred until a user-facing privacy policy is defined;
  Activities exposes only an internal current-liked-object query in this release.

= 0.0.11 =
* Add an Activity-specific JSON-LD finalizer so transport adapters can emit the canonical
  ActivityStreams context without imposing object-only `url` and `attributedTo` members.
* Keep the immutable Activity ledger free of representation concerns while retaining
  Object Projections as the sole `@context` owner.

= 0.0.10 =
* Own the public Actor Outbox representation and neutral `axismundi/v1` read route through
  the collection-transformer registry, backed by Activities public-safe queries.
* Advertise Outbox from the Actor document without requiring the ActivityPub Bridge.
* Prevent transport filters from overriding Outbox identity; retain Inbox, sharedInbox, and
  publicKey as Bridge-supplied transport properties.

= 0.0.9 =
* Project public local Axismundi Actor URLs as Person, Application, Organization, Group,
  or Service JSON-LD through the same renderer and content-negotiation surface.
* Keep Actor representation ownership here while allowing the ActivityPub Bridge to add
  transport properties such as inbox, sharedInbox, and publicKey.
* Keep Inbox writes, Activity data, signatures, queues, and delivery outside this plugin.

= 0.0.8 =
* Emit an idempotent Core Post publish candidate from `wp_after_insert_post`, after terms
  and post meta are stored. Object Projections performs no Activity write or transport.
* Exclude drafts, password posts, pages, attachments, and Actor-less posts. Keep media
  uploads silent and defer Reply semantics until Axismundi Notes defines local Note identity.
* Fail closed when the official ActivityPub plugin owns post lifecycle publication; a
  compatibility adapter must explicitly transfer single-publisher ownership.

= 0.0.7 =
* Expand Remote Object details with structured Tags/Mentions, audience declarations,
  attachment metadata, extension properties, and complete escaped raw JSON. Remote media
  remains metadata-only and is never rendered or downloaded.
* Link Actor references to the cached Actors administrator record when available and fall
  back to the canonical remote URI when absent; do not fetch while rendering.
* Replace synchronous post-fetch Actor discovery with one deduplicated WP-Cron event for
  the primary attributedTo Actor only. Object storage no longer waits on Actor discovery,
  and Mention/audience members never cause request fan-out.

= 0.0.6 =
* Add Tools > Remote Objects: bounded public-HTTPS ActivityStreams fetch, conditional
  ETag/Last-Modified refresh, explicit signed-fetch-required errors, refresh/delete, and
  a text/metadata-only inspector that strips every media/embed element.
* Add schema v2 metadata retention (`expires_at`, `last_accessed_at`) with a filterable
  30-day sliding default, capped failure backoff, daily expiry maintenance, and manual
  expired-cache purge. No front-end render path performs a network request.
* Reserve metadata-only/preview/display/original cache levels while deferring every
  binary, hotlink, shared-blob, and shadow-attachment decision.

= 0.0.5 =
* Add the InnoDB `wp_ax_remote_objects` repository for URI-keyed remote ActivityStreams
  observations, with hash-indexed long URIs, normalized display fields, lossless bounded
  payload JSON, tri-state sensitivity, fetch validators, refresh state, and Tombstones.
* Keep invalid refresh input from overwriting the last good snapshot; verify the table,
  unique identity index, and storage engine before recording schema version 1.
* Keep fetching and public mirroring out of this increment. The repository performs no
  network request; bounded administrator discovery follows separately.

= 0.0.4 =
* Detect Axismundi Media Library in Independent mode and project public/unlisted,
  ungated attachments as Image, Video, Audio, or Document. Keep stable attachment ids,
  human media-page URLs, bounded image renditions, sensitivity, and canonical licenses.
* Keep the first-party boundary explicit: Media Library owns data and access services;
  Object Projections owns ActivityStreams mapping and never uses authenticated bypasses.
* Clarify that an Actor's primary feed is an Activities-owned outbox, not an Article
  archive. Article and Media views may later be optional filtered profile tabs.

= 0.0.3 =
* Add the browser-friendly `?activitypub` representation selector alongside standard
  Accept negotiation. It changes only retrieval format: the selector is never included
  in the emitted object id, and all existing visibility and single-negotiator gates apply.

= 0.0.2 =
* Add precise Accept negotiation for application/activity+json and ActivityStreams-
  profiled application/ld+json on existing WordPress object URLs. Bare application/json
  and unprofiled application/ld+json never hijack HTML; responses emit Vary, alternate
  Link, CORS, and nosniff headers, with GET/HEAD support.
* Add the Core Post → Article transformer: stable /?p={ID} id, human permalink url,
  public Actor attribution, rendered HTML content, manual summary, and timestamps.
  Draft, private, password-protected, or Actor-less posts fail closed.
* Disable standalone negotiation whenever the official ActivityPub plugin is active,
  while leaving registry and renderer APIs available to the future compatibility adapter.

= 0.0.1 =
* Phase 0 — lock the projection contract and scaffold the plugin: object vs actor
  projections, stable object id vs human url, the renderer as the sole @context owner,
  transformers as pure projections, and the single-negotiator + legacy-id-preserving
  compatibility contract for the official ActivityPub plugin (docs/).
* Phase 1 — the transformer registry and renderer, with no table and no route. Public API:
  axismundi_op_register_object_transformer(), axismundi_op_register_collection_transformer(),
  axismundi_op_transform_object(), axismundi_op_transform_collection(). The renderer
  validates the four required members, forces the emitted id to equal the declared object
  URI, owns the JSON-LD @context, sanitizes HTML members, and keeps "no transformer", a
  transformer error, and "not public" as three distinct outcomes.
