# Transformer contract

A transformer is a **pure projection** of one WordPress source into an ActivityStreams
object (or collection). It performs no DB write, no network call, and owns no route.

## Registration

```php
add_action( 'axismundi_op_register_transformers', function () {
    axismundi_op_register_object_transformer( 'core-post-article', array(
        'supports'   => fn( $s ) => $s instanceof WP_Post && 'post' === $s->post_type,
        'object_uri' => fn( WP_Post $s ) => add_query_arg( 'p', $s->ID, home_url( '/' ) ),
        'transform'  => 'my_post_to_article',   // returns array | WP_Error
        'visible'    => fn( WP_Post $s ) => is_post_publicly_viewable( $s ),
        'priority'   => 10,
    ) );
} );
```

- `supports($source): bool` — cheap type/ownership check; throwing is treated as "no".
- `object_uri($source): string` / `collection_uri($source): string` — the **stable AS id**.
  Must be non-empty; the renderer asserts the transform output's `id` equals it.
- `transform($source): array|WP_Error` — the mapping. Return a plain array **without**
  `@context` (the renderer owns it). Return a `WP_Error` for a genuine failure.
- `visible($source): bool` — optional public/private gate. `false` yields
  `ax_op_not_public`, kept distinct from an error and from "no transformer".
- `priority` — lower runs first; ties break on registration order.

## What the renderer guarantees

- Required members `id`, `type`, `attributedTo`, `url` are present and non-empty.
- The emitted `id` equals the declared object/collection URI.
- `name` is reduced to plain text; `content` / `summary` pass the dedicated FEP-b2b8
  positive allowlist. Embedded preview HTML uses the same sanitizer.
- Exactly one canonical `@context`, owned by the renderer; a transformer-supplied
  `@context` is dropped.
- A transformer exception becomes `ax_op_transform_threw`, never a fatal.

## Outcome codes

| Situation                         | Result                        |
|-----------------------------------|-------------------------------|
| No transformer supports the source | `WP_Error ax_op_no_transformer` |
| `visible` returns false            | `WP_Error ax_op_not_public`   |
| Transformer returns `WP_Error`     | that error, unchanged         |
| Missing required member            | `WP_Error ax_op_invalid_object` |
| `id` ≠ declared URI                | `WP_Error ax_op_id_mismatch`  |
| Success                            | JSON-LD array with `@context` |

## Built-in Core Post transformer (0.0.13)

`core-post-article` supports only the core `post` post type and emits `Article` with a
stable `/?p={ID}` id, human permalink Link, Actor `attributedTo`, title, rendered HTML,
manual Excerpt `summary`, and published/updated timestamps. A More teaser becomes an
embedded `Note` preview; without More, a manual Excerpt supplies the title+summary preview
recommended by FEP-b2b8. The preview has no independent `id` or `url`. No automatic
excerpt is invented. It requires a public user Actor, or a
deliberately public site Actor fallback. It never creates an Actor during rendering.

`axismundi_op_post_object_uri` is the compatibility seam for pre-existing object ids;
`axismundi_op_post_actor_uri` is the seam for another Actor provider. A future official
ActivityPub adapter must use the former to retain that plugin's per-post legacy choice.

## First-party Actor transformer (0.0.9)

Public local `Axismundi_Actor` values project at their immutable Actor URI. Object
Projections owns the Actor document and its identity/profile fields. The optional
`axismundi_op_actor_transport_fields` filter accepts only protocol transport members
(`inbox`, `endpoints`, and `publicKey`); it cannot replace the Actor `id`, `type`, human
profile `url`, Outbox, or representation-owned social collections. The Actor transformer advertises the
representation-owned Outbox when Activities exposes its public query API. That Outbox is
a separate collection transformer and never reads or mutates the Activity table directly.

### Planned followers and Quote policy projection

Object Projections owns the stable Actor `followers` URI and its representation, following
the existing Outbox boundary: Activities supplies public-safe relation queries, while the
Bridge is uninvolved. The first increment deliberately omits `following` and follower
member enumeration.

- The Actor document advertises a UUID-based `followers` URI.
- When `follow_collections_visibility` permits public disclosure, dereferencing it returns
  a count-only ActivityStreams `Collection` with `totalItems` and no member list.
- When policy forbids disclosure, the endpoint exposes neither count nor members and fails
  closed. Advertising an identity does not grant enumeration permission.
- QuoteRequest approval checks the internal accepted-Follow relation. It does not fetch this
  Collection, so a count-only or inaccessible representation does not weaken policy.

The same increment adds the post setting `Who can quote this post?` with `anyone`,
`followers`, and `me`, and projects it as FEP-044f `interactionPolicy.canQuote`. `followers`
references the stable followers URI; `me` references the author Actor URI. These values are
advisory policy declarations and never constitute proof that a particular quote was
authorized.

