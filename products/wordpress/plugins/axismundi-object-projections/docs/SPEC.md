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

The unified projection work introduces three more, and they are listed here because the
word "renderer" was being used for the whole pipeline, which collides with the frontend
app's component renderer:

- **Projector** — the one thing that turns a local source into one AS object. The word is
  already used in this sense in `axismundi-calendar/includes/cpt.php` ("so there is exactly
  one projector"), and "exactly one" is the point of it.
- **Source reader** — the part that reads WordPress storage. There is one, not one per
  type: `attachment` is a Core `post_type`, so it is not a separately registered reader
  (CORE-WP-OBJECT-SOURCES.md §1).
- **Profile** — the rule selecting which AS2 type a source becomes, in the architecture
  registry's sense of "projection profile". A profile does not alter the type hierarchy.
- **Renderer** — narrowed to what `renderer.php` actually is: `@context` assembly,
  validation, and serialization. It is not the name of the pipeline.

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

## 4a. Decided, not yet implemented

These are recorded so the next slice is not designed against their absence. None of them
is enforced today; each needs its own check before it may be described as a contract.

1. **One declared key order.** `axismundi_op_finalize_object()` is the single point every
   object passes through and it performs no ordering at all today, so member order is
   whatever each transformer's insertion order happened to be. Measured 2026-10-17: the
   same two keys appear in opposite order in two projections of this plugin's own making
   (`url` then `mediaType` on an Article, `mediaType` then `url` on an Image). Machines do
   not care; people reading a payload do, and the divergence is evidence of duplicated
   assembly. The order is declared once at that choke point, keys outside the declaration
   keep their existing relative order and are appended rather than dropped — a full-IRI
   extension key such as `http://fedibird.com/ns#emojiReactions` must survive — and the
   audit asserts **relative order of shared keys**, never a key list, which would break on
   every added member.
2. **An effective property set is not an outbound allow-list.** The architecture registry's
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
