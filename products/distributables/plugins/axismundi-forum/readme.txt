=== Axismundi Forum ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.10.0
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

= 0.10.0 =
* A user may now run an Organization, not only a Group.
* Calls the manager list what it lists, renames the managed-actor functions for
  what they make, and makes a request say which Actor it is about.

Earlier releases are listed in changelog.txt.

