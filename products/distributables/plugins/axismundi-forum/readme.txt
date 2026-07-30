=== Axismundi Forum ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.7.0
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

Managers enable Community on the Group's Managed Groups administration screen. They
then choose Topic posting and membership policy there. An admitted `ax_topic`
projects as an ActivityStreams `Article` addressed to the Group; the Forum entry
table indexes Topics without duplicating their bodies. Topics use a resolvable
per-thread `context`; replies are Notes with `inReplyTo` and inherit that context.

There is no `ax_forum` post type, `/forum/` page, or Group-to-Forum binding. A
Group can be a community or not according to the presence of its Forum settings row.
Disabling a community never changes or deletes its Group Actor.

Not in this plugin: identity, handles, Group lifecycle, or manager delegation
(Axismundi Actors); the activity ledger and social relations (Axismundi Activities);
object rendering (Axismundi Object Projections); Note replies (Axismundi Note).

== Changelog ==

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
