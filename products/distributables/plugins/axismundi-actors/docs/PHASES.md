# Axismundi Actors — Phases

> Status: **Living implementation plan. Pre-implementation (Phase 0 in progress:
> docs lock).** Entry · build · acceptance · non-goals per phase. Cross-refs:
> SPEC, DATA-MODEL, ROUTING, SECURITY, PROJECTIONS.

## Phase 0 — Docs & scaffold *(in progress)*

**Build:** the six docs (SPEC, DATA-MODEL, ROUTING, SECURITY, PROJECTIONS,
PHASES) locking the `actor_uri` / identity format and the projection registry
contract; plugin scaffold (main file header, ABSPATH guard, version constant, no
functional code yet).
**Acceptance:** frozen after the design-review pass — the identity-registry split;
identifiers `local_user_id` / `identity.id` (= actor PK) / `identity.uuid`
(immutable anchor) → `actor_uri` = **`/actors/{uuid}`** (plain fallback
`/?ax_actor={uuid}`) / `profile_url` (`/@handle/`); the actor row as a 1:1
specialization keyed by `identity_id` (no separate `actor.id`); `uuid` immutable
while local `canonical_uri` is a rebuildable cache; the `Axismundi_Actor` value
object and the projection registry signature. No schema or route is implemented.
**Non-goals:** any table creation or routing code.

## Phase 1 — Repository

**Entry:** Phase 0.
**Build:** `wp_ax_identities` + `wp_ax_actors` via dbDelta + schema-version option;
`identity_id` as the actor PK with a **logical** FK only (no physical `FOREIGN KEY`
/ `CASCADE` — it would fight the tombstone contract); integrity enforced by the
repository (identity exists + `object_kind='actor'`, wrapped in a transaction);
create / `get_by_uuid` / `get_by_uri` / `get_for_user` / `ensure_for_user`; immutable
UUID + rebuildable `canonical_uri`; always-seed the site actor (idempotent) +
conditional site-owner Person seed; `deleted_user` → identity `tombstone`.
**Acceptance:** UUID and `actor_uri` unchanged after a handle change (and unchanged
across a simulated domain move); a second actor cannot be created for the same user
(unique `local_user_id`); a **local** handle collision is blocked while the **same
remote handle is allowed on multiple remote actors**; CLI activation succeeds with
**no** Person seed (only the site actor); user delete tombstones (never deletes); an
audit finds no actor row without its identity; reactivation creates no duplicate
seed; deactivate/reactivate preserves rows.
**Non-goals:** any public page; projections; admin UI.

## Phase 2 — Actor profile page

**Entry:** Phase 1.
**Build:** `/actors/{uuid}` canonical identity endpoint (+ `/?ax_actor={uuid}` plain
fallback) + `/@{username}/` alias (rewrite + plain fallback, reserved-handle guard);
a plugin block template; actor
header (live-read name/avatar/bio/type badge) + projection navigation region.
**Acceptance:** `internal`/`disabled`/`tombstone` actors 404 for public viewers
(owner/`manage_options` preview only); `public` renders; a username change moves
`/@handle/` while the identity URI is unchanged; works with pretty permalinks off.
**Non-goals:** JSON-LD; sub-routes under the handle.

## Phase 3 — Projection registry

**Entry:** Phase 2.
**Build:** `axismundi_actors_register_projection()` + the
`axismundi_actors_register_projections` hook; the built-in `posts` projection;
ordering / visibility / count resolution; duplicate-id replacement with a
`_doing_it_wrong` notice.
**Acceptance:** `posts` appears with a correct count and is hidden for actors with
no readable posts; a second plugin can add a projection purely via the public API
(no Actors edit); deactivating that plugin removes its projection; ordering follows
`priority`.
**Non-goals:** Media/Notes projections (owned by those plugins).

## Phase 4 — Admin integration

**Entry:** Phase 3.
**Build:** a public/internal toggle + site-actor type (`Application`/`Organization`)
on the relevant admin screens; a "Actor profile" row link on Users; view/edit
capability wiring; the `ax_actors_site_owner_user_id` setting.
**Acceptance:** publishing is explicit and per-actor; no bulk publish of existing
users; email never appears in any actor screen or link.
**Non-goals:** managers table; bulk tools.

## Phase 5 — Media integration (cross-plugin)

**Entry:** Phase 4 + Media Library Phase 5 readiness.
**Build:** Media Library registers `media` (and `folders`) projections; Collection
and Shared Folder owner/member keys are implemented on `actor_uri` (with
`local_user_id` as the nullable optimization pointer), using `ensure_for_user()`.
**Acceptance:** a collection/shared-folder membership survives a username change
(keyed on `actor_uri`, not the handle); an `internal` actor is a valid membership
key while its profile stays 404.
**Non-goals:** federation of memberships (Axismundi Federation, later).

## Out of scope for this plugin (separate packages)

Activity ledger, Like/Undo, actor activity page → **Axismundi Activities**.
Note/Question CPT + projection → **Axismundi Notes**. JSON-LD transformer,
inbox/outbox, HTTP signatures, discovery, delivery, remote actor fetch/cache →
**Axismundi Federation**. The identity registry, `actor_uri`, and projection
contract defined here are the substrate those plugins attach to without a rewrite.
