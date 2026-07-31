=== Axismundi Forum ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.9.10
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: forum, community, activitypub, group, federation

Federated communities for Axismundi, owned directly by managed Group Actors.

== Description ==

Axismundi Forum adds discussion capability to a local **managed Group Actor**. The
Group is the community: its Actor profile is the one public page, and Axismundi
Actors remains the owner of its identity, profile, handle, lifecycle, and manager
delegation. Forum owns only the Group-keyed settings and projections needed for
discussion.

Every public managed Group is a community. Managers choose its Topic posting and
membership policy on the Group's Managed Groups administration screen. An admitted `ax_topic`
projects as an ActivityStreams `Article` addressed to the Group; the Forum entry
table indexes Topics without duplicating their bodies. Topics use a resolvable
per-thread `context`; replies are Notes with `inReplyTo` and inherit that context.

There is no `ax_forum` post type, `/forum/` page, or Group-to-Forum binding. A
Group's default Forum policy is available without a settings row; a row is created
only when a manager changes that policy. Forum never changes or deletes its Group Actor.

Not in this plugin: identity, handles, Group lifecycle, or manager delegation
(Axismundi Actors); the activity ledger and social relations (Axismundi Activities);
object rendering (Axismundi Object Projections); Note replies (Axismundi Note).

== Changelog ==

= 0.9.10 =
* Keeps every Group-directed Reply addressed primarily to that Group, carrying
  `Public` in `cc` for threadiverse validation. The Group Announce, not the
  author Reply, is the public community representation.

= 0.9.9 =
* Treat a direct Topic submission as Group-addressed when the Group is in
  either `to` or `cc`, matching Reply handling and keeping future address
  changes out of the author's profile feed and public outbox.

= 0.9.8 =
* Makes a public local-community Reply's embedded Create Lemmy-valid before the
  Group Announce redistributes it: `Public` is primary, with the Group and
  parent author in `cc`. Member-only communities remain Group-addressed.

= 0.9.7 =
* Sends a Reply to a remote Lemmy community with `Public` in `to` and the
  community plus parent author in `cc`, while preserving the Group `audience`
  and keeping the Reply out of the author's profile and public outbox.

= 0.9.6 =
* Recognizes a remote Group named in a parent Lemmy comment's `cc` as the reply
  destination, so a local reply is addressed back to that community instead of
  falling back to an unrelated public reply.

= 0.9.5 =
* Sends a local Note reply to a cached remote Group directly to that Group, with
  the Group as `audience` and public routing required by Lemmy. The remote Group
  remains responsible for its own community Announce; the direct submission does
  not leak into the author's profile feed or public outbox.

= 0.9.4 =
* Makes public local Group Topic submissions compatible with Lemmy's public-object
  validation without placing the direct Person Create or Update in the author's
  profile feed, public outbox, or follower delivery. The Group Announce remains
  the community's public representation.

= 0.9.3 =
* Treats a managed-Group manager as an invariant derived moderator throughout
  the API as well as the Members screen; it cannot acquire or lose a redundant
  explicit moderator row.

= 0.9.2 =
* Labels local managed-Group delegates as `Moderator (manager)` in Members and
  does not offer a redundant explicit moderator Add/Remove transition for them.

= 0.9.1 =
* Addresses federated moderator `Add` and `Remove` activities to `Public` and
  the community, as required by Lemmy's collection-moderation protocol.

= 0.9.0 =
* Gives a Topic its own page. Forum's single-Topic template had lost its loader when the
  `ax_forum` post type was removed, so every Topic silently rendered through the theme's
  ordinary post template — Core comments instead of federated replies, and no sign of the
  community. The template is registered again and now lays out the Topic beside its community.
* Adds the Community Card block: the Group a Topic belongs to, with its identity and Follow
  control. Every value is read from Actors at render time; Forum stores the Group identity
  and nothing about the Group.
* Replies received from other servers now appear on the Topic they answer, through Object
  Projections' new replies block.

= 0.8.6 =
* Keeps discovery reasons in the Community result list rather than copying them
  into the selected Community field.

= 0.8.5 =
* Makes direct submissions to remote threadiverse Groups compatible with Lemmy's
  public-object validation while retaining Group-only transport delivery.

= 0.8.4 =
* Fixes the Community picker on a new Topic: it can search known Group Actors before
  WordPress has assigned the draft a post ID.

= 0.8.3 =
* Adds a per-entry Group distribution ledger, so every effective Topic lifecycle
  activity is retained for later withdrawal.
* Redistributes edits to visible Topics as Group `Announce(Update)` activities.
* Withdrawing a visible Topic undoes every active Group distribution before returning
  the Topic to pending submissions.
* Permanently deleting a previously distributed Topic records its direct Delete to
  the Group and the Group's matching `Announce(Delete)` to community followers.

= 0.7.0 =
* Makes the managed Group Actor the community directly. Removes the `ax_forum` CPT,
  `/forum/` templates, and the binding table; all Forum-owned records are keyed by
  `group_identity_id`.
* Moves Community enablement, posting policy, membership policy, and membership-request
  decisions into the Managed Group administration record. The Topic editor selects a
  manageable Community directly.
* Development schema reset is deliberate: this pre-release shape has no upgrade migration.
  Deleting the plugin runs its uninstall handler and removes only Forum-owned tables; it does
  not delete Group Actors, Topics, or the Activity ledger.

= 0.5.0 =
* Forum roots are ActivityStreams `Article` objects. The Group is the audience and a
  resolvable per-Topic URI is the thread context. Replies inherit that context.
