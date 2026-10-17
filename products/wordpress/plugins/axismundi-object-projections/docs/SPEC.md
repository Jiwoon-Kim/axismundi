# Axismundi Object Projections — specification

> Status: **Phases 0–5b implemented** (local projections, Actor Outbox, metadata-only remote
> discovery/cache inspection). No Activity
> ledger, or transport. This package owns the projection contract and representation.

## 1. Purpose

Project a WordPress object (post, attachment, archive, folder) into an ActivityStreams
2.0 object or `OrderedCollection`, so the existing WordPress URL can answer with JSON-LD
under content negotiation. It does **not** own an Activity store, Inbox writes, Follow /
Like, signatures, delivery, or the objects' own storage.

```
WP_Post ──(Transformer)──▶ normalized AS object ──(Renderer)──▶ JSON-LD
```

## 2. Ownership

**Owns**

- The transformer registry (object + collection).
- Object / collection URIs (the stable AS `id`).
- ActivityStreams JSON-LD serialization and the single `@context` assembly.
- Object/collection representation + content negotiation on the existing URL.
- Public read routes for representation-owned collections such as Actor Outbox.
- The Core Post → `Article` transformer.
- First-party integration adapters that translate stable domain service APIs into
  ActivityStreams semantics (currently Axismundi Media Library attachments).
- Object lifecycle *events* (publish/update/delete) — emitted, not stored.
- URI-keyed **remote object projections**: rebuildable observed snapshots independent
  of Activity ingestion, with bounded administrator discovery and metadata-only
  inspection (see REMOTE-OBJECTS.md).

**Does not own**

- `wp_ax_activities`, Inbox writes, Follow/Like/Announce state, signatures, delivery, retry.
- Note / media object storage itself (the domain plugins own it).
- Authority over a remote canonical object: its URI remains the source identity and a
  local projection row is only a refreshable cache, never a replacement identity.
- The official ActivityPub plugin's internal models.
- Remote binary files; the object table stores metadata/payload observations only.

## 3. Terms (kept distinct)

- **Actor projection** — the Posts / Media / Notes tabs and links on an Actor profile
  (owned by Axismundi Actors; a navigation concern).
- **Object transformer** — `WP_Post → Article`, `attachment → Image`, …
- **Collection transformer** — author / archive / folder → `OrderedCollection(Page)`.
- **Object Projections** — this plugin: the transformer registry, JSON-LD representation,
  and (Phase 2) object/collection negotiation.

The unified projection work introduces four more. They are listed here because "renderer"
was being used for the whole pipeline, which collides with the frontend app's component
renderer, and because the pipeline is not one step:

```text
source / URI ─(Resolver)─▶ identity + model + type ─(Model handler)─▶ members ─(Renderer)─▶ JSON-LD
                                                          ▲
                                              natural-language values (§4.7-4.8)
```

- **Resolver** — the step that decides *what this is*: a source or URI in, and an
  identity, an AS2 **model** and a type out. This is the accurate name for the front of
  the pipeline, and the code already works this way --
  `axismundi_op_resolve_source_by_uri`, `axismundi_op_local_source_from_object_uri` and
  `axismundi_op_local_object_profile` are all resolution. Calendar's "so there is exactly
  one projector" (`axismundi-calendar/includes/cpt.php`) remains the statement of intent --
  one path per source, never two -- while "resolver" names the mechanism that achieves it.
