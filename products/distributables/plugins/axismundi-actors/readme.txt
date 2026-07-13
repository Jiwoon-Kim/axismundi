=== Axismundi Actors ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.1
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
The identity URI (`/?ax_actor={uuid}`) is immutable; the `/@handle/` alias may
change with the username.

This is a pre-implementation scaffold. The design contract is locked in the
plugin's docs/ directory (SPEC, DATA-MODEL, ROUTING, SECURITY, PROJECTIONS,
PHASES); the repository, routing, and projection registry ship from Phase 1.

Not in this plugin: activity ledger, likes, JSON-LD, inbox/outbox, follow, HTTP
signatures, and remote fetch — those belong to Axismundi Activities and Axismundi
Federation, which attach to the identity and projection contracts defined here.

== Changelog ==

= 0.0.1 =
* Phase 0 — docs and scaffold. Locks the identity model (a shared wp_ax_identities
  registry plus a wp_ax_actors profile), the distinct identifiers, the actor URI vs
  mutable profile alias, and the projection registry contract. No behaviour ships yet.
* Design-review pass: canonical actor URI is the path form /actors/{uuid} (plain
  fallback /?ax_actor={uuid}); the UUID is the only immutable anchor while the local
  canonical URI is a rebuildable cache; the actor row is a 1:1 specialization keyed
  by identity_id (no separate actor id); preferred_username is unique; site actors
  read profile from bloginfo; the Axismundi_Actor value-object interface is frozen.
