# Axismundi Actors — Projection Registry

> Status: **Living specification. Phase 3 implemented.**
> The projection registry is the seam between Actors (identity + navigation) and
> every domain plugin (Posts, Media, Notes, …). It is frozen here before code.

## 1. What a projection is

A **projection** is one domain's view of an actor: a labelled link (and optional
count) to that actor's archive in another plugin. Actors renders the actor hub
`/@{username}/` and a navigation of its projections; it does **not** run the
projection's query or own its content.

```
Actor hub  /@alice/
├─ Activity     → the profile feed             (Axismundi Activities; primary surface)
├─ Articles     → /author/kimjiwoon96/         (core post archive; a future registrar)
├─ Media        → /media/author/kimjiwoon96/   (Axismundi Media Library)
├─ Notes        → /notes/kimjiwoon96/          (Axismundi Notes, later)
├─ Collections  → …                            (later)
└─ Folders      → …                            (later)
```

The handle (`@alice`) and the WordPress archive slug (`kimjiwoon96`) are independent
(ROUTING §0.1); a projection points at the domain plugin's own URL, not at `@alice`.

Actors coordinates only **URL, label, visibility, and order**. The domain plugin
owns the data, the template, and the permission logic behind the link.

## 1.5. The actor value object

Every callback receives a read-only `Axismundi_Actor` — the same object the
repository returns. Its public interface is **frozen** here so domain plugins never
read the tables directly:

```php
interface Axismundi_Actor {
    public function get_uuid(): string;              // immutable anchor
    public function get_uri(): string;               // actor_uri = /actors/{uuid} (or remote canonical)
    public function get_profile_url(): string;       // /@handle/ after registration; '' before
    public function get_preferred_username(): string; // immutable after registration; '' before
    public function get_local_user_id(): ?int;       // null for site / remote
    public function get_type(): string;              // Person | Organization | Application | Service | Group
    public function get_scope(): ?string;            // site | user (local); null (remote)
    public function get_status(): string;            // internal | public | disabled | tombstone
    public function is_local(): bool;
    public function get_display_name(): string;      // resolved live (user/bloginfo) or remote snapshot
}
```

A projection typically uses `get_local_user_id()` (to build a WordPress archive URL)
or `get_uri()` (federation), and `get_status()` / `is_local()` for visibility. Email
is deliberately absent — it is never exposed (SECURITY §2).

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
   plugins must not silently fight over an id. Actors registers none itself.
3. **Ordering** is by `priority` then registration order; ties are stable. By
   convention `activity` sorts first (low priority), then `articles ≈ 20`,
   `media ≈ 20`, etc.
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
7. **Callback isolation.** A callback exception omits that projection and fires
   `axismundi_actors_projection_error`; a broken optional integration must not take
   down the actor profile hub.

## 4. Actors ships **no** built-in projection

The actor profile's **primary surface is an activity feed**, not a post list — the
feed is owned by **Axismundi Activities** (a separate plugin), which registers it as
the default projection when present. Everything else is a domain-registered tab:

```
Actor header
[Activity]  [Articles]  [Notes]  [Media]        ← all registered by their own plugins
 └ primary   └ core post  └ Notes   └ Media Library
   (Activities) archive
```

- With **no** domain plugin active, an actor renders **header-only** (an empty
  projection nav). Actors never invents a placeholder link or queries another
  plugin's content.
- **Articles** (the core WordPress `post` author archive) is a future
  domain registration — its slug is **`articles`**, not `posts`, and it is *not*
  owned by Actors. (An earlier build shipped a built-in `posts` projection; it was
  removed as premature — the profile is activity-first, and Actors owning a
  post-list decision violates the identity-only boundary.)
- Actors' only job here stays URL / label / visibility / order coordination.

## 5. What projections are **not**

- Not separate actors. `/author/alice/`, `/media/author/alice/`,
  `/notes/alice/` are all views of **one** actor URI. JSON-LD may later expose them
  as multiple `url` links on a single `Person`, never as multiple actors.
- Not owned by Actors. Actors stores no projection rows; the registry is in-memory,
  rebuilt each request from the hook.
- Not a content API. A projection yields a URL + label (+ optional count) — not
  posts, not media, not markup.
