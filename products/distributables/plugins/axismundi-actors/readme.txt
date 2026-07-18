=== Axismundi Actors ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.34
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, identity, actor, federation

Identity registry for Axismundi: one immutable actor URI and one profile hub per identity.

== Description ==

Axismundi Actors gives every local person, the site itself, and cached remote
federated actors **one stable identity record** and **one human profile hub**
(`/@handle/`), without collapsing that identity into the WordPress user account and
without owning the content it points at.

Each domain plugin keeps its own storage and screens; Actors holds identity and
wires each archive in as a **projection** (Posts, Media, Notes, …) under one actor.
The identity URI (`/actors/{uuid}`, with `/?ax_actor={uuid}` as the plain
fallback) is derived from an immutable UUID; the `/@handle/` alias is registered
once during Actor activation and does not track later WordPress username changes.

The identity repository, actor profile routes, projection registration, profile
administration, local WebFinger/NodeInfo, and bounded remote Actor discovery are
implemented. Activity delivery and full federation remain later phases; see docs/.

Not in this plugin: activity ledger, likes, local JSON-LD serialization,
inbox/outbox processing, follow, HTTP signatures, background refresh/backoff, and
delivery. Those belong to Axismundi Activities and Axismundi Federation.

== Changelog ==

= 0.0.34 =
* Add authenticated cached-Actor search and fully-qualified ActivityStreams Mention names for Core editor mentions.

= 0.0.33 =
* Add a permission-checked repository API for the local Actor Follow-collection
  disclosure enum. The stable collection address remains separate from whether its
  count or members may be disclosed.

= 0.0.32 =
* Install the actor, handle, WebFinger, and NodeInfo routes whenever they are found
  missing from the rewrite table, instead of once per version counter. A counter records
  an intent to flush and then burns itself whether or not the flush persisted, so a rule
  that never reached the table stayed missing until someone saved permalinks by hand.
  This is self-healing for any cause, including a ZIP-replace update that never fires the
  activation hook and a host that discards the write.
* A changed rule set no longer needs a manual counter bump to take effect. Retries are
  limited to once an hour, and sites on plain permalinks are untouched.

= 0.0.31 =
* Open the identity registry to the non-actor kinds it already reserved (collection,
  folder, media, activity, place), so an owning plugin can register a shared folder or
  collection identity through the repository API instead of writing the table directly.
* Refuse actor identities from that API: an actor identity without its profile row is an
  orphan, and actors stay creatable only through their own transactional path.
* No schema change; the object_kind column and its kinds were already specified.

= 0.0.30 =
* Recover due remote image-cache rows when plugin replacement or deactivation cleared
  their one-shot cron worker, instead of leaving them permanently pending.
* Show due-row and next-worker diagnostics on the Remote Actors administration screen.
* Keep successful remote images until a verified Actor update changes their source URI;
  ready assets no longer refresh on a timer.

= 0.0.29 =
* Queue a first-time NodeInfo cache fill when a cached remote Actor participates in
  federation without an instance row; never delay Inbox processing for NodeInfo.
* Add a cached-only, complete-document repository gate for verified Update(Actor)
  Activities. Ordinary Follow and Accept traffic never refreshes Actor snapshots.
* Clarify that the administrator Fetch Actor action ensures caches exist and remains
  the explicit recovery path.

= 0.0.28 =
* Add search, total counts, and pagination to the cached remote Actor screen so older
  imported or previously discovered Actors are not hidden behind the recent 50-row window.

= 0.0.27 =
* Add a remote Actor administrator-detail action seam so companion social plugins can
  provide relationship controls without moving relationship state into Actors.

= 0.0.26 =
* Require the Contributor-level `edit_posts` capability for self-service Actor activation
  and policy management. Keep administrator management through `manage_options`.
* Keep the existing Users submenu for administrators while exposing Actor Profile under the
  Contributor's accessible core Profile menu.

= 0.0.25 =
* Add the permission-checked `axismundi_actors_set_local_policy()` repository API so
  social-domain plugins can manage local follower approval without direct Actor-table writes.

= 0.0.24 =
* DB v10b — add wp_ax_identity_relations for alsoKnownAs and movedTo claims. Remote
  discovery accepts JSON-LD string, object, and list forms, then stores each safe
  HTTPS target as observed/unverified under a compound identity/type/URI key.
* Preserve relation evidence across partial refreshes and never let an inbound Actor
  document downgrade a verified or rejected decision. A repository seam allows the
  future Federation plugin to promote or reject an observed claim after reciprocal
  identity or Move verification.
* Show cached identity relations and their verification state in the Remote Actors
  inspector. Actors records claims only; it does not redirect, migrate followers, or
  process Move activities.