The renderer remains the sole JSON-LD context owner. FEP-044f quote and authorization terms,
GoToSocial interaction-policy terms, and Misskey compatibility terms are added only through
`axismundi_op_jsonld_context`; transformers must not emit a second `@context`.

Object Projections also owns the dereferenceable representation of an Activities-issued
`/?ax_quote_authorization={uuid}` identity. It emits the FEP-044f authorization members,
including the quoting Object, quoted Object, and author Actor, without becoming the owner of
authorization lifecycle state.

## First-party Media Library adapter (0.0.4)

When Axismundi Media Library is active in Independent mode, Object Projections registers
an attachment transformer without requiring Media Library to know ActivityStreams. It
emits Image, Video, Audio, or Document with stable `/?attachment_id={ID}` identity, the
human attachment page as `url`, Actor attribution, sensitivity, canonical license, and a
nested media Link. Images use Media Library's bounded derivative service by default.

Visibility is anonymous and cache-safe: public and unlisted, ungated attachments only.
Owner/editor bypasses are deliberately not used. The adapter consumes public Media Library
functions and never queries its tables or private metadata schema.

## FEP-1311 media renditions (0.0.16)

The `url` = human page + nested single media `attachment` structure described above is
**superseded**. An attachment now emits one FEP-1311 `url` **Link array** — see
MEDIA-RENDITIONS.md for the full contract:

- Media Links first (`mediaType` / `width` / `height` / `size`), the `text/html` page **last**,
  so a naive `url[0]` consumer of an `Image` reads media rather than the page. Resolve the HTML
  representation by `mediaType`, never by position.
- Images advertise **only already-generated derivatives**, supplied by Media Library's
  `axismundi_media_federation_renditions()`. **The original is never advertised**; an image with
  no derivative emits the HTML Link alone. Video / audio / documents keep their existing
  single-file policy while no transcoding substrate exists.
- `id`, `type`, `mediaType`, and the ordered `url[]` form **one shared core** across the
  standalone Attachment, an Article's `attachment[]`, and `preview.attachment`. Only the
  descriptive `name` diverges: the standalone keeps `post_title`; embedded media uses the image
  **alt text** and omits `name` when alt is empty.
- Object Projections **serializes only** — Media Library owns selection, and no attachment
  metadata internals are read here.

## Media folder collection (0.0.18; children 0.0.20)

A federated folder is an `OrderedCollection` at its immutable identity URI, paginated as
`OrderedCollectionPage`. Media Library owns identity, ACL, ordering, and item selection —
this transformer serializes and owns the public read route, nothing else.

`orderedItems` is **heterogeneous by contract**: a folder is an OS-directory affordance
(FEDERATED-MEDIA.md §3.1), so its listing carries the folder's child folders *and* its
direct media. AS2 permits a Collection among a Collection's items; this is not an
extension.

```
orderedItems = [ child folder refs … , media objects … ]
totalItems   = visible children + visible media
```

Frozen rules:

1. **Children first, then media.** Not a preference: media order is
   `_ax_media_folder_added_at`, which a child folder has no value for, so the two cannot
   interleave under one key. Children sort by name, media by add time descending. The order
   is total, so page boundaries are stable even though they cut across both kinds.
2. **A child is a shallow reference, never an inlined collection.** It carries `id`, `type`,
   `name`, `totalItems`, and its human `url` — enough to render a row and open it. Inlining
   a child's items would recurse the whole tree into one document; depth costs one request
   per level, as a drive does.
3. **A media item is the full standalone object**, identical to what its own `id` returns.
   The collection is not a summary, so a consumer needs one request per page rather than
   one per item. This is why the item and its `id` fetch the same document byte for byte.
4. **A child is listed only if it would federate alone.** The gate is Media Library's
   `axismundi_media_folder_federation_allowed()`, unchanged and unduplicated. `internal`,
   private, and gated children are absent from `orderedItems` **and** from `totalItems` —
   a name is a disclosure.
5. **`totalItems` counts what is listed.** A parent whose media are all private and whose
   children are all internal reports 0 and offers no `first`.

From 0.0.13, the adapter consumes the Media Library relation API in both directions.
Featured media becomes Article `image`; distinct active in-content image/video/audio/file
references become Article `attachment`. Arbitrary external URLs in rendered HTML are not
reverse-resolved into WordPress IDs. Each public Attachment advertises a `usedIn`
OrderedCollection (an Axismundi extension term) containing only distinct public Article
URIs. Private usage is never enumerated.

Public Article and Attachment projections advertise count-only `likes` and `shares`
collections backed by the Activities ledger. They intentionally do not enumerate Actor or
Activity members. Both use `OrderedCollection` for Axismundi representation consistency;
Mastodon's corresponding ActivityPub endpoints use unordered `Collection`, and the
ActivityPub vocabulary permits either. Human-facing liker and booster lists are separate
local UI/API projections, not members synchronized through S2S federation.
