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

**A schedule without a recorded intent federates nothing.** C3 stores replaceable intent in
`ax_activity_submission`, not in the immutable Activity ledger. The source triple
`(source_owner, source_kind, source_ref)` identifies one current submission, and `intent_uuid`
identifies its replaceable generation. `activity_type` is explicit; the current Post adapter
only dispatches `Create`. This storage identity does not advertise a general Activity scheduler.
Core remains the clock and trigger;
`scheduled_for` is a validation copy of `post_date_gmt`, replaced whenever the post is rescheduled.
Only an explicitly submitted Account can create a pending intent. Old schedules are not backfilled.
At firing, the frozen principal, Actor, source and audience are re-verified. If the resolved
audience has changed, the intent fails rather than silently taking a new audience. The Create
uses the snapshot and consumes the exact intent generation in the ledger's transaction, before
recorded/delivery hooks run. Failed intents remain private and visible to source editors.
The post still becomes public in WordPress if federation fails. No current selection or author's
Person is substituted. See `BRIEF-C3-SCHEDULED-PUBLICATION.md` for implementation and checks.

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
- Nothing stops a local Activity from claiming a past `published`. `axismundi_act_datetime()`
  parses with `strtotime` and never compares against the current time, on the same path that
  serves outbound rows (`repository.php` line 1221). §10 states the rule that is missing.

Order:

1. Retire the OP hook, or keep it only as a compatibility adapter that submits, so that intent
   comes from the editor rather than from a projection.
2. Record Update for local posts, with the partial-to-complete translation in §5.
3. Pending submissions are implemented by C3, with frozen identity and atomic consumption.
   A legacy scheduled post without an intent still reaches the adapter with no session and
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

## 10. `published` is a claim, and a local Activity may not backdate it

2026-10-25. WordPress lets an author set `post_date` to any past time, so a post can
claim to be published in 1996. That makes `published` **authored metadata, not an
observation**, and it forces a distinction the specification does not make for us.

### The specification requires no equality

ActivityPub §6.2 says what a server copies between a `Create` and its object, and
`published` is not in it.

```txt
SHOULD   actor → object.attributedTo
SHOULD   recipients of the Create → its object, and the object's → the Create,
         on initial distribution
MUST     §6.2.1 -- to/bto/cc/bcc/audience on a bare object are copied to the
         Create the server wraps it in
published   no normative text. It appears only in examples 15, 16, 17.
```

Both are Objects, so `published` means the same thing on each -- "the date and time at
which the object was published". They are therefore allowed to differ, and for imported
material they **should**: the object says 1996, the activity says today, and both are true.

Measured on staging 2026-10-25: our embedded `Create`s currently match their object's
`published` exactly, because nothing has been backdated yet.

### The rule: real time or scheduled, never backwards

A draft is not an activity; it has no ledger row. Sending fixes the time; scheduling fixes
a future time; claiming a past time is forgery. The mail analogy is exact -- a scheduled
send carries a future `Date`, and no send carries 1996.

```txt
핀   A local outbound Activity's `published` is the confirmed submission time, or the
     scheduled time for a scheduled publication. A past value is **refused, not
     silently corrected** -- correcting it would change the author's claim.
핀   This binds every activity type, not `Create`. `Question`, `Offer`, `Like`,
     `Announce` take the same ledger path.
핀   `object.published` stays free. Publishing a 1996 article today gives
     object.published = 1996 and Activity.published = today.
핀   An inbound Activity's past `published` is **the peer's claim** and is recorded as an
     observation, not refused. Applying the guard regardless of direction would break
     federation with any server holding archival material.
```

The guard does not exist yet; §9 lists it among what is not true, with the measurement.

### `feed_sort_at` is an immutability device, not a backdating defence

Recorded because it was asserted twice here and was wrong both times.

