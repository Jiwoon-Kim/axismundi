# ActivityStreams Storage Boundaries

**Status:** working proposal, 2026-10-15. This records a storage
classification for local WordPress-backed Objects. It does not introduce a
schema or change a federation policy.

The question is not whether an ActivityStreams property can physically fit in
`wp_postmeta`. The question is what kind of fact it is. One Object response is
assembled from Core sources, local authoring settings, relationship projections,
and protocol history. Those remain different sources even when the renderer
eventually emits adjacent JSON properties.

## Decision Rule

| Store | Use it when | Do not use it when |
|---|---|---|
| Core post fields and Core APIs | WordPress already owns the canonical source fact. | An AS2 property merely resembles a Core column. |
| `wp_postmeta` | One local post owns one small, typed authoring or policy selection. It is edited with that post and has no independent identity. | The value is a list, a graph edge, requires ordering/provenance, or is queried across local and remote Objects. |
| Taxonomy and term meta | A reusable local classification is shared by many posts and has a local archive/editorial identity. | Membership itself is an actor collection, an audience, a reply graph, or a remote source of truth. |
| Axismundi relation table | An Object has zero or more targets, the target may be local or remote, or the edge has origin, order, consent, authorization, visibility, or query requirements. | The relationship is only a Core source fact such as a featured-image attachment ID. |
| Axismundi entity/state table | The record has its own lifecycle, canonical URI, cache/refresh state, retention needs, or is a query projection across Objects. | It is merely a post-specific edit setting. |
| Activity ledger | It is an immutable event: who acted, on what, when, delivery direction, payload, and effective status. | The Object must still answer correctly after activity retention, pruning, or the Activities plugin being unavailable. |
| Renderer / derived projection | The value is deterministically assembled from one of the above at read time. | Rendering would hide a missing durable source fact. |

`meta_value` arrays are especially suspect: they are acceptable as a temporary
editor input, but they are not the durable graph representation of a many-target
AS2 relationship.

## When An Activity Type Gets Its Own Table

The Activity ledger holds twenty-two types in one table with type-specific
indexes, which is `wp_posts` with `post_type` by another name. That analogy
carries its warning with it: Core's one table also holds menus, blocks,
templates and revisions, and every query since has paid for it with a
`post_type` condition. Three splits have already been argued one at a time in
this document, so the rule that decided them is written here instead of being
rediscovered a fourth time.

> Split when the row's **shape** differs or its **lifetime** differs. Volume
> alone is an index problem.

| Types | Shape | Lifetime | Result |
|---|---|---|---|
| `Announce`, `Like`, `Follow`, `Undo`, … | audience, delivery, payload, all used | event, compactible | one ledger, split by index |
| `View`, `Listen`, `Read` | registry row when published; a relation row always | derivable, recountable | relation projection |
| `Question` | option rows, tallies, trash and restore | durable, never compacted | own table |
| `Arrive`, `Travel` | same as an Announce: momentary, addressed | event, compactible | the ledger |

The last row is what the rule is for. `Arrive` and `Travel` sit beside
`Question` in the AS2 type tree and invite the same treatment, but their rows
look and live like an `Announce`. The shape decides, not the taxonomy.

This does not predetermine the storage for a future calendar-backed Travel
product. The Calendar plugin already keeps a durable Event source (`ax_event`
plus schedule data) and projects ActivityStreams Event and JSCalendar as
reads; its Join, Accept, Reject, and Undo records remain ledger facts. A
momentary, event-first Travel can remain a ledger Activity. Travel that needs
authoring, editing, scheduling, restoration, and calendar display needs an
equally durable Calendar-owned source. Do not choose that source's CPT or table
shape until that product contract exists.

One consequence of splitting is worth stating before it is discovered. AS2's
own example announces an `Arrive`, so an Activity can be another Activity's
object, and anything a peer may reference needs an Activity URI. A row in a
specialised table has none, which is exactly right while that family is never
emitted: nothing can reference what was never published. If outbound `View`
is ever turned on, the emitted activity is a ledger activity and the
specialised row becomes its derived index -- never a second authority for the
same fact.

Measured while writing this: the ledger's accepted types include neither
`Arrive` nor `Travel`, so an announcement of one is storable only as a URI
reference and a direct `Arrive` is refused twice over -- unregistered type, and
the unconditional `object` requirement that the IntransitiveActivity fix
already has to lift.

## Who Decides To Federate, And Which End Authors First

Two questions kept returning in different clothes: whether the bridge decides
what federates, and whether an object is created before or after its activity.
Both have answers that follow from what is already measurable.

### Emission is an authoring decision; delivery is a transport decision

```text
(a) record the attention locally                      product / Activities
(b) create an addressed, publishable activity         authoring / Activities
(c) deliver an addressed activity to a peer           bridge
```

The switch for something like outbound `View` belongs at (b). Put it at (c) and
the system builds addressed activities and suppresses them in transport, which
leaves deliverable records lying in wait: the day transport policy changes they
begin to flow, publishing a history nobody offered. The operations policy
forbids exactly that backfill, and a suppression switch at (c) is how it would
happen by accident.

The code already works this way. Delivery is driven by audience, and the
bridge's type checks are routing decisions rather than permissions: a public
`Create` or `Update` whose object addresses a known Group takes a direct
submission path (`axismundi-activitypub-bridge/includes/transport.php:149`).
What the bridge does own is per-peer policy -- retries and backoff,
shared-inbox selection, blocked instances, and what a rejection means.

### The source's semantics choose the authoring surface; identity constrains order

`C2S` here means protocol-native authoring: an acting Actor creates an AS2
Activity and the domain source is filled as its consequence. It does **not**
mean every frontend HTTP write that happens to result in a `Create` later.
The first question is therefore not where an id comes from, but whether the
source can represent the AS2 contract without discarding facts it owns.

Core `wp_posts` has its own editorial language: post status, categories and
tags, revisions, hierarchy, templates, block content, attachment state, and
other Core behaviours. `Article` is a useful projection of that source, but it
is not a lossless replacement for it. An Activity editor cannot faithfully
author all of those Core facts, so a frontend `Create` must not become an
alternate write path for Core posts merely because the registry can mint an id.
Core posts remain WordPress-first and the block editor remains their authoring
surface. The later `Create` is a projection/publication fact.

The inverse is true for a Note-like source. It was designed around AS2
properties rather than adapted from a broader editorial substrate, so its
frontend editor can create the Activity and populate the native source from
the same declared property set. That is why C2S is appropriate for notes,
quotes, Questions, and note replies in a Group context.

Identity still constrains the safe ordering after that semantic decision. A
client-to-server flow needs an id before the source row exists; a UUID-owning
source can issue it today, while a `?p={ID}` object cannot.

| Source / type | Source semantics | Identity today | Authoring order | Surface |
|---|---|---|---|---|
| note, quote, Question | AS2-native property contract | table-owned UUID | activity-first, today | frontend app C2S |
| Core post / Article, attachment | WordPress editorial and media semantics; AS2 is a projection | post ID (`?p={ID}`) | WordPress-first | block editor / Core media UI |
| Event | JSCalendar domain model and calendar ACL; AS2 is one representation | post ID, via the same helper | WordPress-first today | calendar-specific editor, undecided |
| Group topic / Article | Core long-form Article plus independent Group admission | post ID | WordPress-first | block editor, then Group submission |
| reply in a Group's context | AS2-native note contract | table-owned UUID | activity-first, today | frontend app C2S |

`axismundi_op_post_object_uri()` returns `?p={ID}`
(`axismundi-object-projections/includes/post-article.php:107`), and the
calendar's event URI calls straight into it (`projection.php:49`), so a
post-backed object has no identity until it is stored. A note does: its UUID
lives in `wp_ax_notes` and resolves by table lookup. The shared registry can
eventually decouple a post's identity from its post ID, but that only removes
the ordering constraint; it does not turn a lossy AS2 projection into a
complete Core-post authoring model.

Activity-first adds a requirement the current order never had. If the activity
exists and the row does not, an activity references an object that was never
stored, so creation has to be atomic or the activity stays unpublished until
storage succeeds. `expected_version` in the publish command is the precedent to
follow rather than a new mechanism to invent.

The division of roles gets cleaner, not messier: the signed-in `wp_user` fills
`post_author`, the acting Actor fills `attributedTo`, and neither is derived
from the other.

### Group topics and the conversation under them

A topic is an Article addressed to a Group, and posting into a remote Group
does not require protocol-native C2S object authoring: the Article is authored
locally through Core, its audience names the Group, and the Group announces it.
The Group submission is an admission command over an existing object, not a
second editor that reconstructs an Article from an Activity.
`wp_ax_forum_entries` is an admission record rather than an object store -- unique on
`(group_identity_id, object_uri_hash)`, with `source_post_id` filled for a
local object and empty for a remote one, beside the accept and announce
activity URIs. Remoteness never forced the order; identity ownership did.

So topics are written in the block editor, where long-form belongs, and the
replies beneath them are notes written in the frontend app. One long-form
editor, not two.