= 0.0.23 =
* DB v10a — the Actor public-key keyring (wp_ax_actor_keys) and remote fetch state
  (wp_ax_actor_fetch_state). Discovery now captures each remote Actor's declared
  publicKey (owner-checked so a payload cannot smuggle a foreign key) instead of
  discarding it, storing it as a row keyed by key URI so key rotation retires the old
  key as history rather than overwriting it. A keyless refresh never wipes a known key.
  A local private key is reserved as a reference only (private_key_ref) — never stored
  in plaintext here.
* Record fetch bookkeeping on each discovery: payload hash, ETag / Last-Modified
  validators, a one-day refresh horizon on success, and a capped exponential backoff on
  failure that keeps the last good snapshot. This is substrate for a future background
  refresher; Actors performs no scheduling, signature verification, or delivery — those
  stay in Axismundi Federation.

= 0.0.22 =
* Revise processor v2 defaults for lower compute cost: avatar caps are now
  96/192/400px, while header width caps remain 640/1024px. Images are never
  upscaled, and the front-end profile limits the displayed header height to 500px.
* Make WebP candidate generation an administrator-controlled, default-off option in
  Users > Remote Actors. A setting change queues stale mappings for asynchronous
  regeneration instead of doing image work in the request.
* Preserve old processor trees while referenced and teach render, purge, and GC paths
  to resolve each row's processor version, allowing v1 to v2 migration without broken
  images or a synchronous cache rebuild.

= 0.0.21 =
* Add DB v9 remote Actor avatar/header caching in one mapping table and a
  content-addressed, processor-versioned uploads tree. Fetches are bounded,
  asynchronous, stale-while-revalidate, signature-validated, and never run from a
  render path; originals are discarded after local derivatives are produced.
* Generate avatar caps at 96/192/384px and header width caps at 640/1024px without
  upscaling. Preserve header aspect ratio, omit duplicate small-source outputs, and
  select WebP only when it is actually smaller than normalized JPEG/PNG.
* Add nonce-protected actor/instance/all cache inspect and purge controls, plus an
  administrator-only, noindex/no-cache remote profile preview that reuses the local
  actor-profile template and serves cached images only (never remote hotlinks).

= 0.0.20 =
* DB v8 — follower / discovery policy axes on wp_ax_actors: published_at,
  manually_approves_followers, discoverable, indexable, and
  follow_collections_visibility. These are independent axes, not one enum:
  internal|public stays lifecycle, while follow approval, discovery/indexing, and
  collection visibility are separate.
* Preserve the tri-state on remote actors: a policy the remote Actor JSON did not
  declare is stored as NULL (unreported) and never conflated with an explicit false;
  a refresh only writes a boolean the payload actually reported. The administrator
  inspector shows "not reported" distinctly from yes/no. The default posting audience
  is not stored here — it belongs to the Activity plugin.
* Documented and locked the remote avatar/header binary cache contract implemented in
  the following release: content-addressed storage, one cache table, asynchronous
  stale-while-revalidate, and strict fetch limits.

= 0.0.19 =
* DB v7 — normalize inbox, outbox, followers, following, featured, and sharedInbox
  into wp_ax_actor_endpoints. One Actor has at most one URI per role, while the URI
  hash is deliberately non-unique because many Actors may share one sharedInbox.
* Make remote Actor refresh replace its endpoint set atomically with the snapshot,
  removing stale optional roles. The administrator inspector now shows normalized
  endpoints alongside verified addresses and raw JSON.
* Backfill endpoints from legacy inbox/outbox columns and cached Actor payloads,
  verify every legacy value, then remove the old columns. A failed migration remains
  retryable and does not record DB v7.

= 0.0.18 =
* Add Users > Remote Actors for manage_options administrators: resolve an acct,
  /@handle profile URL, or canonical Actor URL through the existing safe discovery
  services, then inspect normalized identity data, verified addresses, raw Actor
  JSON, and the associated instance/NodeInfo cache.
* Add recent remote-Actor and instance cache tables plus a nonce-protected refresh
  action. Profile aliases are resolved through WebFinger; arbitrary canonical URLs
  must return an exactly matching Actor id.
* Keep the screen diagnostic and read-only apart from explicit fetch/refresh. It
  does not follow, deliver, mutate remote state, or bypass the existing SSRF limits.

= 0.0.17 =
* DB v6 — the remote instance / NodeInfo host ledger (wp_ax_instances). A host's
  software, version, and registration policy are cached once per host (keyed on a
  host hash), never duplicated across the actors that live there.
  axismundi_actors_discover_remote_instance() fetches /.well-known/nodeinfo and the
  NodeInfo 2.1/2.0 document over the same bounded, private-network-safe HTTPS helper;
  a failed fetch still records the attempt (fetch_status=error) for later backoff.
  Discovering a remote actor caches its host once (best-effort, once per day).
