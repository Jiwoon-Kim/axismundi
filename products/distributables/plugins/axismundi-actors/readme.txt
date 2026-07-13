=== Axismundi Actors ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.8
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, identity, actor, federation

Identity registry for Axismundi: one immutable actor URI and one profile hub per identity.

== Description ==

Axismundi Actors gives every local person, the site itself, and (later) remote
federated actors **one stable identity record** and **one human profile hub**
(`/@handle/`), without collapsing that identity into the WordPress user account and
without owning the content it points at.

Each domain plugin keeps its own storage and screens; Actors holds identity and
wires each archive in as a **projection** (Posts, Media, Notes, …) under one actor.
The identity URI (`/actors/{uuid}`, with `/?ax_actor={uuid}` as the plain
fallback) is derived from an immutable UUID; the `/@handle/` alias may change
with the username.

The identity repository and actor profile routes are implemented. Projection
registration, admin publishing controls, and federation remain later phases; see
the plugin's docs/ directory for the living contracts.

Not in this plugin: activity ledger, likes, JSON-LD, inbox/outbox, follow, HTTP
signatures, and remote fetch — those belong to Axismundi Activities and Axismundi
Federation, which attach to the identity and projection contracts defined here.

== Changelog ==

= 0.0.8 =
* Tighten the local actor handle rule for mention interoperability: lowercase
  letters, numbers, and underscores only (^[a-z0-9](?:[a-z0-9_]{0,28}[a-z0-9])?$) —
  no hyphens or dots, because many servers parse mentions as a bare @\w+ and would
  split @kim-jiwoon into @kim. Candidate suggestions fold hyphens/spaces to
  underscores; registration validates the immutable handle without silently
  rewriting it. Collision suffixing uses _N. Remote preferredUsernames keep their
  own characters. UI copy updated.
* Design revision: avatar and header are two columns on wp_ax_actors
  (avatar_attachment_id / header_attachment_id), not a wp_ax_actor_media table —
  they are fixed 0..1 slots and the site/Group/Service actors have no WP_User.

= 0.0.7 =
* Phase 4a — actor activation & profile management. Adds a Users > Actor Profile
  screen with the activation wizard (choose a handle from user_nicename / nickname /
  free input, normalized + reserved/dup checked, registered once and then permanent;
  choose Internal or Public), a read-only summary panel on profile.php / user-edit,
  an Actor status column on users.php, and a Settings > Actor Profile screen for the
  site actor's type (Application / Organization) and visibility.
* Activation is a dedicated nonce'd, capability-checked POST action — never mixed
  into the profile.php save — so registering the immutable handle and publishing is
  one explicit act. No forced login redirect. Avatar, header, and translations are
  shown only as notices here (Phase 4b/4d) and are not saved yet.

= 0.0.6 =
* Remove the premature built-in Posts projection. The actor profile's primary
  surface is an activity feed (owned by Axismundi Activities); Actors ships no
  built-in projection and renders header-only until a domain plugin registers one.
  The core-post projection, when added, is `articles` (not `posts`) and is owned by
  its own registrar. The registry, hook, and navigation block are unchanged.
* Lock two design contracts ahead of admin integration: the Actor handle is
  independent of the WordPress profile name and the author/media archive URLs
  (handle changes never move `/author/…` or `/media/author/…`); and the handle is a
  reservable routing alias with a future alias-history table — Person handles stay
  reserved after a user is deleted (re-signup is tombstone recovery, not handle
  reuse), while the site actor may change its handle with a reserved redirect.

= 0.0.5 =
* Phase 3 projection registry: request-local public registration API and hook,
  deterministic priority ordering, visibility/URL/count callbacks, graceful
  disappearance on plugin deactivation, and callback error isolation.
* Adds the built-in Posts projection and the dynamic actor-projections navigation
  block. Actors links to the core author archive but never owns its query.

= 0.0.4 =
* Phase 2.1 — handle immutability and deferred registration. A user's actor is now
  created handle-less; the handle is registered once, at explicit activation, and is
  then immutable (register_handle replaces the old mutable set_handle). Handle
  candidates come from user_nicename / nickname, never user_login.
* Public exposure now also requires a registered, locked handle: a public actor with
  no handle stays 404 to anonymous viewers. Actor activation is separate from the
  WordPress "Anyone can register" account setting.
* Schema v2: preferred_username is nullable and a handle_locked_at column is added,
  with a one-off upgrade that locks any pre-existing handled actor. The same remote
  preferredUsername is still allowed across domains; verified per-domain uniqueness
  is deferred to the WebFinger/Federation phase.

= 0.0.3 =
* Phase 2 actor profiles: canonical /actors/{uuid} and mutable /@handle/ routes,
  plus plain-query fallbacks that work without pretty permalinks.
* Adds a theme-overridable block template and the dynamic axismundi/actor-profile
  block. Local Person and site profile fields are read live; email is never
  rendered. Internal, disabled, and tombstoned actors return 404 to public
  viewers while the linked owner and administrators may preview.
* Keeps collision-resolved preferred usernames and local routing keys identical,
  preventing two local actors from minting the same /@handle/ alias.

= 0.0.2 =
* Phase 1 — the actor repository. Creates wp_ax_identities + wp_ax_actors (dbDelta,
  schema-versioned); the actor row is a 1:1 specialization keyed by identity_id with
  a logical FK only (no physical FOREIGN KEY / CASCADE — the tombstone contract).
  Immutable UUID + rebuildable /actors/{uuid} URI; create / get_by_uuid / get_by_uri
  / get_for_user / ensure_for_user; a read-only Axismundi_Actor value object.
* Handles: preferred_username is not globally unique (remote actors share handles);
  a local_handle_key enforces one handle per local actor while remote duplicates are
  allowed. Activation always seeds the site actor and, only when the activating user
  is a valid admin, the site-owner Person (skipped on CLI). Deleting a user
  tombstones its identity instead of deleting it.

= 0.0.1 =
* Phase 0 — docs and scaffold. Locks the identity model (a shared wp_ax_identities
  registry plus a wp_ax_actors profile), the distinct identifiers, the actor URI vs
  mutable profile alias, and the projection registry contract. No behaviour ships yet.
* Design-review pass: canonical actor URI is the path form /actors/{uuid} (plain
  fallback /?ax_actor={uuid}); the UUID is the only immutable anchor while the local
  canonical URI is a rebuildable cache; the actor row is a 1:1 specialization keyed
  by identity_id (no separate actor id); preferred_username is unique; site actors
  read profile from bloginfo; the Axismundi_Actor value-object interface is frozen.
