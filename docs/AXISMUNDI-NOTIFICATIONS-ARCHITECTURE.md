# Axismundi Notifications Architecture

Planning document. Nothing here is built yet; the only shipped part is the declaration
side in Axismundi Calendar (`86c9360`), which fires `axismundi_notify` and stores
nothing.

## Scope

One inbox for everything a person has to look at. A mention, a follow request, a reply
and a calendar invitation are the same kind of thing to whoever receives them, so a
product keeping its own list would hand out one badge per plugin — which is how people
learn to ignore badges.

```
Actors          identity, manager delegation, acting Actor
Activities      federated Activity ledger and inbox (the record)
Calendar/Note/… domain acts; each declares who needs to know
Notifications   normalises those into "what this Actor must look at" + delivery
```

Notifications owns no source of truth. Every entry points back at an Activity, a
comment, a participation row or a moderation record, and holds a snapshot of how that
read at the time. Because it is derived, it can carry as much personal state as it
likes — read, dismissed, bundled, muted — without touching any origin.

**Not a copy of the Activity ledger.** An Activity is what happened on the network. A
notification is a change one Actor has to act on or know about. Most Activities are
neither.

## The unit is the Actor

```
notification target   Actor           (Organization, Group, Person)
viewer and delivery   local user      (whoever manages that Actor)
```

An Organization is invited to things, removed from things and told when they are called
off. An account managing three Actors is looking at three sets of news, which is the
acting-Actor contract already in force everywhere else. Whether a signed-in user may
see an entry is a question about who manages that Actor, asked at display time.

Read state cannot live on the entry. Two people manage a Group; one of them reading a
notification must not clear the other's badge.

## Tables

```
notification_events        the fact, addressed to an Actor
  id
  kind                     axismundi-calendar/event-invited
  category                 conversation | social | calendar | moderation | low
  recipient_actor_id
  actor_uri                who did it
  object_uri               what it was about
  source_uri               the Activity/comment/participation it derives from
  snapshot                 how it read at the time
  occurred_at
  grouping_key             what may be collapsed with what
  state                    accepted | filtered

notification_deliveries    one person's copy
  notification_id
  local_user_id
  delivered_at | read_at | dismissed_at

notification_preferences   what somebody wants, per Actor they manage
  local_user_id
  actor_id                 the Actor context, or 0 for "all of mine"
  kind_or_category
  in_app | push | email    off | immediate | inactive_only | digest

push_subscriptions         per device, never per Actor
  local_user_id
  endpoint | keys | last_seen_at | revoked_at
```

`grouping_key` goes in from the first migration even if nothing collapses yet. Adding it
later means backfilling a key over history that no longer has the context to compute it.

### What the WordPress feature plugin gets right, and where it stops

`wp-feature-notifications` separates **immutable message / subscription / per-user
queue**, and registers namespaced channels (`core/post-new`). That three-layer split is
the right shape and is what the tables above use.

What must not be carried over: recipient is a `wp_user` there, and read state hangs off
the same row. And its own repository is still TODO — it is a model to learn from, not a
dependency.

Its word `channel` is also used for two different things. Here:

```
category   what somebody subscribes to     calendar, social, moderation, security
transport  how it reaches them             in-app, push, email, digest
```

## The three stages

Taken from Mastodon and Misskey, which both split what a naive "type on/off" list
cannot.

```
1. kind        what happened
2. acceptance  accept | filter | drop      about the sender and the relationship
3. delivery    in-app | push | email       about the recipient and their attention
```

**Acceptance is not a preference.** Mastodon's `NotificationPolicy` decides on the
sender: not followed, not a follower, brand-new account, limited, bot. `filter` does not
discard — it quarantines into a notification request, which is what keeps the mechanism
usable against harassment without silently losing legitimate contact. Misskey instead
attaches a sender condition per type (`all / never / following / follower / mutualFollow
/ followingOrFollower / list`).

Take Mastodon's shape for protection (one policy, three outcomes, quarantine not
deletion) and Misskey's for granularity (per-kind conditions), and keep them as separate
settings rather than one merged matrix.

Misskey is also worth a warning: its email settings screen saves follow and
follow-request preferences whose send functions are still commented TODO. **A preference
that exists is not a delivery that happens** — every switch shipped needs the audit that
proves something reaches somebody.

