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
- `name` is reduced to plain text; `content` / `summary` pass `wp_kses_post`.
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

## Built-in Core Post transformer (0.0.2)

`core-post-article` supports only the core `post` post type and emits `Article` with a
stable `/?p={ID}` id, human permalink `url`, Actor `attributedTo`, title, rendered HTML,
manual excerpt, and published/updated timestamps. It requires a public user Actor, or a
deliberately public site Actor fallback. It never creates an Actor during rendering.

`axismundi_op_post_object_uri` is the compatibility seam for pre-existing object ids;
`axismundi_op_post_actor_uri` is the seam for another Actor provider. A future official
ActivityPub adapter must use the former to retain that plugin's per-post legacy choice.

## First-party Media Library adapter (0.0.4)

When Axismundi Media Library is active in Independent mode, Object Projections registers
an attachment transformer without requiring Media Library to know ActivityStreams. It
emits Image, Video, Audio, or Document with stable `/?attachment_id={ID}` identity, the
human attachment page as `url`, Actor attribution, sensitivity, canonical license, and a
nested media Link. Images use Media Library's bounded derivative service by default.

Visibility is anonymous and cache-safe: public and unlisted, ungated attachments only.
Owner/editor bypasses are deliberately not used. The adapter consumes public Media Library
functions and never queries its tables or private metadata schema.