Events are the open case, and for a reason beyond identity: an Event has two
representations, AS2 and JSCalendar, and a JMAP-style editor would be a third
write path beside the block editor and the client-to-server one. Three paths
must still converge on one identity and one visibility model, and the calendar
already carries its own permission layer in `wp_ax_cal_acl`. Where that layer
meets AS2 audience is the decision that editor would force, and it is not made
here.

## Source Store Versus Shared Registry

Some Object properties need one common query surface even though their source
does not become common. The useful distinction is:

| Layer | Local Object | Remote Object | Purpose |
|---|---|---|---|
| Source record | `wp_posts`, Core post meta/APIs, and narrowly owned authoring meta | `wp_ax_remote_objects` payload/cache | Preserves the source's native semantics and refresh/edit lifecycle. |
| Shared Object registry/index | One row referring to the local post and its canonical Object URI | One row referring to the remote cache row and canonical Object URI | Cross-origin lookup, graph edges, collections, actor/object queries, and rebuildable listing projections. |

This is a good place for common *indexable identity facts*: canonical Object
URI/hash, origin (`local` or `remote`), source reference, Object type/status,
and an optional resolved Actor identity reference. It is not a place to copy
local `post_content`, editorial dates, arbitrary raw JSON, or every AS2 field.
Those remain in their source stores and are rendered or cached through explicit
projections.

### Registry Scope: Durable And Queryable Objects

The proposed `wp_ax_objects` registry is not only a registry for WordPress
content. Its durable/queryable Object scope includes
`Person`/`Group`/`Organization`/`Service` Actors, post-backed Objects, remote
cached Objects, and independently addressed Collections. Its `source_kind`
and `source_id` select the authoritative source; the registry does not copy an
Actor profile, post body, or remote payload.

Although an AS2 Activity is formally an Object, the Activity ledger remains
the identity registry for Activity events. Do not register every `Like`,
`Undo`, `Follow`, delivery retry, or other high-volume/retention-bounded event
in `wp_ax_objects`: that would couple interaction-ledger compaction to durable
Object identity and make an Article share an operational index with every
Like. An Activity is registered as an Object only through an explicit future
profile that needs it to have durable, independently queried Object semantics;
there is no automatic Activity-to-Object row.

This still prevents products from separately solving Object URI resolution. A
mention can resolve to the same Actor Object ID that an Activity's `actor` edge
uses; `inReplyTo`, `target`, `origin`, and `instrument` can point to an Object
registry row when their target is an Object. An Activity target, such as
`Undo.object`, continues to resolve by Activity URI in `wp_ax_activities`.
An unresolved URI remains an edge with a URI/hash fallback and is attached to
a registry row only after its Object is discovered or cached.

`Link` and `Mention` remain outside this table because AS2 defines `Link` as
disjoint from `Object`. A Link occurrence belongs on the relation edge that
uses it (`tag`, `attachment`, `preview`, and so on), with its `href`, Link
type, display metadata, position, and provenance. If Link ever requires an
independent cache or lifecycle, it needs a separate Link registry rather than
an incorrectly named Object row.

### What the Registry Simplifies

Once every visible Object has one registry `object_id`, extension modules no
longer need a separate local-post path and remote-cache path. Their tables can
refer to one ID and let the registry find the source:

```text
wp_ax_objects              object_id -> origin + source reference + canonical URI
wp_ax_object_tag_relations object_id -> tag descriptor / optional local term
wp_ax_object_relations     object_id -> target object / relation metadata
wp_ax_object_audience      object_id -> recipient / addressing role
wp_ax_object_collections   object_id -> collection / position / membership state
module-owned extension     object_id -> typed module facts
```

That simplifies indexing, cleanup, and joins. A cache purge, local post delete,
or source refresh can locate every rebuildable projection by one stable registry
reference. It also makes a common Object card/list query independent of whether
the source was local or remote.

It does **not** make `wp_postmeta` obsolete. Local editor-owned inputs stay
there until they are normalized into a shared projection; remote unrecognized
extensions stay in the received payload. A registry-validated property table
may materialize registered scalar or language-map properties, but it must not
be the universal primary store for every value. `object_id + property_name +
property_value` alone would turn typed relationships, indexes, ordering, and
authorization into an unbounded EAV schema. Route registered relations and
ordered members to their typed tables after naming the query, lifecycle, and
source-of-truth contract they serve.

### Activity Ledger Deduplication Requires Revisions

The registry's strongest operational use is to stop storing a full Object
snapshot in every inbound and outbound Activity row. A Create, Update, Announce,
delivery retry, and inbox observation can all refer to the same canonical
`object_id`.

The registry alone is insufficient, however. Resolving a 2026 Create against
the Object's current representation after a 2027 edit would rewrite history.
The deduplication unit must therefore be an immutable representation revision:

```text
wp_ax_objects
  object_id, canonical_uri/hash, origin, source reference, current_revision_id

wp_ax_object_revisions
  revision_id, object_id, representation_hash, representation_json or blob ref,
  observed_at, source_kind, immutable, expires_at, retention_reason

wp_ax_activities
  activity identity/type, actor reference, object_id, object_revision_id,
  audience/direction/signature-or-delivery evidence, event timestamps
```

For a local Object, a revision records the exact federated representation used
by an outbound Create or Update; the mutable post remains its present source.
For a remote Object, an inbound embedded Object or direct fetch may create or
reuse a revision by representation hash; the remote cache points to the newest
usable revision. Repeated deliveries and retries then reference one revision
instead of copying its object JSON.

An Activity still needs an immutable event envelope. It cannot be reduced to
only `object_id`: activity type, actor, audience, direction, timestamps,
verification/delivery evidence, and a hash of the received or emitted activity
remain event facts. Raw activity payload retention can be shorter or moved to a
content-addressed audit store, but dropping it is a server operating-policy
decision, not merely a normalization optimization.

Revisions need an explicit retention policy rather than permanent storage. A
revision is retained while an active delivery/retry, moderation case, object
lease, or configured audit window requires its exact representation. On expiry,
delete the representation body/blob but retain the Activity envelope,
`representation_hash`, expiry timestamp, and a reason such as
`representation_expired`. Historical activity can then remain auditable without
pretending its former Object body is still available. Tombstone/minimal lifecycle
facts may have a longer policy than rich content, but the duration is an
operator decision to document before implementing the tables.

### Actor Profiles Are a Separate Revision Case

Actor profiles are ActivityStreams Objects, but their default job is to express
the *current* identity/profile. Do not put every profile fetch or local edit
into the generic Object revision store indefinitely. The Actors identity/actor
tables should retain the current normalized profile and a profile hash; create a
short-lived immutable profile representation only when an inbound or outbound
`Update` needs retry, moderation, forensic review, or an audit window.

Cryptographic and endpoint observations require their own retention rules:
public-key identifier/fingerprint, validity/observation interval, inbox and
shared-inbox endpoint history, and signature-verification evidence are not
ordinary display-profile revisions. Never retain replaced private key material
as a profile revision. An Actor Tombstone or disabled state may keep minimal
identity/lifecycle evidence after rich profile data expires.

An observed remote Object can name an Actor before that Actor is cached. The
registry therefore needs to retain the unresolved `attributed_to_uri`/hash (or
obtain it through the remote cache) and permit a nullable resolved
`attributed_to_identity_id`; it must not require actor discovery before Object
storage. When discovery later succeeds, it fills the identity reference without
rewriting the observed URI.

The existing `wp_ax_object_index` is a partial listing projection and
`wp_ax_remote_objects` is the remote source/cache. A future unified registry
must either replace that index deliberately or make their responsibilities
explicit; it must not become a third, silently divergent copy of Object state.

## Current Implementation Facts

| Current source | What it holds | Boundary observation |
|---|---|---|
| `wp_posts` and Core attachment APIs | local text, title, excerpt, MIME family, dates, attachment file/metadata, featured image source | Correct substrate for the planned shared `CorePostSource`; attachment remains a Core `post_type`. |
| OP post meta | `_ax_op_sensitive`, `_ax_op_content_warning`, `_ax_op_quote_policy`, `_ax_op_visibility`, `_ax_op_mentions`, `_ax_op_language` | The scalar settings fit post meta. `_ax_op_mentions` is an editor input, while the actual query projection is already a relation table. |
| `ax_hashtag` taxonomy | local/observed hashtag vocabulary, local post membership, canonicalized term key | Appropriate for this site's reusable tag archive; it is not an Actor collection. |
| `wp_ax_object_mentions` | Object-to-Actor URI edges and origin (`explicit`, `inline`, `remote`) | Correct relationship-table shape. |
| `wp_ax_object_relations` | Object-to-Object quote edges plus evidence, consent and authorization | Correct relationship-table shape. |
| `wp_ax_remote_objects` | remote Object cache, scalar lookup fields, payload and refresh/cache state | Correct entity/cache shape; its `attributed_to_uri` is remote observation, not a local post setting. |
| `wp_ax_activities` | Create/Update/Delete and other activity payload/history/direction/audience | Currently also supplies local `attributedTo`; that makes durable Object attribution depend on the ledger and is the boundary to remove. |

