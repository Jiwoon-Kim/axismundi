# Axismundi Actors — Projection Registry

> Status: **Living specification. Pre-implementation (API lock).**
> The projection registry is the seam between Actors (identity + navigation) and
> every domain plugin (Posts, Media, Notes, …). It is frozen here before code.

## 1. What a projection is

A **projection** is one domain's view of an actor: a labelled link (and optional
count) to that actor's archive in another plugin. Actors renders the actor hub
`/@{username}/` and a navigation of its projections; it does **not** run the
projection's query or own its content.

```
Actor hub  /@alice/
├─ Posts        → /author/alice/                (core; registered by Actors itself)
├─ Media        → /media/author/alice/          (Axismundi Media Library)
├─ Notes        → /notes/alice/                 (Axismundi Notes, later)
├─ Collections  → …                             (later)
├─ Folders      → …                             (later)
└─ Activity     → /@alice/activity/             (Axismundi Activities, later)
```

Actors coordinates only **URL, label, visibility, and order**. The domain plugin
owns the data, the template, and the permission logic behind the link.

## 2. Registration API

```php
axismundi_actors_register_projection(
    string $id,          // stable slug: 'posts' | 'media' | 'notes' | …
    array  $args
);
```

`$args`:

| key | type | required | meaning |
|---|---|---|---|
| `label` | string | yes | Human, translated nav label ("Media"). |
| `url_callback` | callable | yes | `fn( Axismundi_Actor $actor ): string` — the projection archive URL for this actor, or `''` to omit. |
| `visible_callback` | callable | no | `fn( Axismundi_Actor $actor, int $viewer_id ): bool` — default `true`. Return `false` to hide for this actor/viewer (e.g. no public media). |
| `count_callback` | callable | no | `fn( Axismundi_Actor $actor ): ?int` — a badge count, or `null` for none. Must already be visibility-scoped to the viewer. |
| `priority` | int | no | Sort order, default `10`. Lower first. |

Registration happens on an Actors hook so load order is deterministic:

```php
add_action( 'axismundi_actors_register_projections', function () {
    axismundi_actors_register_projection( 'media', [
        'label'            => __( 'Media', 'axismundi-media-library' ),
        'url_callback'     => 'axismundi_media_actor_projection_url',
        'visible_callback' => 'axismundi_media_actor_projection_visible',
        'count_callback'   => 'axismundi_media_actor_projection_count',
        'priority'         => 20,
    ] );
} );
```

## 3. Contract rules

1. **Actors owns no query.** Every callback is provided by the registering plugin.
   Actors calls them and lays out the result; it never reaches into another
   plugin's tables.
2. **`id` is unique and stable.** Re-registering the same `id` **replaces** the
   prior definition (last writer wins) and emits a `_doing_it_wrong` notice — two
   plugins must not silently fight over `posts`. `posts` is registered by Actors
   itself and may be overridden intentionally.
3. **Ordering** is by `priority` then registration order; ties are stable. `posts`
   defaults to `priority = 10`, `media` to `20`.
4. **Visibility is per actor and per viewer.** A projection with no URL
   (`url_callback` returns `''`) or `visible_callback => false` is omitted from the
   nav entirely — it is never rendered as a dead link. An `internal`/tombstoned
   actor exposes no projections publicly (SECURITY).
5. **Counts are optional and pre-scoped.** A `count_callback` must return a number
   already filtered to what `$viewer_id` may see — Actors never re-filters. Return
   `null` when a count is unavailable or would leak.
6. **Graceful absence.** If a plugin that registered a projection is deactivated,
   its projection simply disappears (the hook no longer fires). Actors must not
   persist projection definitions.

## 4. The built-in `posts` projection

Actors registers `posts` itself so the hub works with zero domain plugins:

- `label` → `Posts`
- `url_callback` → the core author archive for the actor's `local_user_id`
  (`get_author_posts_url()`), or `''` for actors with no user (site/remote).
- `visible_callback` → the actor has a `local_user_id` and the author has ≥1
  readable published post.
- `priority` → `10`.

## 5. What projections are **not**

- Not separate actors. `/author/alice/`, `/media/author/alice/`,
  `/notes/alice/` are all views of **one** actor URI. JSON-LD may later expose them
  as multiple `url` links on a single `Person`, never as multiple actors.
- Not owned by Actors. Actors stores no projection rows; the registry is in-memory,
  rebuilt each request from the hook.
- Not a content API. A projection yields a URL + label (+ optional count) — not
  posts, not media, not markup.