```txt
틀렸던 말 1   "COALESCE(published_at, received_at, created_at) replaces an old value
              with the received time."  COALESCE only skips NULL. A 1996 value is chosen.
틀렸던 말 2   "feed_sort_at keeps the feed order stable against a backdated published."
              `axismundi_act_feed_sort_at()` returns `published_at` verbatim when set.
              What it stabilises is the **cursor** -- one immutable key shared by writes,
              upgrades and continuation reads. A past date still sorts to the past.
```

So the feed is protected by the pin above, not by the sort column: once a local activity
cannot claim the past, the only way a distant date reaches our ordering is a remote
observation, which is correct.


### `updated` belongs to the object, never to the activity

Asked 2026-10-25: may a `Create` carry `updated`? AS2 permits it -- a `Create` is an
Object, so the property is available -- but it is wrong in this model and useless to a
receiver.

```txt
불변 사건      LIFECYCLE.md §1 holds an Activity immutable. "the time this was updated"
               on something that is never updated is either a contradiction or noise.
수정의 표현    the object changes, and ActivityPub models that with an `Update`
               activity, not by amending the Create.
수신자         has nothing to do with it. There is no edit-an-activity concept to act on.
```

Measured 2026-10-25, and the codebase is already consistent:

```txt
'updated' =>  emitted in calendar, forum, media-library, note and the media projection
              -- every one of them an **object** projection, from get_post_modified_time()
              no code puts `updated` on an activity document
staging       six Creates, all with activity.updated absent, while their embedded Notes
              carry object.updated
ledger        the payload's `updated` is read in exactly one place, questions.php:187,
              and there it is a **Question object's** value
```

The trap is reading the rule one word too wide. The embedded object **must** keep its
`updated`: that is how a receiver learns a Note has been edited. The distinction is
outside the activity versus inside the object, not "no `updated` anywhere near a Create".

```txt
핀   An activity document carries no `updated`. An activity is an immutable event; a
     modification to its object is expressed as an `Update` activity.
핀   The embedded object keeps `updated`. It is the wire form of its edit history.
핀   `activities.updated_at` is row bookkeeping, not AS2 `updated`. It is not projected.
핀   Each `Update` activity carries its own `published`, and the no-backdating pin above
     applies to it unchanged -- an edit recorded today may not claim to have happened
     earlier.
```

## 11. The federated-object list comes from the ledger

Publishing to the site and publishing to the federation are separate commands. A WP
`publish` may trigger the second -- the way a subscriber mailing does -- but it is not the
same event, and the federated set is therefore not "every published post".

```txt
Activity ledger
  → activities this instance sent (the existing outbound classification,
     not the word "local" as a direction value)
  → activity_type = Create
  → group by object identity
  → apply Delete state and access rights
```

`identity` is the join: a WP post id names a local authored thing, an Object identity names
what federation keeps referring to. Having an identity does not mean "federated" -- the
Create/Update/Delete history attached to it does. That removes any need for a second list
of federated objects.

```txt
federated = a federation publication was confirmed and its Create recorded.
            **Not** delivery success -- an object with no recipients, or one whose
            delivery is still retrying, must not vanish from the list.
```

Two read surfaces follow from the same ledger without being the same query: an Object
DataView is one row per object, an activity feed is one item per activity. And the filter
above is the **local** federated set only; remote objects arrive by other paths such as
`Announce`, so a reader must not restrict everything to `Create`.

2026-10-25 후속 연구 메모: 공동 귀속/발행 동의와 동일 Object의 복수 Create 구상은
`../../../architecture/activitystreams-open-items.md`의
"Research Note: Collaborative Attribution And Publication"에 보존했다. 채택된 프로토콜이
아니며, 기본 C2S 저작이나 Account 전환의 선결 조건으로 추가하지 않는다.

## 12. Question은 와이어에서 Create에 감싸인다 — 저장 소유와는 별개다 (2026-10-26)

AS2 어휘는 `Question`을 `IntransitiveActivity`의 하위로 정의한다. 연합 실무는 그렇지 않고,
FEP-9967이 그 차이를 명시적으로 적는다.

