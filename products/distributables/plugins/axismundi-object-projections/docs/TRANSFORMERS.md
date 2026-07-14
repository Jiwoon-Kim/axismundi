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
