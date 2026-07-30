=== Axismundi Forum ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.6.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: forum, community, activitypub, group, federation

Federated community boards for Axismundi: a Forum bound one-to-one to a managed Group Actor.

== Description ==

Axismundi Forum adds a community surface without creating a second identity,
activity, object, or reply system. A Forum is an `ax_forum` post bound to exactly
one local **managed Group Actor** administered by Axismundi Actors; the Group owns
identity, authority, and lifecycle, and the Forum consumes those APIs.

The binding is a dedicated 1:1 relation: a Forum binds at most one Group, and a
Group is bound by at most one Forum. Only a manager of the Group (per the Actors
authority kernel) may bind it, and unbinding or deleting a Forum removes the link
only — the Group Actor is never deleted.

This release includes the F1 local Topic baseline. An admitted `ax_topic` projects
as an ActivityStreams `Article` addressed to the bound Group, and a
Forum-owned entry table supplies the Topic index without duplicating object bodies.
The local policy is deliberately small: a Group manager selects either `open`
(anyone with Topic edit permission) or `managers` (Group managers only). Managers
may also pin or lock a Topic in Forum context. Membership, moderation, replies,
follow, announce, and federation remain later phases.

Not in this plugin: identity, handles, or Group lifecycle (Axismundi Actors); the
activity ledger and social relations (Axismundi Activities); object rendering
(Axismundi Object Projections); Note replies (Axismundi Note).

== Changelog ==

= 0.6.0 =
* Begins moving the community from the `ax_forum` post to the Group Actor that already is
  one. Adds `wp_ax_forum_settings`, keyed by Group identity, and migrates each bound Forum's
  posting and membership policy out of post meta into it. Adds `group_identity_id` to the
  membership projection and backfills it through the binding table.
* Nothing reads the new key yet, and no legacy column, table, post, or route is removed. The
  binding table and the `ax_forum` posts are what the migration is checked against, so they
  stay until the rekey is verified. DB schema 4.0.
* Still to come in this move: the API rekey from `$forum_post_id` to `$group_identity_id`,
  the Topic editor's "Forum" selector becoming a "Community" selector over manageable Groups,
  the Community settings UI moving into the Managed Group screen, and only then the removal
  of the CPT, the `/forum/` rewrite, and the binding table.

= 0.5.0 =
* A Forum root post is now published as an ActivityStreams `Article` rather than the `Page`
  Lemmy publishes. `Page` is a `Document` subtype, and `Document` is already how this site
  publishes every non-image/audio/video attachment, so `Page` filed a discussion thread
  beside a PDF in our own object model. See CONSTITUTION.md Article 13.
* Inbound admission stays lenient: a remote root post may be `Article` or `Page`, filterable
  via `axismundi_forum_root_object_types`. A bare `Note` is still refused, because a Note
  addressed to a Group is indistinguishable from a post that merely mentioned it.
* `audience` and `context` no longer carry the same URI. The Group is the audience — who the
  post is addressed to and who redistributes it — while `context` now names the thread and
  dereferences to an `OrderedCollection` attributed to the Group. Sharing one context across
  a whole Forum left nothing able to name an individual discussion.
* Replies inherit their parent's thread context (FEP-11dd) instead of carrying none.

= 0.4.0 =
* The bound Group Actor's profile is now the community page: it lists the Forum's Topics
  where an ordinary Actor lists an Activity timeline, so the federated address and the
  place people read are one surface rather than two. The avatar, header, name, summary,
  and Follow control above it remain the Actor's own and are never copied into the Forum.
* The Topic list resolves its Forum from either context — the `ax_forum` page or a bound
  Group profile — instead of one URL shape.
* Corrects `AXISMUNDI_FORUM_VERSION`, which had been left at `0.2.0`.

= 0.3.0 =
* Adds Forum membership as a projection of the Activities relation ledger. A Follow aimed
  at the bound managed Group is the evidence; the Forum's policy decides whether that is an
  admission or a request awaiting a manager. Local and remote followers are treated alike.
* The Accept is recorded before the projection is written, so a member is never admitted
  here while still pending in the ledger and on their own server.
* Membership is rebuildable: `axismundi_forum_rebuild_memberships()` replaces a Forum's whole
  projection from the ledger, pages rather than truncates, and sends nothing outward.
* Switching a Forum from manager approval to open admits everyone already queued and sends
  each of them one Accept. Tightening the policy is not retroactive.
* Moderation, replies, announce, and federation of Forum entries remain unimplemented.

= 0.2.0 =
* F1 baseline — adds the `ax_topic` CPT, local ActivityStreams root-post projection,
  `wp_ax_forum_entries`, a Forum Topic List dynamic block, and plugin-owned single
  Topic template.
* A Topic has one immutable Forum context once admitted. Removing a Topic removes
  only its entry; deleting a Forum removes its entries but leaves source Topics and
  its managed Group Actor intact.
* Adds the initial local admission policy: Group managers choose open posting or
  manager-only posting. The same manager relation controls Forum-context pinning
  and reply locking; locking projects `commentsEnabled: false` without altering the
  source object body.
* Membership, moderation, replies, follow, announce, and federation are not
  implemented by this release.

= 0.1.0 =
* F0 — the local Forum binding contract. Adds the `ax_forum` CPT and a dedicated
  `wp_ax_forum_bindings` join table with a UNIQUE key on each side, enforcing a
  one-to-one Forum ↔ managed Group Actor relation at the database layer.
* Binding routes authority through the Axismundi Actors manager kernel: only a
  manager of the Group may bind it, and a Person, remote Actor, or already-bound
  Group is rejected. Unbinding or deleting a Forum removes only the binding row and
  never tombstones the Group Actor.
* Adds a Forum editor meta box to select and bind an eligible managed Group, plus
  plugin-owned archive and single block templates. No topics, replies, follow,
  announce, or federation behavior yet.
