# Axismundi Activities — specification

> Status: **Phase 3c Activity repository, provenance-aware social relation state, Follow,
> Like, personal Announce, and FEP-044f QuoteRequest decisions implemented, with Core Post
> Create lifecycle and public collection queries**. No public
> Activity route or network behavior.

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
11. The first product workflow is local-only Person-to-Person Follow. Remote interaction is
    fail-closed until an explicit official ActivityPub compatibility adapter exists.
12. Actor activation and local social actions require `edit_posts`; Subscriber accounts are
    read-only and cannot acquire social write access merely by retaining an Actor row.
13. Automatic Core Post publication is keyed by a stable source event and lifecycle state,
    not by callback count. An effective Create/Update suppresses another Create; an effective
    Delete permits one resurrection Create. Object Projections emits candidates and this
    ledger owns deduplication.
14. The official ActivityPub plugin and Axismundi must never publish two Create activities
    for one post. Axismundi fails closed while the official scheduler owns the lifecycle;
    an adapter may transfer ownership only after suppressing that scheduler path.
15. `Undo.object` is the URI of the Like Activity being reversed, never the liked Object URI.
    Like counts and collection members are derived from effective ledger rows and distinct
    Actors; no second authoritative counter store exists.
16. A personal Announce references the canonical Object URI. One Actor has at most one
    effective Announce per Object, while Undo and a later re-Announce remain separate immutable
    cycles. `Undo.object` is the Announce Activity URI and retains its delivery audience.
17. Personal Announce is allowed only when an object-domain provider proves public or
    quiet-public visibility without a network request. Unknown, followers-only, direct,
    private, and local-only objects fail closed.
18. A public personal Announce addresses ActivityStreams Public and copies the original
    Object author to `cc`. This lets the origin server receive the Announce and apply the
    ActivityPub `shares` side effect. Group fan-out Announce is a separate future workflow:
    it may wrap an Activity and must not reuse this personal-boost API.
19. A Quote is an independent Object that references another Object. It is not an Announce,
    reply, or standalone `Quote` Activity type. The observed quote relation and its consent
    status are orthogonal: absence, rejection, or revocation of authorization does not erase
    the fact that a quote Object exists.
20. FEP-044f consent state belongs to Activities. Object Projections may index quote
    relations for discovery and counting, but that rebuildable index is not authorization
    truth. The official ActivityPub plugin supplies verified S2S transport only; its
    WP_User/CPT-backed Quote handler remains disabled.

## 3. Supported activity vocabulary

The initial vocabulary is Follow, Accept, Reject, Undo, Like, Announce, QuoteRequest, Create, Update,
Delete, Add, Remove, Move, Join, Leave, Block, and Flag. Supporting a type in storage does
not imply a transport or product workflow exists for it.

### FEP-044f vocabulary and state machine

The Quote increment adds `QuoteRequest` to the stored vocabulary. A request uses:

```text
actor       requester Actor URI
object      quoted Object URI
instrument  independent quoting Object URI
```

Processing is idempotent by the remote Activity URI and by the local authorization issued
for it. Policy evaluation uses the authoritative Activities Follow relation directly; it
never fetches or enumerates the public `followers` Collection.

```text
anyone     requester is automatically accepted
followers requester is accepted only when an accepted Follow relation exists
me         only a self-quote is accepted
```

An accepted request records an outbound Accept whose `object` is the QuoteRequest URI and
whose `result` is a stable QuoteAuthorization URI. A denied request records Reject with the
QuoteRequest URI as its object. Re-delivery must return the existing decision rather than
minting another Activity or authorization. A later policy change does not automatically
revoke an authorization already issued under the earlier policy.

An absent policy automatically approves nobody: it produces Reject rather than inventing
consent for a pre-policy Post. A consistent inline instrument may be inspected without a
network request; contradictory `attributedTo` or `quote` members prevent a decision while the
immutable inbound request remains recorded for diagnosis.

A valid `Delete(QuoteAuthorization)` changes authorization state to `revoked`; it does not
delete the row or the observed quote relation. When this site owns the quoting Object, the
Delete must be forwarded to that Object's audience as required by FEP-044f. Activities
records the lifecycle and audience; the Bridge delegates only signed delivery and retry to
the official plugin.

## 4. Local identity

Local Activity ids use an opaque UUID path, reserved as `/activities/{uuid}` with a plain
query fallback. The UUID is minted before insert and is independent of the numeric row id.
Remote Activity ids are preserved exactly. Hash-index lookup always verifies the full URI.

## 5. Non-goals

- HTTP inbox/outbox endpoints, signatures, signed fetch, delivery, and retry.
- Notifications, unread counts, email, PWA, service workers, or Web Push.
- Actor/object JSON-LD transformation or remote object binary caching.
- Automatic Activity creation from `add_attachment` or other incidental WordPress hooks.
- Reply/thread semantics before the Axismundi Notes CPT defines canonical local Note identity.