* Background refresh/backoff scheduling, delivery, and moderation policy about a host
  remain out of this increment (Federation plugin / a separate moderation layer).

= 0.0.16 =
* Add bounded remote Actor discovery: HTTPS WebFinger resolves an ActivityStreams
  self URI, validates the Actor id/type/endpoints, and upserts a remote identity
  snapshot plus its verified acct address.
* Harden the network boundary with WordPress safe-URL validation, private-network
  rejection, strict content types, no redirects, a one-megabyte response limit,
  exact WebFinger-self/Actor-id matching, and tombstone non-resurrection.
* Keep instance NodeInfo caching, background refresh/backoff, JSON-LD transforms,
  signatures, inbox processing, and delivery out of this increment.

= 0.0.15 =
* Local NodeInfo 2.1. Advertises this site's software, protocol, registration policy,
  and usage at /.well-known/nodeinfo (discovery) and /nodeinfo/2.1 (document) — no
  table, built from WP options and live counts. usage.users.total counts only public
  local actors with a registered handle; openRegistrations mirrors the WordPress
  setting. software name/version, protocols, services, and metadata are filterable so
  the future Federation plugin can own the real values.

= 0.0.14 =
* Add local WebFinger discovery at `/.well-known/webfinger` with a plain-query
  fallback, strict host matching, public-actor-only resolution, and JRD output.
* Record locally authoritative `acct:` rows in the address ledger and keep the
  ActivityStreams `rel=self` link as an explicit Federation-plugin extension until
  the canonical Actor URI actually serves ActivityStreams JSON.
* Enforce the documented fail-closed topology policy: single-site, subdomain, and
  mapped-domain sites are enabled; ambiguous subdirectory multisite is disabled.

= 0.0.13 =
* Prefer an explicitly authored profile translation matching a local Person's
  WordPress profile language on their human-facing Actor page.
* Keep the ActivityStreams scalar language controlled separately by the Actor's
  `default_language`.

= 0.0.12 =
* DB v5 — the actor address ledger (wp_ax_actor_addresses): a routing + history table
  for local handles. Install backfills each handle as a primary local_handle address;
  register_handle records it and now rejects a handle reserved to a different actor;
  reserve_former_handle() is the infrastructure for a future site-actor rename / admin
  recovery so a retired handle is never recycled. Address hashing is namespaced, so a
  reserved former handle blocks the same string while acct: addresses never collide
  with handles. The version is recorded only after the table and its unique index
  exist.
* WebFinger acct: policy decided fail-closed: enabled for single-site / subdomain /
  mapped-domain; explicitly disabled for subdirectory multisite (Actor URI and profile
  still work). acct: rows and the endpoint come in the WebFinger increment.

= 0.0.11 =
* Phase 4d / DB v4 — add optional multilingual Actor profile text (`name`,
  `summary`, and long `content`) with normalized BCP-47 language tags.
* Keep WordPress user and site profile values live: translation rows are created
  only when explicitly authored, and empty translations fall back to Core data.
* Add language editing to Person and Site Actor management screens and resolve
  profile output through exact/base/default/site/user-language fallbacks.

= 0.0.10 =
* Phase 4c — use a local Person actor's avatar as their WordPress avatar. When a
  user's actor has an avatar image, WordPress avatars (comments, admin, author
  blocks) show it instead of the Gravatar, via the get_avatar_data filter. Default
  on; a site can turn it off with the axismundi_actors_use_actor_avatar filter. Only
  Person actors are affected — the header image stays Actor-profile-only, and site /
  remote actors are not WordPress users. No schema change.

= 0.0.9 =
* Phase 4b — actor avatar & header images. Schema v3 adds avatar_attachment_id and
  header_attachment_id to wp_ax_actors (two fixed slots, no separate table); the
  version is only recorded once the columns exist. The Actor Profile and Settings >
  Actor Profile screens gain a core Media picker (assets loaded only there) for both
  the current user's Person actor and the site actor. The public profile renders the
  header cover and an avatar with srcset via wp_get_attachment_image; avatar falls
  back to the core avatar / site icon, the header shows nothing when unset.
* A shared setter validates that the image is an attachment, an image MIME type, and
  editable by the current user, then runs the axismundi_actors_can_use_profile_media
  filter (the seam for a future Media Library private/sensitive policy). Removing an
  image only nulls the reference, and deleting an attachment auto-releases any actor
  avatar/header that pointed at it — the file is never deleted by this plugin.

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
