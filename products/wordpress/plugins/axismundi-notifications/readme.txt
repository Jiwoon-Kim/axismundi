=== Axismundi Notifications ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors, axismundi-activities
Stable tag: 0.1.0-beta.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, notifications, federation, fediverse

One inbox for everything worth looking at now, addressed to an Actor rather than
to a WordPress user.

== Description ==

Every product here already records what happened. Axismundi Notifications does
not record it again: it reads the Activity ledger, asks each product what one
Activity meant to whom, and keeps the answer as an event addressed to a
recipient Actor.

The unit is the Actor, because an Organization or a Group receives invitations
and mentions too, and the person reading them is whoever manages that Actor
today. Access is re-checked on every view, so somebody who is no longer a
manager stops seeing the Actor's inbox even though the rows are still there.

Four layers, kept apart on purpose:

* **Kind** -- what happened, namespaced by the product that answered for it.
* **Acceptance** -- whether an event from this sender is accepted, held as a
  request, or dropped. A question about the sender, decided once when the event
  is written, so turning a filter on never rearranges an existing inbox.
* **Preference** -- whether a particular person wants this by in-app, email or
  push. A question about attention rather than about the sender, decided when
  the delivery is made, so two managers of one Group can answer differently.
* **Transport** -- in-app, email, and Web Push when Axismundi PWA is installed.
  Each attempt is its own row, so a mail that failed is not a notification that
  never happened.

Notifications never carries what was said. A private message exists in one
place; an email or a push holding a copy would be a second place to read it
from and a second place to have to delete it.

Push appears only when Axismundi PWA is present and holds VAPID keys. Without
it the setting is hidden rather than shown broken.

== Changelog ==

= 0.1.0-beta.1 =
* First pre-release. In-app inbox with an admin bar count, acceptance policies
  and mutes, per-person transport preferences, email delivery, and Web Push via
  Axismundi PWA. Resolvers for Axismundi Calendar and Axismundi Note.