## Proposed Property Classification

`AS2` below means the ActivityStreams Vocabulary property. “No new generic
store” means a type-owning module may later define a narrow contract; it does
not authorize an untyped global meta key.

| AS2 property or family | Local WordPress-backed Object | Remote Object | Store / owner |
|---|---|---|---|
| `id`, `url`, `type` | Derive from routing plus `WP_Post.ID`, `post_type`, MIME classification. | Cache canonical URI/type and received human URL. | Core adapter / remote-object cache; no post meta. |
| `name`, `content`, `summary` | `post_title`, raw `post_content`, `post_excerpt`; transformation/sanitization belongs after source reading. | Cache scalar received representation plus payload. | Core adapter / remote cache. |
| `published`, `updated` | Core GMT dates, subject to a later representation contract. | Cached remote timestamps. | Core adapter / remote cache. |
| `mediaType`, image dimensions, rendition links | Read Core attachment metadata and a media-policy resolver. | Preserve payload and cache selected scalars when needed. | Core APIs plus media policy; no duplicate raw Core-meta copy. |
| `attributedTo` | **Proposed:** one `_ax_object_attributed_to_identity_id` post-meta value referencing the Actors identity ledger. Its URI is resolved at render time. | Preserve URI/hash in `wp_ax_remote_objects`; resolve/cache the Actor independently. | Post meta for local selection; remote cache for observation; identity table for referenced entity. |
| `sensitive`, content warning, quote preference, language | Existing typed scalar OP meta remain suitable authoring settings. | Preserve received payload/scalars; do not apply local authoring meta. | Post meta / remote cache. |
| Visibility intent versus `to`/`cc`/`bto`/`bcc`/`audience` | A compact local visibility policy may remain scalar post meta. Resolved recipients and delivery audience are not the same fact. | Preserve received addressing in payload; index only explicit query needs. | Policy plus recipient/audience relation tables and activity delivery history; not an authoritative post-meta URI array. |
| `tag` as local hashtag/topic | Use a local taxonomy when this site owns a reusable classification and archive. | Map to a local term as an explicit shared vocabulary/index projection, while retaining the received `tag` payload as the remote source. | Taxonomy/term meta; current `ax_hashtag` plus `wp_ax_remote_object_hashtags` is this case. |
| `tag` as `Mention` | Explicit editor input may temporarily enter through meta, but the durable result is edges. | Extract received Mention tags to edges without synchronously fetching Actors. | `wp_ax_object_mentions` or successor relation table. |
| other `tag` members: `Link`, emoji, custom types, unknown extensions | Do not silently make them terms. A module may define a local taxonomy-backed kind only when it owns its vocabulary and archive semantics. | Preserve the received descriptor and index a kind only when a concrete query needs it. | Generic tag-relation projection, with optional `term_taxonomy_id`; raw payload remains the lossless source. |
| `attachment`, `image`, `icon`, `preview` | Parse links/attachment IDs into relation/representation projections; `_thumbnail_id` remains a Core source for the singular featured image. | Preserve payload; index only retrieval-critical edges. | Core resolver for featured image; relation table for general/multiple edges. |
| `inReplyTo`, `context`, `partOf`, `replies` | Do not use `post_parent`. Store durable object/collection edges once the local authoring contract exists. `replies` itself is a derived collection/query. | Cache direct URI scalars where useful and retain payload; graph/index is separate. | Object-relation and collection-membership tables; renderer for collection pages/counts. |
| `oneOf`, `anyOf`, `closed` | Question-specific state; options are ordered, multiple, and independently addressed. | Preserve received payload. | Question module tables, not generic meta arrays. |
| `startTime`, `endTime`, `duration`, coordinates, radius | Type-specific Event/Place/Media facts. A single scalar can be module-owned post meta; a reusable Place/independent object needs its own entity and edges. | Cache typed scalar fields only if queried. | Owning type module; no generic OP meta until that contract exists. |
| `location`, `origin`, `target`, `instrument`, `result` | Usually a typed relation rather than an unqualified string. | Preserve payload and selectively index. | Relation table with `relation_type`, target URI/hash, and edge metadata. |
| `generator` | Site/application configuration. | Received payload fact. | Site option/configuration; remote payload. |
| Collection paging: `current`, `first`, `last`, `next`, `prev`, `items`, `orderedItems`, `totalItems`, `startIndex` | Derived from a collection query and its membership/order. | Cached remote collection page only when explicitly fetched. | Dedicated collection/membership tables and renderer; never taxonomy membership alone. |
| `formerType`, `deleted` / Tombstone | Requires an operator policy for local retention, withdrawn federation state, and HTTP representation. | Cache Tombstone payload/status. | Lifecycle/entity state table plus activity ledger; defer schema until policy exists. |
| `accuracy`, `altitude`, `latitude`, `longitude`, `units`, `width`, `height`, `href`, `hreflang`, `rel` | Representation-specific or typed-module facts. | Preserve received representation. | Derived attachment/link representation or typed owner; no generic post meta. |
| `subject`, `relationship`, `describes` | Semantic graph edges, not a WordPress category by default. | Preserve/index only a concrete query case. | Relation table. |

The AS2 vocabulary also permits extension properties. `contentMap`, `nameMap`,
and `summaryMap` are not Vocabulary properties. A single local language override
can influence one rendered representation, but genuine multilingual maps need a
translation-owner model with per-language rows, not serialized post meta.

## Shape of the Proposed Local Path

```mermaid
flowchart LR
  P["wp_posts + Core postmeta<br/>canonical local content facts"] --> S["CorePostSource<br/>normalized source facts"]
  Q["wp_ax_remote_objects<br/>received cache/payload"] --> X["shared Object registry/index<br/>URI, origin, source ref, type/status"]
  S --> X
  M["post meta<br/>scalar authoring/policy<br/>attribution identity selection"] --> R["Object renderer"]
  T["taxonomy<br/>local reusable classifications"] --> R
  E["Object relation tables<br/>mentions, replies, attachments, contexts"] --> R
  I["wp_ax_identities<br/>canonical Actor identity"] --> R
  X --> E
  S --> R
  Q --> R
  R --> O["AS2 Object representation"]

  A["wp_ax_activities<br/>Create / Update / Delete / delivery history"] -. records and distributes .-> O
  A -. never sole Object truth .-> R
```

### Durable Intransitive Activities Are A Second Retention Class

Question is an `IntransitiveActivity`, and the vote classifier, the audience
resolver and the C2S path that serve it already live in Activities. Ownership
belongs there. What follows from that is a change to the plugin's own contract,
which has described itself as the store for event-first, retention-bounded
facts.

Activities now owns two retention classes:

```text
event ledger        Like, Follow, Undo, Announce, delivery history
                    compactible, snapshot-droppable, bounded
durable aggregate   Question and its options, tallies, lifecycle
                    never compacted; a deletion is a product decision
```

Measured when this was written: Activities carries no retention, compaction or
pruning code at all, so nothing is broken today. That is exactly why the
contract is recorded now -- the first compaction job written against this
plugin must already know that one of its tables is not a log.

Three constraints hold the rest of it together.

- The ledger's primary key is `identities.id`. A durable store that mints its
  own URI identity would make a third identity ledger beside `wp_ax_identities`
  and a future object slice, which is the thing the registry gate just decided
  against.
- Scope is durable intransitive aggregates, not every `IntransitiveActivity`.
  AS2 puts exactly three types under `IntransitiveActivity` -- `Arrive`,
  `Question` and `Travel` -- so the siblings this scope excludes are `Arrive`
  and `Travel`, both momentary events that stay in the event ledger until one
  of them acquires authoring, editing and restore semantics of its own.
  `Leave` is not one of them: it is a direct Activity and carries an object.
  A table named for the AS2 supertype would invite the other two in for the
  wrong reason, so the name states the retention model instead.
- Local and remote rows share the table and not the authority. A local Question
  owns its tally; a remote Question's tally is an observation with a time and a
  source, never a number this site computes.

The canonical URI does not move with the container. A Note's identity already
lives in `wp_ax_notes.local_uuid` and resolves by table lookup rather than by
post query, so an existing Question keeps the URI it published while new
objects mint a type-neutral one. A URI that names its owning plugin is the
mistake this avoids repeating.

Note's Question becomes legacy on this decision. Nothing is migrated and no
compatibility is kept: no other plugin reads `wp_ax_questions`,
`wp_ax_question_options` or `wp_ax_poll_votes`, and the whole surface is ten
files inside the Note plugin.

The order is disconnect, then delete. Note's authoring entry point closes when
the Activities editor lands, so the legacy row count stops growing and the
registry never sees two writers for one kind, while the implementation stays
readable as the reference it is. A frozen path is marked frozen in the code and
not only here: breakage is fixed, nothing is added.

What the new ledger inherits from it is not the storage shape but the solved
problems. A Question carries a short `content`, a content warning expressed as
`summary` with `sensitive`, attachments, audience and tags, and it renders as
the same feed card an object does -- because `IntransitiveActivity` inherits
Object's properties and excludes only `object`. The ledger's columns therefore
come from the registry's effective property set,

