# Client to Server: one submission contract

Status: **design, not implemented.** Decided 2026-09-28. Section 9 says what exists today.

Every claim about the protocol here is quoted from
[ActivityPub](https://www.w3.org/TR/activitypub/) rather than paraphrased, because the
client-to-server and server-to-server halves of the same verb have deliberately different
rules and the difference is easy to lose.

## 1. What C2S is, and what it is not

C2S is not a save hook. It is a submission:

> Clients MUST discover the URL of the actor's outbox from their profile and then MUST make
> an HTTP `POST` request to this URL with the Content-Type of
> `application/ld+json; profile="https://www.w3.org/ns/activitystreams"`

The server owns the identity of what comes back:

> If an Activity is submitted with a value in the `id` property, servers MUST ignore this and
> generate a new `id` for the Activity.

> Servers MUST return a `201 Created` HTTP code, and unless the activity is transient, MUST
> include the new `id` in the `Location` header.

A client may submit a bare object instead of an Activity, and the server wraps it:

> The server MUST accept a valid ActivityStreams object that isn't a subtype of `Activity` in
> the POST request to the outbox. The server then MUST attach this object as the `object` of a
> Create Activity.

> Any `to`, `bto`, `cc`, `bcc`, and `audience` properties specified on the object MUST be
> copied over to the new Create activity by the server.

So the submitted document is a **request**, not a record. What gets stored is the server's own
Activity. That distinction is the whole reason this file exists: today a WordPress save is
treated as though it were already the Activity, which is why the actor had to be guessed.

## 2. Why this belongs to Activities

| plugin | owns |
|---|---|
| Actors | Actor identity, the acting Actor, `can_act_as()` |
| **Activities** | **the C2S submission contract, verb side effects, the Activity ledger, outbox projection, recipients** |
| Object Projections | `WP_Post` ↔ AS2 Object, Object URIs, the current snapshot |
| Note / Forum / Calendar | Object types and their context |
| official ActivityPub plugin | S2S transport: HTTP Signatures, key handling, delivery |
| ActivityPub Bridge | official inbound → normalised inbound Activity |

C2S is not a transport concern, so it does not follow S2S into the official plugin. It is the
boundary where a local principal asks to act as an Actor, and only Activities holds the ledger
that answers what that Actor has already done.

### Three adapters around one meaning layer

C2S and S2S are **adapter boundaries, not two domain models**. Activities does not grow a
second ledger or a parallel vocabulary for each; they are different ways in and out of the
same one.

```txt
wp-admin / Reader / PWA / REST client
    → local submission adapter  ┐
                                ├→ Activities command service ─┐
official plugin inbox + signature verification                 │
    → S2S inbound bridge        ┘→ Activities ingestion ───────┤
                                                               ↓
                                                   Activities ledger
                                                   outbox projection
                                                               ↓
                                                    S2S outbound bridge
                                                    → official plugin delivery
```

Activities owns the middle: verifying a local submission, reflecting a verified remote
Activity, and projecting the outbox. Signatures, inbox HTTP, shared inboxes and delivery retry
stay with the official plugin. Activities re-verifying a signature would duplicate ownership.

Object Projections decides nothing about identity on any of these paths.

### Naming

The block and classic editors are **local submission adapters**, not ActivityPub C2S HTTP
clients. Calling them C2S clients would claim wire conformance we do not have. When the Actor
outbox `POST` endpoint exists it becomes the real C2S wire adapter, and it calls the same
command service.

### The same verb, two trust boundaries

`Create` is one vocabulary term used at two different points, which is why the rules attached
to it differ:

| | C2S `Create` | S2S `Create` |
|---|---|---|
| direction | client → its own server's outbox | origin server → remote inbox |
| what it is | a request to publish | a finished, already-published fact |
| who is trusted | an authenticated principal, whose chosen Actor is re-checked | a verified origin server |
| the `id` | ignored; the server mints one | authoritative; it identifies the Activity |
| the `object` | may be bare, and gets wrapped | as the origin server sent it |

The S2S row is deliberately not "complete". ActivityStreams allows `object` to be a link, and
we already handle an inbound `Announce` that carries only a URI. What a receiver owes a
`Create` is verification — of origin, and of the Activity's relationship to its Object — not an
assumption that the body travelled with it. The one verb with a stated completeness contract
is `Update`, and only server to server; see §5.

## 3. The submission contract

```php
axismundi_act_submit_c2s(
    int   $principal_user_id,   // who is authenticated
    int   $actor_identity_id,   // who they are asking to act as
    array $command,             // operation, subject, expected version, audience intent
    array $context = array()    // source surface, idempotency key
);
```

**Principal and acting Actor are separate arguments and neither is inferred.** The caller
states both; the server verifies the relation with `can_act_as()` at submission time, not from
a stored preference. This is the specific thing the current code gets wrong — it reads
`get_current_user_id()` and the acting-Actor preference from inside a lifecycle hook, so there
is no answer to "which command authorised this publication" on the REST, scheduled-publication
and proxy-save paths.

**The Actor is named by identity, not by URI.** Our `actor_uri` is immutable, so this is not
about the URI going stale; it is that the permission kernel is identity-keyed throughout —
`can_act_as()` resolves through `get_by_identity()`, `set_acting_actor()` and
`managed_actor_can_manage()` both take an identity id. An Actor has four public lookups (actor
URI, profile URL, handle, uuid), so accepting a string would make the command guess which one
it was handed before it could check anything. The command takes the identity; the server
resolves it to the `actor_uri` and the `attributedTo` URI when it writes the Activity, because
what gets recorded and federated is URIs.

### The submitted command is not the Activity

```txt
command                        recorded Activity
  operation: Create|Update       id        minted by the server
  post_id                        actor     resolved from the identity
  expected_version               object    OP's current projection
  audience intent                to/cc     resolved by the audience resolver
```

The caller says what it wants done to which post; it does not hand over a document to be
stored. This is the same separation the spec draws when it makes the server ignore a submitted
`id`, and it is what keeps a client from asserting an actor or an audience it has not been
granted.

`expected_version` is required for anything that already exists. Gutenberg and Classic write
to `wp_posts` before we hear about the save, so between that write and the submission another
edit can land, and without a version the Activity silently describes a body nobody chose to
publish. The command carries `post_id` plus the modified timestamp (or a content hash) the
caller believed it was publishing, and the server records nothing if the post has moved on.

Keeping the principal abstract rather than binding it to a cookie and nonce is what lets a
later OAuth client reach the same command. The spec does not decide this for us:

> Unfortunately at the time of standardization, there are no strongly agreed upon mechanisms
> for authentication.

with OAuth 2.0 bearer tokens named only as a MAY, through the actor's `endpoints`. So the
authentication method is ours to choose per surface, and the command must not care which was
used.

An `$actor_identity_id` of `0` is a real request, not an error — see §8.

## 4. Storage comes first

An Activity that references an Object nothing can serve is a broken record. `wp_posts` is the
source of truth for locally authored content, and the Object URI is derived from the saved
post, so the save has to happen before the Activity is recorded:

```txt
C2S command
  → authenticate principal, verify can_act_as( actor )
  → verify the verb is permitted for this actor on this object
  → save/modify wp_posts            (object type adapter)
  → OP builds the current Object    (projection, no identity decision)
  → Activities mints the Activity id and records it
  → outbox exposes it
  → official plugin signs and delivers
```

The two entry shapes differ only in who performs the save:

- **Gutenberg and Classic** already wrote to `wp_posts` before we hear about it. The editor
  submits afterwards, and the adapter's "save" step is a no-op that just resolves the post.
- **React admin, PWA and any later client** submit first; the object type adapter creates or
  modifies the post, then the same path continues.

Delivery failure must not roll back the local post. It stays a pending delivery.

## 5. Per-verb contracts

### Create

> When a `Create` activity is posted, the `actor` of the activity SHOULD be copied onto the
> `object`'s `attributedTo` field.

We make the SHOULD a local MUST: a locally created Object is attributed to the Actor that
created it, always. That is what makes attribution recoverable later without a dedicated
column.

### Update — the asymmetry that matters

C2S and S2S mean different things by the same verb.

> For client to server interactions, updates are partial; rather than updating the document
> all at once, any key value pair supplied is used to replace the existing value with the new
> value.

> For server to server interactions, an `Update` activity means that the receiving server
> SHOULD update its copy of the `object` of the same `id` to the copy supplied in the `Update`
> activity. Unlike the client to server handling of the Update activity, this is not a partial
> update but a complete replacement of the object.

So an inbound C2S patch cannot be forwarded as-is. The path is: apply the patch to `wp_posts`,
have OP project the **complete** current Object, and send that as the S2S payload. A remote
receiver has no way to apply our partial.

`Update.actor` is the Actor that performed the edit. `object.attributedTo` does not change.
A contributor revising an Organization's Article emits `Update.actor = contributor`,
`attributedTo = Organization`, and the Object stays the Organization's.

Changing `attributedTo` is a separate, explicitly authorised reassignment. It is never a side
effect of an edit.

### Delete

> The `Delete` activity is used to delete an already existing object. The side effect of this
> is that the server MAY replace the `object` with a `Tombstone`.

Authority to delete is the Object's attribution, or an explicit delegation. It is specifically
not "whoever performed the newest Activity" — that reading refuses an owner their own
withdrawal as soon as anyone else has edited the Object. We have no delegation model yet, so
delegated deletion is refused rather than silently allowed.

Our 410-with-Tombstone behaviour for AP and 404 for human HTML is unchanged and deliberate.

### Addressing

> The server MUST remove the `bto` and/or `bcc` properties, if they exist, from the
> ActivityStreams object before delivery.

Recipient calculation stays where it already is, in the shared audience resolver.

## 6. The outbox is a projection, not the ledger

> The outbox stream contains activities the user has published, subject to the ability of the
> requestor to retrieve the activity (that is, the contents of the outbox are filtered by the
> permissions of the person reading it).

> If a user submits a request without Authorization the server should respond with all of the
> Public posts.

The ledger holds more than the outbox shows, and §5.1 leaves the extent to implementation
discretion. The outbox endpoint is a filtered public view over the ledger; it is not storage.

`POST` to the outbox is the standard surface and `axismundi_act_submit_c2s()` is its handler.
The editors call the handler directly rather than making an HTTP request to themselves. Both
must go through the same function or the two will drift.

## 7. Delivery

> Federated servers MUST perform delivery on all Activities posted to the outbox

We satisfy this through the official plugin: Activities hands it a canonical Activity and a
recipient list and never signs or makes the HTTP request itself.

## 8. Clients, and the ones that cannot choose an Actor

| surface | when it submits | actor |
|---|---|---|
| Gutenberg, Classic | publish, update of a published post, transition out of published | acting Actor, chosen in the editor |
| WP-admin React, PWA | explicit user action | chosen explicitly |
| official WordPress / Jetpack apps | via the existing WordPress REST API | **none supplied** |
| later mobile C2S | explicit, with OAuth | chosen explicitly |

The official apps build from `wordpress-mobile/WordPress-Android` and have no acting-Actor
switcher, so they will never name an Actor. The policy for a request that names none:

1. the principal's own Person Actor, if they have one;
2. otherwise save to `wp_posts` and record **no** Activity.

The server never guesses an Organization or the site Actor. An unattributed local Object is a
real state: it can still be projected for reading, it is simply not published by anyone.

### Scheduled publication

A scheduled post already has a `wp_post` and therefore a stable Object URI, so the identity of
what will be published is settled the moment it is scheduled. The Actor is settled then too.

What must not happen is reading the acting Actor again when the schedule fires. The acting
Actor is the current state of a UI control; the schedule is an intent fixed in the past. An
author who scheduled an Organization's article and has since switched back to their own Person
would otherwise have that article quietly published under the wrong name.

So the choice is frozen at scheduling time and re-verified, not re-read, at execution:

```txt
at scheduling            object_uri, actor identity, expected version,
                         audience snapshot, scheduled_for, state = pending

when it fires            re-verify can_act_as() for that identity
                         → OP serialises the current Object
                         → the Create becomes active, then delivers
                         → or the submission is left failed
```

Two rules follow.

**A pending submission is not an active ledger row.** Measured on 2026-09-28: a `future` post
fails `axismundi_op_post_article_visible()`, `axismundi_op_transform_object()` returns
`ax_op_not_public`, and both an `application/activity+json` and an HTML request to its Object
URI return 404 with no body. Writing a Create for it into the ledger as active would advertise
in the outbox something the Object endpoint correctly refuses to serve. A pending submission is
therefore excluded from the outbox projection, from delivery, and from public Activity reads
until it fires.

(`axismundi_op_resolve_source_by_uri()` does find the `future` post — it resolves sources, not
public objects. Any new endpoint built on the resolver has to apply the visibility gate itself;
the existing ones do.)

**Editing a scheduled post replaces the pending intent.** Nothing has been federated yet, so
there is nothing to `Update`. The pending submission's expected version and audience snapshot
are replaced, and exactly one live intent exists when the schedule fires.

**Until pending submissions exist, a scheduled publication federates nothing.** Implemented: a
publish candidate reaching the adapter with no authenticated principal makes no Create. The post
becomes public in WordPress and is readable as an unattributed Object. The alternative — falling
back to the author's own Person — publishes an article that may have been written as an
Organization under the wrong name, and leaves no record that it was a guess. Silence is
recoverable; a wrong attribution that has already been delivered is not.

If `can_act_as()` fails at execution, the submission is left failed and the author is asked to
choose again.

### Objects that predate the ledger

Activating this plugin on a site with existing posts produces Objects with no Create. They are
not backfilled, and `post_author` is specifically not used to invent one: that is the ownership
error this whole design removes, and there is no way to tell a post written as an Organization
from one that was never meant to federate at all.

| what is on record | what the Object gets |
|---|---|
| a Create in this ledger | that Create's attribution |
| legacy metadata a migration can read | whatever that migration establishes, explicitly |
| nothing | no attribution; readable, with no Create, outbox entry or delivery |

Attribution for these is claimed, never inferred. A claim is checked twice — the principal may
edit the post, **and** may act as the chosen Actor — because an editor may revise someone else's
work and because `post_author` cannot name an Organization at all. Where a default is offered it
is narrow: the principal is the author, no Actor was chosen, and their own Person is *suggested*
rather than applied. Administrator proxy edits, imports and cron claim nothing.

A claim settles who the current representation belongs to. It is not a publication and it is not
an `Update`: remote servers have never seen the Object, so there is nothing for them to update.
The first federated `Create` happens later, when someone asks for it, and it happens as a present
act rather than a forged past one. The post may still become publicly readable on schedule — that is WordPress's
decision about its own content — but a federated Create is not made in the name of an
Organization whose authority has been withdrawn, and never falls back to a Person or the site
Actor.

`save_post` firing is not an intent to publish. Autosaves, drafts, scheduled transitions and
administrator proxy saves all reach it, so the editor integration decides when a submission
actually happens rather than submitting on every write.

## 9. What exists today, and the order to get there

Implemented:

- `axismundi_act_submit_c2s()` — the command service. Principal, actor identity and expected
  version are arguments; it verifies `can_act_as()`, the principal's capability over the
  source, and the version, then records. `Create` and `Delete` only.
- The block and classic editors call it as the first local submission adapter, offering an
  identity that the service re-checks rather than trusts.
- `axismundi_act_get_object_attribution()` — attribution read from the Create that began the
  current generation, so a foreign Update cannot move an Object to its editor.
- `Create` on an Object already attributed elsewhere is refused as an unauthorised
  reassignment. `Delete` is authorised by attribution, not by the newest Activity's actor, and
  the principal must still pass `can_act_as()` for it -- a WordPress capability to delete the
  post is not authority to speak as the Organization that published it.

Not yet true, and this file is the target rather than a description:

- The trigger is still OP's `axismundi_op_object_publish_candidate` hook, so publication is
  still started by a projection observing a save rather than by a submitted intent. The
  adapter therefore reads the principal and the offered identity from the ambient request.
- There is no outbox `POST` endpoint.
- No Update is recorded for local posts. The service refuses the operation rather than
  pretending to accept it, which is why the attribution rule above is exercised only by tests.

Order:

1. Retire the OP hook, or keep it only as a compatibility adapter that submits, so that intent
   comes from the editor rather than from a projection.
2. Record Update for local posts, with the partial-to-complete translation in §5.
3. Pending submissions, so a scheduled publication freezes its identity instead of resolving
   one when it fires. Until then a scheduled post reaches the adapter with no session and
   publishes under the author's own Person.
4. The outbox `POST` endpoint over the same command service — the first true C2S wire adapter.
   Nothing before this point needs it; React, PWA and any later mobile client call the command
   service the way the editors do.
5. A dedicated `attributedTo` column once an Update may carry a different actor than the
   Create — attribution stops being recoverable from the Create at that point.
6. Reassignment as an explicit, separately authorised command.

## Related

- `axismundi-object-projections/docs/LOCAL-OBJECTS.md` — why identity and body stopped being
  derived at projection time.
- `docs/BOUNDARIES.md`, `docs/LIFECYCLE.md` — the ledger's existing contracts.