## Kinds

```
conversation   mention, reply, quote
social         follow_requested, follow_accepted, follow_rejected
calendar       event_invited, event_invite_withdrawn, event_removed,
               event_cancelled, event_reinstated, event_join_requested,
               event_joined, event_join_answered, event_join_withdrawn,
               event_invite_answered, event_invite_answer_undone
moderation     report_received, report_resolved, content_limited, actor_suspended
security       manager_added, manager_removed, key_rotated, sign_in_new_device
low            reaction, like, boost/announce
```

Calendar's eleven are already emitted. The rest are named here so the registry is one
list rather than one per consumer.

## The four questions

**1. Immediate, bundled, or off by default.**

```
immediate in-app, email candidate
  mention, reply, follow_requested
  event_invited, event_cancelled, event_removed
  moderation, security

in-app, bundled by grouping_key
  event_join_requested and event_invite_answered on a busy Event
  event_reinstated, event_joined, event_join_answered

in-app only, collapsed, no email
  reaction, like, boost, quote
```

The test is whether it needs an answer or changes a plan somebody made. An invitation
and a cancellation both do. A like does not.

**2. Self-notification: always dropped.** Already the rule in Calendar's emitter, and it
should be the contract, not each product's habit. The one case worth naming: an act by
*another manager* of an Actor you also manage is **not** self-notification — the Group
was told, and you are one of the people who reads what the Group is told.

**3. A new manager sees the Actor's history.** The inbox belongs to the Actor. Somebody
made a manager of an Organization today can read what the Organization was told last
month, because the alternative is an Organization whose notifications are only ever
visible to whoever happened to be a manager at that minute. Their *deliveries* start
empty, so nothing arrives pre-read and nothing arrives as a hundred unread badges: the
entries are visible, the unread count begins at the moment they were added.

Manager removal is the same rule backwards. Access ends with the relationship, and it is
re-checked at read and again immediately before any push is sent.

**4. MVP channels: in-app inbox and admin bar badge only.** Push and email wait. Both
need the preference matrix, the presence heuristic and the delivery audit before they
are anything other than a switch that lies.

## PWA and Web Push, when it comes

Push is a third transport, never a second origin.

```
notification fact   recipient_actor_id
push subscription   local_user_id + device
preference          user × Actor context × category × transport
```

- **Payload carries no content.** A push endpoint is effectively a capability URL, and
  the payload crosses a push service and can land on a lock screen. Send a delivery id
  and a category — "a new calendar notification" — and let the opened, authenticated app
  fetch the snapshot. A private invitation's title, its guest list and a comment body do
  not go through that boundary.
- **Delivered is not read.** A push that arrived says nothing about attention, and
  dismissing a system notification is not reading an inbox entry.
- **Actions re-verify.** An Accept from a notification action opens the app and runs the
  ordinary server-side command, which re-checks the acting Actor, the manager relation,
  the capacity and the Event's lifecycle. The RSVP engine already refuses on a cancelled
  Event; the notification path must not be a second door into it.
- **Active users get in-app instead.** Presence is a hint, so a missed suppression is
  acceptable and a missed inbox entry is not — the inbox is the answer, push is the
  reminder.
- **Best-effort.** Web Push TTL, urgency and topic replacement are useful for collapsing
  a badge update or a bundle. Background Sync is not universally available and must not
  carry correctness for offline RSVP or read state.
- **Permission on intent.** No prompt at install. The prompt follows somebody turning on
  notifications for a named Actor — "get calendar invitations and mentions for
  @busan-wordpress" — which is also the moment the preference row is created.

## Slice order

```
1. contract + registry     kinds, categories, the events table, the emitter contract
2. Actor inbox             deliveries, read state, admin bar badge, manager gate
3. consume                 Calendar's eleven, then mentions/replies/follows
4. acceptance policy       accept | filter | drop, quarantine, mute/block
5. preferences             per Actor context, category, transport
6. transports              email (inactive-only first), then Web Push
```

Products keep declaring through `axismundi_notify` and never learn what an inbox is. A
site with Notifications absent loses nothing: the acts still happened, the ledgers still
hold them, and the screens that show them are unchanged.