```text
effective_properties(Question) =
  Object properties + Activity properties - object + oneOf, anyOf, closed
```

rather than from improvisation, and the content-warning text is treated as body
class with `content`, consistent with the eviction layering above.

What does not carry across: the disposable draft post the local vote path
creates, the `ax_note` URI naming, and every lifecycle assumption that came
from being post-backed.

The vote path simplifies as a consequence rather than as a goal: authoring into
the ledger directly removes the disposable draft post that local voting creates
today, and `VoteIntent -> Create(Note)` becomes a direct path.

### The Question Ledger, And Why Options Need No Identity

Question moves to Activities whole, and Note's version is removed rather than
frozen. Keeping it would leave three tables -- `wp_ax_questions`,
`wp_ax_question_options`, `wp_ax_poll_votes` -- whose contract is `note_post_id`
and the `ax_note` lifecycle, which makes Note the hidden owner of Questions
after the move and forces new code to keep the old shape in view. There is
nothing to migrate and no compatibility to keep, so the cutover is hard and the
old code survives as design reference, not as a runtime layer. The new tables
must not inherit the old ones physically either; adopting `wp_ax_questions` as
it stands would revive the `note_post_id` contract it was built around.

**Options carry no public identity.** This was checked against live payloads
rather than assumed: a Mastodon poll and a Misskey poll fetched on 2026-10-16
both emit `oneOf`/`anyOf` members as `{ "type": "Note", "name": ..., "replies":
{ "totalItems": n } }` with no `id`. Mastodon's own model carries it the same
way -- `polls.options` is a string array, `cached_tallies` a parallel integer
array, and `poll_votes.choice` an integer index, tallied as
`cached_tallies[choice] += 1`. An option URI or UUID would be a term nobody
reads.

So the identifier is the position, and the storage follows the wire:

```text
questions
  identity_id            shared identity, as every vertical slice uses
  mode                   oneOf | anyOf
  options                JSON array of names, in document order
  option_counts          JSON array of tallies, parallel to options
  votes_count            denormalized cache
  voters_count           distinct actors
  end_time, closed_at, updated_at
  lock_version           optimistic lock for concurrent tallying

question_votes
  question_identity_id
  voter_actor_uri        canonical, and the authority
  voter_actor_uri_hash
  voter_identity_id      nullable shadow, filled once the actor is known
  choice_position        nullable -- an ignored vote may name no valid option
  vote_activity_uri
  status                 active | ignored | undone
  ignore_reason          nullable; a diagnosis, not a silent drop
  origin                 local | inbound
```

The voter follows the same rule as every other actor reference here: the URI is
canonical and the identity is a nullable accelerator, because an inbound vote
can arrive before its actor is cached and must still be stored.

`question_votes` is the authority and the counts are a cache, exactly as
`wp_posts.comment_count` relates to comments: recountable, updated inside the
same transaction as the vote it reflects, and protected by `lock_version`
because two votes arriving together would otherwise lose one increment.
Normalising options into rows is the step to take if per-option analysis or
partial updates ever need it, and not before.

Two counts must never merge. A Question has ordinary replies and it has votes,
and FEP-9967's vote is a reply in shape -- so `reply_count` and `votes_count`
are separate fields answering separate questions.

#### The wire, and the envelope the protocol still wants

FEP-9967 fixes the shape: a Question is published inside `Create`, a vote is a
`Create` wrapping a `Note` with no `content`, carrying `name` equal to an option
name, `inReplyTo` the Question, and addressed to the Question's author, and
results are redistributed as `Update` of the Question. Multiple choice is one
`Create` per option.

That envelope is in tension with AS2, where `Question` is an
`IntransitiveActivity` and has no `object` of its own, and the tension must not
be resolved by letting a validator wave Questions through. A blanket exception
is how the rule stops meaning anything. Instead the profile is explicit --
`fep-9967-poll` -- and says what it permits: a Question inside `Create.object`
and `Update.object`, a vote Note with `name` and without `content`, option names
unique within one Question, and the two shapes an `Update` may take.

Those two shapes matter, and an earlier draft of this section got them wrong by
allowing only the first. FEP-9967 states that a poll's type and its options
"might be changed at any time", and that the author then MUST reset it: empty
every option's `replies` collection, set `votersCount` to zero, and publish an
`Update`. A validator that required an Update to differ only in tallies would
reject the reset the specification demands.

```text
Update changes tallies and timestamps only
  -> the vote projection stands; validate the tallies against it

Update changes mode or options
  -> every tally and votersCount must be zero in that Update
  -> the existing vote projection is reset rather than recounted
```

Pure AS2 validation stays unchanged beside both.

Authority splits by origin. For a local Question this instance validates votes
and owns the tally. For a remote one, a local actor's vote is recorded in the
ledger, but the displayed tally is an observation taken from the author's
`Update`, never a number computed here. Votes that fail validation -- a closed
poll, a duplicate, an unknown option name -- keep their raw activity and sit in
the projection as `ignored` rather than disappearing.

And votes are not feed material. They look like replies and must not surface as
them, which is the practical reason the projection exists at all.

### Media Authority Belongs To The Source, Not To The Type

The unified engine reads one source layer and specialises into profiles, so it
has to carry a distinction `post-article.php` never had to name: who is
authoritative for an object's attachments.

```text
CorePostSource        the body is authoritative   -> provenance derived-from-body
native domain source  an explicit list exists     -> provenance authored
```

A core post stores its media as block attributes -- `attrs.id` on the six core
media blocks, plus the featured image -- so the engine reads ids, not markup,
and the list is a projection of `post_content`. Promoting that list to authored
data would let it drift: nothing in Core guarantees a stored list moves with the
body across revisions, programmatic updates and REST writes. A note-like source
that records its attachments explicitly is the opposite case, and its list is
the authority.

Both can hold for one object. The engine resolves that, rather than assuming it
cannot happen:

- the published `attachment` property takes the authored list when one exists;
- the relation graph keeps the union, each row carrying its own provenance, so
  an inline image that the authored list omits is still a recorded reference;
- a derived row never overwrites an authored row, and a body that stops
  mentioning a file never deletes an authored one;
- re-deriving on every save reconciles rather than replaces, or `first_seen_at`
  resets on each edit and the lifetime columns mean nothing.

Two further rules the profiles inherit rather than reinvent:

- `post_type=attachment` is the Object itself, never a relation from itself.
- Media the body references without an attachment id -- a classic-editor or
  pasted `<img>` -- has no truthful `mediaType` or dimensions, so it stays out
  of the published `attachment` property. Once identities exist it may still be
  recorded as an unresolved identity with `body-url` provenance: keeping the
  reference in the graph and publishing a descriptor are separate decisions.

The proposed local attribution path is deliberately direct:

```text
editor chooses authorized Actor identity
  -> post meta stores identity_id
  -> Core/Object projection resolves canonical Actor URI
  -> Object.attributedTo
  -> Create/Update command records the actor and delivery event
```

That lets a scheduled post render its attribution without an active user session
or a retained Create row. `post_author` and `_edit_last` remain WordPress
editorial facts; changing either must not silently reassign `attributedTo`.

The Actors plugin's per-user acting-actor preference is a compose/UI default,
not the post's source of truth and not an authorization grant. A user may
author a post through an Organization, transfer the WordPress `post_author` to
another user, and retain the Organization attribution. On a later edit, the
editor's current authority is checked against the stored attributed Actor: an
authorized editor may issue the corresponding Update, while an unauthorized
editor can make the local WordPress edit without silently emitting an Update
under an Actor they cannot represent. Reassignment of `attributedTo` is an
explicit post/source command, never an incidental result of an author transfer
or a global UI switch.

## Tag Is a Relation Family, Not a Taxonomy Alias

ActivityStreams `tag` may contain `Hashtag`, `Mention`, `Link`, emoji-shaped
extensions, or other extension types. A local term is therefore an *optional
projection target*, not the universal representation of `tag`.

The current hashtag implementation already indexes remote observations and
merges them with local taxonomy-backed posts in the dedicated archive block.
It cannot use the default WordPress taxonomy query as a single mixed query,
because the remote Object is not a `WP_Post`; it joins
`wp_ax_remote_object_hashtags` to `wp_ax_remote_objects` and merges the two
result sets. This is functional today, but pagination and ranking are still
two-source behavior.

Once a shared Object registry exists, the generalized shape should be an
Axismundi table such as `wp_ax_object_tag_relations`, not a reuse of
`wp_term_relationships`:

```text
object_id                 shared Object registry ID
tag_kind                  hashtag | mention | link | emoji | extension type
term_taxonomy_id          nullable; only when mapped to local vocabulary
target_uri/hash           nullable; Mention/Link/remote tag target
name / observed_name      descriptor/display evidence
source_href               nullable received link evidence
origin                    local-authored | remote-observed | derived
position                  nullable when source ordering matters
UNIQUE(object_id, tag_kind, comparison identity, origin)
```