```txt
FEP-9967 "Polls"   Question 은 Create 활동으로 **MUST** 발행된다
                   AS2 가 Question 을 intransitive activity 로 정의하는 것에서 의도적으로 벗어난다
                   투표는 Create(Note), name 이 선택지와 일치, inReplyTo 가 Question,
                   to 가 저자. "The object MUST NOT have a `content` property."
                   다중 선택은 "each vote MUST be sent as a separate activity"
Mastodon 사양서    Question "is used as an Object type instead of as an IntransitiveActivity",
                   "wrapped in a Create just like any other status"
```

이미 기록해 둔 규칙 -- vote 는 `Create(Note){name}` 이고 `content` 가 비어야 분류된다 -- 는
이 FEP 와 일치한다. 어휘가 아니라 와이어를 따른 결과다.

**그러므로 와이어 타입으로 저장 소유를 추론하지 않는다.** 2026-10-26 리뷰에서 같은 추론으로
두 번 틀렸다 -- 먼저 "Question 은 intransitive 라 소스 참조를 가질 수 없다"(와이어상 object
이므로 틀림), 다음 "Question 은 Activity 자체라서 Activities 가 저장한다"(결론은 맞지만
근거가 틀림). 둘은 독립된 사실이다.

```txt
와이어 타입   Create(Question).  FEP-9967 MUST
저장 소유     Activities.        우리 결정이고 와이어 타입에서 따라 나오지 않는다
```

제출 계층의 분기도 AS2 타입 계층이 아니라 **무엇이 제출됐고 누가 그 저장을 소유하는가**로
가른다. 진짜 intransitive 는 `Arrive` · `Travel` 이고 현재 범위 밖이다.

### 측정된 공백 -- 투표 수신 시 Update 를 발행하지 않는다

```txt
FEP-9967   저자는 갱신된 Question 으로 Update 를 발행한다.
           "This activity MUST be immediately delivered to the actor that sent the vote."

측정 (2026-10-26)
  Update 를 발행하는 production 코드   actor-lifecycle.php:53 한 곳뿐 (Actor 프로필 변경)
  questions.php 의 Update 는 전부 수신 처리
    :5    "its results arrive in Update"        받는 쪽 주석
    :624  Create/Update 수신 시 Question 투영
  record_vote() 는 집계를 커밋하고 끝난다. 발행 경로가 없다
  훅은 axismundi_act_question_projected 하나이고 투표 수신이 아니라 투영 시점에 돈다
```

원격 Question 에 투표하는 쪽은 구현되어 있고, **로컬 Question 이 투표를 받았을 때 결과를
되돌려 보내는 쪽이 비어 있다.** 로컬 폴은 자기 집계를 소유하므로 데이터는 맞지만, 투표자는
자기 표가 반영된 것을 보지 못한다.

**다만 이 분기는 아직 도달 불가능하다.** 별도 슬라이스로 떼면 구현할 대상이 없는 슬라이스가
된다.

```txt
question 행          15
vote 행               0        ← record_vote() 가 한 번도 돈 적이 없다
원장 Question         outbound Create 509 (전부 발신, 수신 0)
작성 표면             ax_question CPT 없음 -- Question 작성 UI 가 아직 없다
```

Question 작성이 가능해지는 조건이 사슬로 묶여 있고, Update 발행은 그 사슬의 **완료 조건**이지
앞에 둘 수 있는 작업이 아니다.

```txt
ax_note CPT 컷오프
  → OP C2S 객체 테이블        Note 본문을 소유할 자리
  → Create(Question) 작성 UI  Question 은 Create 의 object 이므로 객체 저장이 먼저다
  → poll 성립                 비로소 로컬 Question 이 투표를 받는다
  → Update 발행이 필수가 된다  FEP-9967 MUST. poll 기능의 완료 조건이다
```
