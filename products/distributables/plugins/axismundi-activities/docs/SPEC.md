# Axismundi Activities — specification

> Status: **Phase 2 Activity repository and social relation state implemented**. No public
> route or network behavior.

## 1. Purpose

Axismundi Activities records what happened and derives social relationship state. It is
an offline ledger: it does not decide who receives a notification or how a remote server
receives an Activity.

```text
Actor URI + Activity + Object URI
                 |
                 v
       immutable activity ledger
                 |
                 +--> relation state / logical collection membership
                 +--> post-commit domain events
```

## 2. Invariants

1. **Activity != Notification != Delivery.** Activities records an event; Notifications
   decides recipient-facing state; Federation and Web Push own transport queues.
2. Actor, object, target, and related Activity identities are canonical URIs. Local database
   ids are implementation details and never cross plugin boundaries.
3. A recorded Activity payload is immutable. A valid Undo may update a denormalized
   `effective_status`, but never rewrites the original payload.
4. Follow acceptance/rejection is relation state, not Activity lifecycle. Delivery failure
   is transport state and never changes an Activity row.
5. WordPress media upload does not create an Activity. Only an explicit user action to share
   media to a feed may record `Create(Image|Video|Document)`.
6. Cache leases belong to Object Projections. Activities may declare or release a lease only
   through its public API and remains functional when that plugin is absent.
7. The WordPress table prefix is the tenancy boundary. Activity tables do not contain a
   `blog_id`; a future network-wide index is a separate projection.
8. Hooks such as `axismundi_act_activity_recorded` fire only after a successful commit.
9. Axismundi Actors is a required plugin dependency. Every `actor_uri` must resolve through
   its repository before an Activity is accepted; Activities never creates Actor identities.
10. Relation state is derived in the same transaction as its Activity. Relation hooks fire
    only after commit, and duplicate Activity delivery never emits duplicate relation changes.

## 3. Supported activity vocabulary

The initial vocabulary is Follow, Accept, Reject, Undo, Like, Announce, Create, Update,
Delete, Add, Remove, Move, Join, Leave, Block, and Flag. Supporting a type in storage does
not imply a transport or product workflow exists for it.

## 4. Local identity

Local Activity ids use an opaque UUID path, reserved as `/activities/{uuid}` with a plain
query fallback. The UUID is minted before insert and is independent of the numeric row id.
Remote Activity ids are preserved exactly. Hash-index lookup always verifies the full URI.

## 5. Non-goals

- HTTP inbox/outbox endpoints, signatures, signed fetch, delivery, and retry.
- Notifications, unread counts, email, PWA, service workers, or Web Push.
- Actor/object JSON-LD transformation or remote object binary caching.
- Automatic Activity creation from `add_attachment` or other incidental WordPress hooks.