`ax_hashtag` can become one taxonomy-backed slice of that table. `Mention`
may remain a specialized, actor-targeted projection while sharing the same
registry Object ID. Unknown tags must remain recoverable from the raw source
payload rather than being fabricated into local taxonomy terms.

### Tag 참조와 Term 사용 인덱스의 구현 경계 (2026-10-20)

위의 `object_id` 중심 제안은 이 절의 URI 기반 2층 모델로 대체한다.
Actor와 Activity를 OP Object 레지스트리에 억지로 등록하지 않는다.

- `wp_ax_tag_references`: 출처 canonical URI별 descriptor 증거.
  `id`/`href` 없는 `{name: "#example"}`도 저장하며, Person을 Mention으로
  변환하지 않는다. 원본 JSON, provider, 출처 역할, 관측 시각, 현재 여부를 보존한다.
- `wp_ax_term_usage`: `(term_taxonomy_id, source_uri_hash)`별 파생 멤버십.
  같은 출처의 대소문자 변형이나 여러 descriptor는 멤버 하나로 센다.
  URI 해시 조회에는 원문 URI 조건도 함께 적용한다.

참조 보존과 아카이브 참여는 별도 결정이다. 기본 역할은 `reference`이며
참여하는 공급자가 `content`를 명시한다. Actor 프로필의 태그는 링크화 및
역조회 근거이지 해시태그 피드 멤버십이나 관심사/추천 선언이 아니다.
`Create.tag`와 `Create.object.tag`는 각각 Activity URI와 Object URI의 증거다.
Activity 상속만으로 일괄 제외하지 않는다. 독립 콘텐츠인 Question은 명시적으로
참여할 수 있다. 이번 구현은 WP 및 원격 Object 캐시 공급자를 연결하며,
Actor/Activity/C2S 공급자는 현재 감사에서 계약과 가드만 검증하며,
수신·저작 경계의 실제 공급자 연결은 다음 단계다.

로컬 포스트의 권위는 계속 `wp_term_relationships`다. 원격 Object는 저장된
payload와 수신 경계의 완전성 정보가 근거다. `complete=false` 관측은 기존 태그를
철회하지 않고, 완전 관측에서 빠진 descriptor는 이력을 남기고 현재 상태를 내린다.
코어 taxonomy `count`는 변경하지 않는다. 인덱스 행은 공개 가능성의 증명이 아니며,
공개 reader는 권위 있는 가시성 판정을 별도로 적용해야 한다.

`feed_sort_at`은 최초 편입 시 정하고 Update 및 제거 후 재편입으로 이동하지 않는다.
기존 행을 유지한 재구축은 커서를 보존한다. 인덱스 자체를 파괴하고 처음부터
재구축할 경우 기존 커서의 유효성까지 보장하지 않는다.
`axismundi_op_rebuild_tag_references(provider, after, limit)`는 WP 또는 원격 캐시를
작은 배치로 다시 읽는다. 기존 원격 hashtag 테이블을 권위로 읽지 않는다.
재구축은 네트워크 요청을 하지 않는다.
캐시 행 자체에는 완전성 플래그가 영속되지 않으므로, 재구축 시에는 누락을 철회로
해석하지 않는다. 이미 보존한 참조 증거를 유지하면서 현재 payload의 증거를 합친다.
재구축은 실패 행에서도 `after`를 전진시키고 `errors`에 ID·오류 코드·메시지를 남긴다.
`done`은 열거가 끝났다는 뜻이며 성공 선언이 아니다. 호출자는 `failed`와 `errors`를
확인하고 실패 ID를 명시적으로 재시도해야 한다. WP 배치는 taxonomy에 연결된
post type의 ID만 조회하고, 본문·리비전을 통째로 읽지 않는다.

태그 투영은 자체 트랜잭션을 소유한다. 외부 트랜잭션 또는 `autocommit=0`에서는
taxonomy/인덱스 쓰기 전에 오류를 반환하며 외부 작업을 암묵 커밋하지 않는다.
훅이 커밋 후라는 보장은 없으므로 호출 규약만으로 안전하다고 주장하지 않는다.
직접 트랜잭션을 여는 공급자는 커밋 후 명시적으로 재동기화해야 한다.
같은 요청에서 동일한 태그 상태를 관측한 중복 WP 훅은 쓰기를 생략한다.
명시적 동기화·재구축은 이 생략을 우회한다. 중간 상태가 실제로 다르면 별도 쓰기다.
term 삭제 시 참조의 vocabulary 매핑은 NULL이 되고 원본 증거는 남는다.
같은 이름의 term을 다시 만들더라도 매핑은 다음 공급자 동기화/재구축 때 복구된다.

첫 슬라이스는 기존 아카이브 reader와 발신 형식을 유지한다. 따라서 구 인덱스는
기존 reader용 호환 투영으로 남지만 새 인덱스의 권위는 아니다. reader 전환 시 제거한다.
숫자 query 라우트, `{id,name}` 발신, 본문 링크화, 공개 커서 열거 및 `totalItems`는
후속 슬라이스다. 정확한 공개 총계를 증명하기 전에는 총계를 생략하되 열거를 막지 않는다.
원격 삭제는 `remote-cache` 공급자 행만 철회한다. 로컬 C2S 저작 저장소를 삭제하지 않는다.

공개 reader 전환 전 게이트: 같은 term을 공유하는 읽기 가능한 로컬 포스트와
원격 Object를 준비하고, 기존 reader와 새 reader의 canonical URI 집합 및 순서가
일치하는지 확인한다. 비공개 멤버가 긴 구간을 이루는 경우도 포함한다.
빈 사이트에서 양쪽 결과가 빈 배열이라는 것만으로 전환을 승인하지 않는다.
이 게이트는 아직 수행 전이며 새 reader를 만드는 슬라이스의 완료 조건이다.

감사 명령: `wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-tag-references.php`.
발신 상호운용 검증은 아직 수행하지 않았으며, 외부 인스턴스에 글을 발행하지 않았다.

## Replies Are Objects; Reactions Are Interaction State

Axismundi does not use `wp_comments` as its reply model. A local reply is its
own post-backed Object, and `inReplyTo` is an Object relation edge. It therefore
uses the same source/projection path as a root Article or Note.

### `inReplyTo` Is A Graph Relation

AS2 permits a property value to be represented as one value or an array. The
Axismundi compatibility contract therefore preserves every received
`inReplyTo` target; it must not silently retain only the first parent. Local
authoring may offer a single-parent reply UI, but that is an explicit product
profile, never a reason to discard additional inbound values.

The current `wp_ax_thread_edges` index is a useful predecessor, not the final
owner of this relation. It records one URI parent per child (`UNIQUE
child_uri_hash`) and derives one `root_uri` and `depth`. That makes it a
single-parent tree projection. Its unresolved-parent write and later
reconciliation behavior are correct and should be retained, but a general
relation source must instead allow one row per `(child, parent)` pair and keep
the canonical parent URI/hash even before the parent is cached.

Once a shared Object registry exists, each edge can additionally carry nullable
child and target registry IDs. Those IDs accelerate joins after discovery; they
are not a prerequisite for recording the URI relation. A tree-oriented reader
may build a separate primary-parent/root/depth projection under an explicit,
deterministic policy. It must be labeled as a derived view and must never claim
to represent every `inReplyTo` value.

#### Decision: The Tree Stays A Derived View

`wp_ax_thread_edges` is not migrated into the general relation table. It becomes
a derived view over it, rebuilt rather than carried, for three reasons:

- It already declares itself a rebuildable index and both writers regenerate it
  from preserved sources -- an outbound Create/Update payload and a stored
  remote-object row. There is no durable fact in it to migrate.
- `UNIQUE child_uri_hash` is the constraint that makes the single-parent tree
  cheap, and it is exactly the constraint a general relation table cannot
  carry. Keeping the view keeps the constraint where it is true.
- Two models stop competing when one is generated from the other. The relation
  table answers "what did this object reply to"; the view answers "where does
  this sit in a thread".

#### The Primary-Parent Rule

When `inReplyTo` carries several values, the tree view takes **the first value
in document order**, reduced to its `id` when the member is an embedded object
or Link.

The rule is deliberately indifferent to everything else. It must not prefer a
local parent over a remote one, a resolved parent over an unresolved one, a
cached parent over an uncached one, or an earlier `published` value. Every one
of those alternatives makes the same stored object produce a different tree at a
different time: a thread would reshape itself as caches warm, as a remote parent
is fetched, or as a parent is tombstoned. An arbitrary but stable parent is
worth more than a well-chosen moving one.

Consequences that follow from it, and that a checker should hold:

- Re-indexing the same payload yields the same primary parent, whatever the
  cache contains at the time.
- A Tombstone payload carries no `inReplyTo` and therefore never rewrites an
  existing edge. This is already implemented and must survive the move.
- The view's parent is not "the" parent. A reader that needs every ancestor
  reads the relation table, not the view.

#### Sequencing: Backfill Before Snapshot Removal