- **Model handler** — composes the members one AS2 Core §4 model owns: Object, Link,
  Actor, Activity, IntransitiveActivity, Collection. The dispatch key already exists in the
  architecture registry's contract as `category` (`core | actor | activity | object | link
  | collection`), so this is not a new axis. `IntransitiveActivity` needs no handler of its
  own: what distinguishes it is that it excludes `object`, which `effective_properties()`
  already computes.
- **Source reader** — the part that reads WordPress storage. There is one, not one per
  type: `attachment` is a Core `post_type`, so it is not a separately registered reader
  (CORE-WP-OBJECT-SOURCES.md §1).
- **Profile** — the rule selecting which AS2 type a source becomes, in the architecture
  registry's sense of "projection profile". A profile does not alter the type hierarchy.
- **Renderer** — narrowed to what `renderer.php` actually is: `@context` assembly,
  validation, and serialization. It is not the name of the pipeline.

Model dispatch is a split this file already began without naming it, which is the argument
for finishing it rather than introducing it. `renderer.php` has three finalize paths today
-- `axismundi_op_finalize_object()`, `axismundi_op_finalize_activity()` and
`axismundi_op_run_collection_transformer()` -- so object, activity and collection are
already separate. Actor is not: it goes through the object path and adds `inbox`, `outbox`,
`endpoints` and `publicKey` as ad-hoc members. **Link has no handler at all**: measured
2026-10-17, `'type' => 'Link'` is assembled inline in five files across three plugins
(media-library three times, post-article twice, thread-edges, Note's federation, Contacts'
canonical), which is where the `href`/`rel`/`hreflang`/`mediaType` rules would otherwise be
enforced once.

Natural-language values are **not** a model. §4.7 and §4.8 are a value shape every model
shares -- the `Map` forms, BCP 47 keys, string values -- and `axismundi_op_clean_language_map()`
is already the one implementation. They belong beside the model handlers, not inside one.

A note on how that absence was found, because it is the kind of thing that stays invisible.
Nobody had failed to notice that `Link` has no handler; there was no concept under which
"a model with no handler" was a thing one could look for. The gap became a measurable
question only once the models were named, and then it took one search to answer. Naming is
not decoration here: the project's standing rule is to measure rather than infer, and this
is its complement -- you can only measure what you have a concept for. Both this gap and
the key-order divergence in section 4a were sitting in the output the whole time.

### The resolver's core post-type contract

Decided 2026-10-17, and the first half is implemented.

| `post_type` | Resolves to |
| --- | --- |
| `post` | `Article` |
| `attachment` | `Document` by default, narrowed by `post_mime_type` to `Image`, `Audio` or `Video` |
| `page` | **nothing.** Not projected |
| anything else | nothing, unless a product registers a profile for it |

`attachment` already works this way: `axismundi_op_core_attachment_type()` returns `Document`
and narrows on the MIME prefix, so the default is the general case and the media types are
refinements of it rather than a list to be matched exhaustively. A MIME type nobody
anticipated therefore still projects, as a `Document`.

`page` was registered as a `Page` profile by an earlier slice and that has been removed.
Measured before removing it: a published page served `application/activity+json` at its
permalink with `"type": "Page"`. Two reasons it should not. The architecture registry's
projection-profile table already recorded `core page | no default | explicit opt-in only`,
so the code had quietly made a default out of something the record said needed opting into.
And a page is site furniture -- a contact page, a policy, a landing page -- so publishing one
as a federated Object asserts it was authored for an audience, which `post_status = publish`
has never meant. The same table says so in general: a visibility state is never a sufficient
projection selection rule. No opt-in mechanism was built in its place; when one is wanted it
belongs to the registration API rather than to a second hardcoded profile.

### Neither of these is an open question, and both were listed as one

Recorded because the mistake is instructive: two consequences of decisions
already taken were written here as though the owner still had to choose.

**Whether an embedded descriptor respects the Media Library's locked gate is not
this plugin's question.** An embedded descriptor is what an install with no
Media Library plugin emits; tiers, locked folders and collections are that
plugin's policy. The seam is already the right shape --
`axismundi_op_attachment_descriptor_visible` asks, the Media Library answers,
and its answer today is no -- so what remains undecided is that plugin's policy,
which belongs to its collection model and is recorded in its own DATA-MODEL.md.
Nothing here waits on it.

**Whether a standalone media Object declares an audience was answered twice,
and the second answer is the one that holds.** The first was that it cannot,
because addressing presupposes somebody who addressed it and an uploaded file
has no `attributedTo`. That reasoning proves too much: by it a published post
with no Actor could not be addressed either, and it is. Publication intent comes
from the source's own state and an Actor only narrows, so media is public by
default like anything else published, and the narrowing comes from the Media
Library's policy and from the Actor an exposure supplies. See section 4a item 0;
the measured asymmetry is an inconsistency to fix, not a derivation to keep.

What is still genuinely open is recorded with the decision it belongs to, in
LOCAL-OBJECTS.md: what settles attribution for a local file whose origin
recorded nobody.

## 4. Frozen contracts

1. **id vs url are different.** The AS `id` is a stable, permalink-independent URI; `url`
   is the human HTML permalink (§ROUTING). A transformer's `id` **must** equal its
   declared object URI — the renderer rejects a mismatch.
2. **The renderer is the sole owner of `@context`.** A transformer returns a plain object
   with no `@context`; the renderer strips any supplied one and sets the canonical
   context. Extensions come only through `axismundi_op_jsonld_context`.
3. **Transformers are pure projections** — no DB writes, no network, no route ownership,
   no content-negotiation decisions.
4. **Three outcomes are distinct**: no transformer (`ax_op_no_transformer`), a transformer
   error (its own `WP_Error`), and a not-public source (`ax_op_not_public`). The router
   passes unsupported sources through, maps not-public to 404 and real failures to 500.
5. **Visibility is a callback, not the transformer body.** Public/private is decided by a
   registered `visible` callback, so the transform stays a pure mapping.
6. **The official ActivityPub plugin is not a dependency.** Object Projections works fully
   standalone; when ActivityPub *is* active, only one negotiator owns a URL (§COMPATIBILITY).
7. **First-party domain plugins remain ActivityStreams-agnostic.** Object Projections
   detects them and consumes their public service functions; it does not inspect their
   private SQL or metadata schema. Third-party plugins may register their own transformer.
8. Core Post lifecycle candidates fire only after a committed, projectable public save.
   They are idempotent hints, not proof of first publication. Activities owns lifecycle
   state and storage. Media upload emits no candidate, and Reply waits for the Notes CPT.
9. Core Posts are long-form `Article` objects following FEP-b2b8. Manual Excerpt and More
   are distinct authored inputs (`summary` and embedded Note `preview`); no automatic
   truncation is treated as authored content. Federated HTML uses a plugin-owned positive
   allowlist rather than WordPress's globally extensible post allowlist.
10. Media relationships come from the Media Library reverse index, never URL guessing.
    Featured media is `image`; active in-content media is `attachment`. Attachment
    `usedIn` is a representation-owned extension collection and must not reveal a source
    that an anonymous viewer cannot project.
11. **Object member order is renderer-owned.** `axismundi_op_finalize_object()` puts known
    top-level Object members in one declared, human-readable order. It does not reorder
    nested Links or embedded Objects, which have their own model contracts. Unknown
    extension keys remain present and retain their relative transformer-provided order at
    the tail. Audits assert relative order only; a complete key list would turn ordinary
    extension work into a serialization regression.

## 4a. Decided, not yet implemented

0. **A published source is public by default, and only an Actor narrows it.** Superseded
   on 2026-10-17 the rule recorded here a few hours earlier, which said an Object with no
   `attributedTo` declares no audience. It does declare one. Publication intent comes from
   the **source's own state** -- WordPress says this post is published -- and that
   statement is not about any Actor, so it survives the absence of one. What an Actor adds
   is the ability to *narrow*: followers-only and mentioned-only are defined relative to
   an Actor, which is why they need one and the public default does not.

   So the measured behaviour is right rather than wrong: `/vqa-demo-post-1/` carries no
   `attributedTo` and `to: ["…#Public"]`, and the code's own comment -- "An unattributed
   Object can still be addressed, but only publicly" -- was already the correct reading.

   The prerequisite that earlier reading implied therefore dissolves.
   `axismundi_op_post_article_publicly_readable()` derives readability from the resolved
   audience, and under this rule the two agree by construction: a published source is
   public, so it is both readable and publicly addressed. Nothing has to come apart first.

   **Media follows the same logic, and that is the point of recording it.** It is easy to
   land on "an uploaded file has no author, therefore no audience", which was written here
   too. The consistent reading is that a standalone media Object is public by default for
   the same reason a published post is -- and the attachment page is a forced-public
   surface in any case, carrying a content warning as its only modifier -- while the
   narrowing comes from the Media Library's policy and, once an exposure gives the object
   an Actor, from that Actor. One rule for both, with the asymmetry measured earlier (an
   Article declaring Public while the standalone Image of the same site declares nothing)
   now an inconsistency to fix rather than a derivation to keep.

   Related and deliberately not changed with it: `cc` is emitted as an empty array when
   nothing was copied, and a check in `audit-post-article.php` asserts exactly that for a
   mentioned-only Article, using the empty array as evidence that neither followers nor
   Public were copied. Whether an empty member is omitted or emitted is one representation
   decision for every member, and it belongs to the Object model handler rather than being
   taken a member at a time. Changing it for `cc` alone was tried here and reverted when it
   contradicted that check.

1. **An effective property set is not an outbound allow-list.** The architecture registry's
   `effective_properties(type)` is an internal conformance contract; ActivityStreams does
   not forbid extension properties outside it, and inbound stays permissive. A projector
   that emits only what the set names would silently drop the FEP and vendor terms this
   plugin already publishes. Compose from the registry; do not filter by it.

## 5. Public API

```
axismundi_op_register_object_transformer( string $id, array $args ) : true|WP_Error
axismundi_op_register_collection_transformer( string $id, array $args ) : true|WP_Error
    // $args: supports(callable), object_uri|collection_uri(callable),
    //        transform(callable):array|WP_Error, [visible(callable):bool], [priority=10]

axismundi_op_transform_object( mixed $source ) : array|WP_Error
axismundi_op_transform_collection( mixed $source ) : array|WP_Error
```

Registration is done on the `axismundi_op_register_transformers` action.

The built-in `core-post-article` transformer is available in standalone mode. Adapters
may filter `axismundi_op_post_object_uri` to preserve an established external id and
`axismundi_op_post_actor_uri` to bridge another Actor provider.

See TRANSFORMERS.md (contract detail), ROUTING.md (URI contract), COMPATIBILITY.md
(ActivityPub co-existence), PHASES.md (roadmap).