Relation rows for multiple parents must be populated before the Activity ledger
drops its stored envelopes.

For a remote object the full array survives in `payload_json`, so it can be
re-derived at any time. For a locally authored object the outbound Create
snapshot in the Activity ledger may be the only copy of the array: the
post-backed source records one parent today, and the single-parent index records
one by construction. Removing ledger snapshots first would therefore delete the
only surviving record of a second parent.

Ledger snapshot removal is one of the strongest arguments for the shared
registry. It is also the step that must wait: "relation backfill complete" is a
precondition of it, not a parallel task.

`Like`, `Dislike`, `Announce`, and `EmojiReact` are different: they are
Activities that establish an Actor-to-Object interaction. The current
`wp_ax_activities` ledger stores their event rows and queries its active rows
directly; it already carries reaction keys and an aggregate-oriented index.
That is correct for an event record, but it makes every likes/shares/reactions
collection and count depend on a growing history table.

After the shared Object registry exists, materialize current interaction state
instead of making a separate physical table for every collection:

```text
wp_ax_object_interactions
  interaction_id
  target_object_id
  actor_identity_id nullable + actor_uri/hash fallback
  interaction_kind       like | dislike | announce | emoji_react | extension
  reaction_key/raw       nullable; only for reaction kinds
  source_activity_uri/hash
  state                  active | undone | rejected | expired
  occurred_at / updated_at
  interaction_identity   policy/type-specific deduplication key
  indexes: target+kind+state, actor+kind+state, activity URI
```

`Object.likes`, `Object.shares`, an emoji reaction collection, and an Actor's
liked/announced collection are then indexed views over the same edge table,
not independent sources of truth. `Undo` locates the edge by referenced
activity URI and changes its state. The Activity ledger retains the event
envelope; if event payload retention expires, the interaction edge retains the
minimal identity and state needed for current collections until its own policy
expires.

Do not impose one universal `UNIQUE(actor, object, kind)` constraint. A Like
may be one active edge per Actor/Object under a product policy, while emoji
reactions often allow one active edge per Actor/Object/reaction key, and an
extension can define different semantics. The interaction type owns its
deduplication key.

### `View`, `Listen` And `Read` Are Activities Without An Audience

These three are direct Activities in the type tree, so they inherit `actor` and
`object` and the spec's own examples carry both. That settles more of their
design than a storage argument could.

An anonymous visit has no actor, so it is not one of these activities and is
not counted. The alternative -- recognising a browser well enough to count it
once -- is device identification whatever it is called, and a rotating-salt
day key only shortens how long the identification is kept. The number this
project stores is therefore not site traffic. It is the count of actors who
said they viewed, and it must be named that way: "known viewers", a floor.
Traffic analytics, if it is ever wanted, is a separate system and does not
merge into this number.

An actor who publishes quietly produces no row at all. Recording the activity
and excluding it from a count would leave data that a later policy could
retroactively count, which is not what the actor chose; keeping the choice
structural means not writing the row.

These are Activities, and they still do not belong in the Activity ledger.
What makes that ledger the ledger is audience, delivery, `Undo` and feed
membership, and these three use none of them: nobody is addressed, nothing is
delivered, un-viewing means nothing, and "Sally viewed an article" must never
appear as an item in an outbox feed. Storing a three-column fact in a
fifteen-column envelope would also make the heaviest table in the schema the
one with the least to say. They get a specialised table instead, owned by
Activities, in the same way Question's durable aggregate does.

They are not their own table either. `wp_ax_activity_relations` already carries
this shape for actor-to-actor relations -- `relation_type`, `state`, the
activity URI that set the state, and an `evidence_type` -- so what is missing
is its sibling for actor-to-object, where `Like` already belongs.

```text
wp_ax_actor_object_relations           (Activities)
  actor_identity_id
  object_identity_id
  relation_type                        like | announce | view | read | listen
  state                                active | undone
  evidence_type                        activity | local_state
  first_at, last_at
  initiating_activity_identity_id      nullable
  state_activity_identity_id           nullable
  UNIQUE ( actor_identity_id, object_identity_id, relation_type )
```

`state` and `evidence_type` answer different questions and must not be merged.
`state` says whether the relation was undone; `evidence_type` says where its
authority is. A `local` value inside `state` would make the count query read
`state IN ( 'active', 'local' )`, and the first person who writes
`state = 'active'` alone silently drops every locally recorded view.

That column is also what makes one table safe for two authorities. Rows
evidenced by activities are rebuildable from the registry; rows evidenced by
local state are not, and a reindex that cannot tell them apart erases what a
person did in their own reader.

Three consequences for storage:

- One row per actor per object per type, enforced by the UNIQUE key above.
- Feed exposure does not create one. Only a path the product defines as direct
  attention does -- opening the object's own page, starting playback. Counting
  what was merely shown would teach a future ranking signal that display is
  interest, which is a loop that feeds itself. Client-side recording protects
  this twice over: a browser prefetch or prerender usually runs no JavaScript,
  so a page fetched and never looked at records nothing.
- An object's figure is `COUNT(DISTINCT actor_identity_id)`, not a sum across
  the three types. An actor who both viewed and read one article is one known
  viewer, and summing per-type counts would report two. Per-type detail still
  falls out of the same rows, and a `Listen` row against a text article is a
  signal that something upstream is wrong.
- The count is derived from those rows and can be recounted at any time. This
  is exactly the relationship `wp_posts.comment_count` has with comments, and
  it is only true because nothing anonymous is counted: a dedup key that had to
  expire would leave a counter with no source to rebuild from.
- A self-view is excluded by default, as Jetpack Stats excludes a logged-in
  author's visits to their own public site. On a single-actor instance the
  number would otherwise be entirely the author.

Measured from Jetpack Stats' documentation while deciding this: it counts with
a browser pixel, states plainly that it samples, counts a *view* on every page
load without deduplication, and deduplicates only its separate *visitor*
metric, within a chosen time frame. Its bot filtering is heuristic and
documented as incomplete. Two things transfer -- excluding the author's own
visits, and counting from the client rather than the server render, which drops
crawlers that run no JavaScript for free. Sampling does not transfer: it exists
for their scale, and a unique key gives this project exact counts instead.

`Listen` and `Read` carry an obvious further question -- how much was heard,
how far was read -- and that is progress, not a count. It stays out of this
slice so that "once" keeps one meaning.

### Collection Ownership

Activity collections and Object collections may share a registry ID and a
renderer, but they do not have the same source of truth:

| Collection family | Source of truth | Owner |
|---|---|---|
| Actor outbox/inbox, activity audit/history, Actor's likes/announces, Object likes/shares/reactions | Activity events plus current interaction-state projection and Undo handling | Activities |
| Object replies, Object graph/context/attachments, local/remote object discovery, taxonomy-backed hashtag archives | Object sources plus Object/relation/tag projections | Object Projections |
| Generic actor/remote collection membership | A future dedicated collection and membership model | Deferred; neither Media folders nor taxonomy owns it by default |

Object Projections should not interpret activity history to count a reaction.
Activities should not decide how a reply post is sourced or rendered. The
boundary is a narrow read contract, for example an Object ID/URI to an
interaction summary and collection URI. This avoids a shared table becoming a
shared ownership boundary.

## Visibility Is Delivery Policy And HTTP Access Policy

An ActivityPub visibility value and permission to read the canonical WordPress
representation are related policies, not the same mechanism. Activities already
owns the authored `public | unlisted | followers | mentioned` Audience policy:
it resolves local Actor visibility into AS2 `to`/`cc`, and Object Projections
uses that result to fail closed for anonymous ActivityStreams negotiation when
an Object is not public. A frontend that has fetched JSON-LD can also make a
viewer-specific presentation decision, but a Block Theme permalink is rendered
by PHP and can otherwise bypass that decision entirely.

Keep the following cases distinct:

| Intent | Server-side fact | Consequence for a WordPress representation |
|---|---|---|
| Core private post | WordPress capability such as `read_private_posts` | Not "all signed-in users"; retain Core semantics rather than reusing it for federation visibility. |
| Members-only local post | A verified WordPress session | A custom access policy can permit any signed-in account, but it must run before the main query/template output and cover permalink, archive, feed, REST, and Object routes. Responses must not be shared by public caches. |
| Followers-only Object | A viewer can be resolved to a local Actor and has a current allowed follow relation to the attributed Actor | A local signed-in viewer can be checked server-side. A remote follower who merely opens an HTML link has no browser proof of its Actor identity, so inbox delivery can be restricted while direct HTML browsing cannot promise the same follower-only guarantee without a separate federated browser-authentication design. |
| Actor/user block | A viewer Actor or local user resolves to a denied relation | Apply the same early representation gate for local sessions. Delivery policy can stop future federation but cannot retract content already fetched or cached remotely. |

`post_status=private` must therefore not mean `followers`. The existing
Audience resolver remains the authority for federation addressing and anonymous
Object disclosure. A future server-owned
`can_view_object(viewer_context, object)` decision must extend it to
viewer-aware WordPress HTTP representations. The frontend consumes that
decision; it must not be its sole enforcement point.

The minimum honest rollout is to distinguish delivery-only followers visibility
from local-session followers visibility. A fully equivalent browser experience
for remote followers requires an identity-proof protocol (for example a
separate sign-in or delegated authentication flow) and is deferred.

### Current Product Scope

The React frontend is the intended reader for the unified local-and-remote
Object model. It receives the normalized Audience/visibility result and makes
the corresponding reader decision; the existing Activities and Object
Projections implementation already supplies federation addressing and the
anonymous Object-route public gate. Applying an Audience-aware authorization
gate to ordinary Block Theme HTML, Core archives, and arbitrary third-party
WordPress queries is explicitly outside the current frontend/Object Projection
slice. It is not an accidental promise made by `_ax_op_visibility`, and it
must not be added piecemeal to a template while calling the result site-wide
access control.

## Deliberately Unresolved

1. Whether the partial `wp_ax_object_index` evolves into a unified Object
   registry, or a new table supersedes it. It may unify cross-origin identity
   and queries, but must not duplicate the canonical Core source without a
   rebuild contract.
2. The local-only, federation-ready, federated, withdrawn, and tombstone
   operator policy. Code currently has lifecycle behavior, but there is no
   unified server operating-policy document to make retention/republication a
   declared product contract.
3. A generic collection model. Actor collections and remote membership cannot
   be modeled as Media Library folders or WordPress taxonomy relationships.
4. Migration of `_ax_op_mentions` from editor-input meta into an explicit
   local edge-authoring contract. The existing index demonstrates why the
   destination is a relation table.
5. The operator-facing visibility contract: which local-only and federated
   visibility states exist, whether followers-only includes local WordPress
   sessions, denial behavior (`404` versus `403`), and cache headers. This is
   a server policy document and enforcement service, not a frontend feature.
6. Cross-origin Question voting. Today a committed `Create(Note)` from a
   remote Actor can be classified against a local authoritative Question, and
   a local Actor can vote on a local Question. A local Actor cannot vote on a
   remote observed Question: the current target resolver deliberately accepts
   only a local Question URI and the local form follows it. Do not patch this
   by teaching the poll tables to identify remote Objects independently. First
   establish the shared Object registry so a `QuestionTarget` can name either
   a local authoritative Object or a remote observed Object by one stable
   Object ID. Then one direct `VoteIntent -> Create(Note)` path can deliver a
   vote to a remote Question while only local authoritative Questions
   materialize a tally in `wp_ax_poll_votes`. This also removes the current
   local-vote implementation's disposable draft `ax_note` post. Inbound
   remote-vote classification requires its own regression coverage; current
   coverage exercises the same classifier through outbound fixture rows only.

## Identity, Authoring Order, And The Note Cutoff, 2026-10-20

이 절은 그동안 대화에만 있던 결정들을 기록한다. 결정이 추적되지 않은 것이
전날 비용의 대부분이었다. 각 항목은 결정과 그 근거, 그리고 근거가 측정인
경우 측정값을 함께 적는다.

### Authoring Order And The Local Lookup Key

저작 순서는 소스 행보다 먼저 정체성을 확보해야 하는지를 결정한다.
타입이나 소유 플러그인의 이름으로 URL 모양이 자동 결정되지는 않는다.

```txt
WordPress-first   편집기와 post 행이 먼저 생긴다    id = wp_posts 키
activity-first    Activity/C2S 제출이 먼저 생긴다    소스 저장 전 영속 키 확보가 필요
```

이미 발행 중인 선례는 두 개이고, 둘 다 site root 아래의 uuid다. 2026-10-20
확인:

```txt
home_url( '/actors/' . $uuid )   axismundi-actors/includes/repository.php:984
home_url() . '/activities'       axismundi-object-projections/includes/activity-read-route.php:8
```

후속 owner 결정으로 C2S의 canonical id를 `/objects/{uuid}`로 하자는 안은 대체됐다.
다음은 아직 구현 전인 목표 계약이며 이번 해시태그 슬라이스에서 라우트를 바꾸지 않는다.

```txt
로컬 C2S   id = /?ax_object={identity_id}     url = /objects/{identity_id}
원격 객체  id = 피어의 canonical URI       로컬 뷰 = /?ax_remote_object={identity_id}
```

여기서 정수는 `wp_posts.ID`나 축출 가능한 원격 캐시 행 ID가 아니라 공유 identity 키다.
이미 `wp_ax_objects.identity_id`와 `wp_ax_identities`가 있으므로 새 정체성 테이블은
선결 조건이 아니다. 목록 투영인 `wp_ax_object_index`를 레지스트리로 오해하지 않는다.
`source_owner/source_kind/source_ref`를 통해 실제 소스 행으로 연결하며,
원격 재수집 시 같은 canonical URI의 identity를 재사용하고 source_ref만 갱신한다.
이 수명주기는 라우트 전환 전에 별도 감사로 확인한다.
현재 `?ax_object={hash}` 원격 HTML 뷰는 위 목표와 용도가 다르므로 전환 시 분리한다.
로컬 C2S 키 발급과 canonical URI 확정 순서도 저작 경로를 구현할 때 함께 연결한다.

그 프론트앱 경로를 id 로 오해하지 말 것. `axismundi` 플러그인은
`AXISMUNDI_CAPSTONE_ROUTE = 'social'`(`axismundi.php:23`) 아래에서
`social/objects/{…}`를 애플리케이션 라우트로 소유한다(`includes/route.php:13`).
그것은 React 라우팅 경로이며 Object identity namespace 가 아니다. 두 경로는
접두사가 달라 충돌하지 않지만, 이름이 같아서 나중에 혼동될 자리다.

따라서 규칙은:

- **Note 전용 URI를 새로 발급하지 않는다.** 컷오프 이후 C2S 저작 경로는
  위의 타입 중립 조회·정체성 계약으로 연결한다.
- **명시적 컬렉션은 `/collections/{uuid}`다.** 컬렉션은 우리 원장의 것이므로
  CPT 키를 쓸 이유가 없다. 암시적 컬렉션(Outbox, followers 등)은 소유 Actor
  의 주소 아래에 그대로 남는다.
- **private CPT query URL 은 절대 발행하지 않는다.** `axismundi-note`의
  `includes/view.php`에 기록된 이유가 그대로 유효하다: `ax_note`는 공개
  permalink 를 의도적으로 갖지 않으며, 전역 `get_permalink()`를 바꾸면
  비공개 Object 식별자가 유출된다.
- **`UNIQUE canonical_uri_hash` 가 URI namespace 의 전역 제약이다.** 두
  저작 경로가 같은 주소를 주장할 수 없게 하는 것은 규칙이 아니라 이 제약이다.
- **`id`와 `url`은 분리된다.** 현재 WP-first 객체는 `?p=ID` 또는
  `?attachment_id=ID`를 유지하며 `url`은 사람이 읽는 WordPress 주소다.
  제품별 조회 변수를 추가하는 것과 이미 발행된 AS2 id를 바꾸는 것은 별도다.
- **프론트 데이터는 canonical URI로 대상을 식별한다.** 앱의 화면 경로는
  별도로 가질 수 있지만 그것을 새 연합 정체성으로 해석하지 않는다.

### Three Ways An AS2 Type Can Land, Not One

AS2 Object 타입 목록을 하나의 규칙으로 처리할 수 없다. 세 갈래다.

```txt
저작 콘텐츠        사람이 본문을 쓴다          소스 저장소가 필요하다
파생 서술자        다른 사실에서 계산된다      저장소가 아니라 투영이다
코어 미투영        WordPress 가 권위다        기본 투영하지 않는다
```

1. **저작 콘텐츠** — `Note`, `Article`, `Event`, `Question`. 본문·상태·정책을
   보관할 소스가 필요하다. 어느 소스냐는 위의 저작 순서 축이 정한다.

2. **파생 서술자** — `Profile`, `Relationship`, `Tombstone`. 이 세 개는 저작
   대상이 아니라 다른 사실의 표현이다. `Profile`은 Actor 사실의 서술,
   `Relationship`은 관계 행의 서술, `Tombstone`은 사라진 Object 의 서술이다.
   **이들에게 저작 저장소를 주면 같은 사실이 두 곳에 생긴다.** CPT 로 만들기
   애매했던 것은 모델링 실패가 아니라 이들이 저작물이 아니기 때문이다.
   `Tombstone`은 이미 그렇게 동작한다: envelope 의 `object_status`가
   `tombstone`이고 별도 저장소가 없다.

3. **코어 미투영** — `Page`. WordPress `post_type=page`는 기본 투영하지
   않는다. 금지가 아니라 기본값이며, 제품이 필요하면 등록할 수 있다. 이것은
   사양이 아니라 우리 선택이다(`activitystreams-collection-model.md`의
   specification-versus-project-choice 규칙 참조). AS2 `Page`는 별개 문제이고
   미디어 라이브러리 경로는 MIME 로 타입을 정하므로 AS2 `Page`를 만들지
   않는다.

### Place Is An Inline Value, Like Mention

`Place`에 CPT 를 주지 않는다. 로컬과 원격이 **같은 좌표, 같은 이름**을 가질
수 있고, 그때 두 개의 Place 행은 같은 장소에 대한 두 개의 주장이 된다.
해소할 권위가 없는 중복이다.

그래서 `Place`는 `Mention`과 같은 자리에 둔다: **값으로 inline 되고, id 를
발급하지 않고, 독립 수명주기를 갖지 않는다.** 좌표·용어·주소의 3분리와
privacy 정밀도는 이미 geodata 쪽 결정이고, 그 결정이 Place 를 저장 객체로
만들 필요가 없다는 뜻이기도 하다. Geodata 가 Place 관련 작업을 미뤄둔 이유가
이것이며, 미룬 것이 옳았다.

원격이 `Place`에 `id`를 붙여 보내면 그 `id`는 관측 증거로 보존한다. 우리가
발급하지 않는다는 것과 받은 것을 버린다는 것은 다르다.

### Note: The Envelope Is Already The Authority

`ax_note` CPT 를 제거한다. 하드 컷오프이며 복원 경로를 만들지 않는다.

근거는 두 개이고, 두 번째는 측정이다.

**첫째, 연합 객체는 URI 로 주소를 잡는데 CPT 는 로컬 정수로 잡는다.** 원격
`Note`와 원격 `Article`은 전부 URI 키 캐시에 들어가는데 로컬 `post`와
`ax_note`만 식별 구조가 다르다. 그 비대칭이 Note 를 특수 사례로 만들었다.

**둘째, envelope 은 이미 post 보다 오래 산다.** 2026-10-20 측정:

```txt
wp_ax_notes 행                                     813
ax_note post 행                                     76  (publish 66 / draft 9 / auto-draft 1)
wp_posts 에 짝이 없는 envelope 행                   744
그 중 object_status='tombstone'                     744
그 중 tombstone 이 아닌 것                            0
```

고아 행 744 와 tombstone 744 가 **정확히 같은 집합**이다. 즉 post 가 삭제된
뒤에도 envelope 이 남아 Tombstone 의 주소를 유지한다. 이것이 설계대로
동작하는 모습이고, envelope 이 이미 권위라는 주장의 증거다. CPT 를 제거하면
사라지는 것은 블록 에디터이지 정체성이 아니다.

Note 본문에는 블록 에디터가 거의 필요 없다. 순수 HTML 또는 Markdown 이 더
맞고, GitHub 식 편집기가 적절하다. `author`도 필요 없다 — Actor 로 쓸 수
있으면 된다. 이 점에서 Note 는 `wp_comments`와 닮았지만 **`wp_comments`는 쓸
수 없다**(아래).

이 결정으로 `axismundi-note` 플러그인의 존재 근거가 CPT 에서 옮겨간다. Note 는
§7 의 경로로 타입·source reader·UI 를 등록하는 제품이 되고, 그 경로는 Event 와
Place 가 쓸 경로와 같다. 기존 구현 자산(thread 로직, interaction, 관리 화면,
C2S envelope 검증)은 폐기가 아니라 등록 쪽으로 이동한다. 플러그인을 완전히
폐기하고 나중에 Markdown 편집기 UI 플러그인으로 다시 분리하는 것도 열려
있으며, 그 선택은 이 컷오프의 선결 조건이 아니다.

### A Merged Object Store Needs `authority_origin` First

로컬 저작 Note 와 원격 Object 캐시를 한 테이블에 합치는 것은 성립한다.
선례가 있다: `wp_ax_activity_questions`가 `authority_origin varchar(8)`에
`KEY`까지 두고 로컬/원격을 한 테이블에서 구분한다
(`axismundi-activities/includes/questions.php:40`, `:54`, 분기는 `:358`,
`:468`, `:574`).

그러나 **선결 조건이 하나 있고, 구현보다 먼저여야 한다.** 원격 캐시 테이블의
전제는 "축출 가능한 관측"이다. `includes/leases.php`는 자신을 "Multi-reason
retention leases for URI-keyed remote object observations"로 설명하고,
`includes/remote-objects.php:1029`에는 URI 로 행을 지우는
`DELETE FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s`가 있다.
리스는 보존 힌트이지 권위 보증이 아니다.

```txt
선결 조건   authority_origin 을 삭제 경로가 반드시 검사한다
핀          URI 로 지우는 호출은 authority=local 행을 제거할 수 없다
```

관측은 다시 받으면 되지만 저작은 지워지면 끝이다. 되돌릴 수 없는 손실이 그
경로에 있으므로 이것을 나중에 붙이는 것은 안전하지 않다.

두 번째로 `payload_json`이 출처마다 다른 사실이 된다. 원격은 **받은 문서**의
증거이고, 로컬은 **내보낸 스냅샷**의 증거다. 같은 컬럼에 두 사실을 담으면
섞인다. 이름을 나누거나 컬럼을 둘로 둔다.

합쳐서 얻는 것은: 저장소 하나, source reader 하나, assembly 경로 하나.
레지스트리가 이미 `source_owner`로 디스패치하므로 `source_kind` 두 값이
하나로 접힌다. 다만 물리 테이블 통합은 Note 컷오프의 선결 조건이 **아니다.**
주소(`/objects/{uuid}`)를 공유하는 것과 테이블을 공유하는 것은 별개이며,
삭제·보존 규칙이 다른 두 저장소를 한 테이블에서 관리하는 비용이 먼저
확인되어야 한다. 애플리케이션에는 이미 하나로 보인다.

### Why Neither Core Table Can Hold This

`wp_posts`도 `wp_comments`도 쓸 수 없는 이유는 같다. **연합 객체는 URI 로
주소를 잡고, 두 코어 테이블은 로컬 정수 키로 주소를 잡는다.**

```txt
wp_posts.ID          bigint  auto_increment   로컬에서만 의미 있다
wp_comments.comment_ID bigint auto_increment  같다
연합 객체의 주소      절대 URI                발신 서버가 정한다
```

`wp_comments`는 Note 의 모양(author 불필요, 짧은 본문, 답글 중심)과 닮았지만
그것은 표면 유사성이다. 정체성 축이 다르고, taxonomy 연결도 자동으로 따라오지
않는다. `wp_set_object_terms()`가 정수 object ID 를 받기는 하지만 코어 관계
테이블에는 객체 종류 구분이 없어서, 같은 taxonomy 에서 post ID 와
comment/custom-table ID 를 섞으면 충돌한다. 그래서 `terms`와 `term_taxonomy`는
재사용하고 객체-term 연결은 별도 관계 행으로 둔다. 원격 해시태그가 이미
`remote_object_id ↔ term_taxonomy_id` 연결을 쓰고 있다. 해시태그 때문에 CPT 나
`wp_comments`로 돌아갈 필요는 없다.

### Correction: The 744 Orphan Rows Were Not A Defect

이전에 "식별자가 소스보다 오래 남는다"는 defect 로 기록해 둔 네 건 중
`wp_ax_notes` 744 행은 **결함이 아니었다.** 위 측정이 보여주듯 고아 행 744 와
`object_status='tombstone'` 744 가 같은 집합이고, 비-tombstone 고아는 0 이다.
Tombstone 이 주소를 유지해야 하므로 envelope 이 post 보다 오래 사는 것이
설계다.

표본 하나를 측정 없이 결함으로 일반화한 것이 원인이다. 같은 모양의 오류가
이 문서들에서 반복되었으므로 삭제하지 않고 정정으로 남긴다. 나머지 세 건
(`page` 정체성 2 행, Note 정체성 shadow, 캐시 행이 사라진 원격 Object)은
재측정 전까지 미결로 남는다.

## Activity Handlers Before A General Executor, 2026-10-20

타입별 실제 핸들러를 먼저 완성하고, 반복이 확인된 구현만 공통 집행 모듈로 추출한다.
판정 축은 Activity 타입 + C2S/S2S 경로 + 대상 도메인이다. 같은 Create라도
로컬 저작 명령과 원격 관측 반영은 동일한 작업이 아니다.

멱등성, 실패·재시도 경계, 원장 기록과 상태 변경의 일관성은 각 핸들러에서 처음부터
명시하고 해당 감사로 검증한다. 나중에 추출할 대상은 구현이지 정확성 조건이 아니다.
이것은 설계 방향이며, 이번 슬라이스가 범용 실행 엔진을 구현하거나 모든 기존
핸들러의 조건을 이미 검증했다는 주장이 아니다.

## Sources

- [ActivityStreams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/)
- [Core post source boundary](../plugins/axismundi-object-projections/docs/CORE-WP-OBJECT-SOURCES.md)
- [Local Object lifecycle and attribution contract](../plugins/axismundi-object-projections/docs/LOCAL-OBJECTS.md)
- `axismundi-object-projections/includes/post-settings.php`, `mentions.php`,
  `object-relations.php`, `hashtags.php`, and `remote-objects.php`
- `axismundi-activities/includes/repository.php`

