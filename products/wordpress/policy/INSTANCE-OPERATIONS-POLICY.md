# Instance Operations Policy

**Status: draft. Nothing here is enforced by a check yet.**

This document states what an Axismundi instance promises about data: what it
records, what it sends to other servers, what it keeps, and what it refuses to
collect. The architecture documents say how the software is built; this one says
what the operator is telling people. The two drift apart easily, so where a
promise below is already enforced somewhere, the enforcement is named. Where it
is not, the line says so rather than implying a guarantee.

A promise that no code keeps is a convention. A promise contradicted by the code
is a defect, and the code is what people actually experience.

## 1. What this instance records about reading

**It does not count anonymous visits, and does not try to.**

`View`, `Listen` and `Read` are ActivityStreams activities, and an activity
requires an actor. A visitor who is not signed in has no actor, so nothing is
recorded about them. Counting them once each would require recognising a
browser across requests, which is device identification however it is phrased —
including the common trick of a rotating daily salt, which only shortens how
long the identification is retained.

The consequence is deliberate and should be stated wherever a number appears:
what this instance can show is **known viewers**, not traffic. It is a floor.
Anonymous readers are missing from it, so are actors who publish quietly, and
each actor counts once no matter how often they return.

If site traffic analytics is ever wanted, it is a separate system with its own
policy, and its numbers are never merged into this one.

- Enforcement: none yet. Planned as a unique key on
  `(object, actor, activity_type)`.

### The count is public; the names are not

An object's known-viewer count is public by default. Published software has
long shown such numbers -- WordPress's own view-count blocks, and the view
figures major networks display -- and later features such as hashtag trends are
aggregates over the same data.

**Who viewed is not public.** The identities behind the number are visible to
nobody but the viewer themselves; an author sees how many, not which. Nothing
in this instance exposes a per-object list of viewers, and no API answers that
question.

Two limits follow, and both are honest rather than reassuring:

- A small count can identify people by itself. Three known viewers, a visible
  follower list and a timestamp narrow a set quickly, and on an instance with
  one actor a count of one is a name. Hiding identities does not fix an
  aggregate that is small enough to invert. Whether to suppress counts below a
  threshold, or to show them in coarse bands, is **undecided** and listed below.
- Federating the number is a separate decision from displaying it.
  ActivityStreams defines no view-count property and the major
  implementations publish none, so putting one on the wire means registering an
  extension term with its provenance -- never quietly borrowing a core-looking
  name.

If it is published, it is published as a collection that states its size and
never its members. `likes` and `shares` already work this way in deployed
software: a Collection with `totalItems` and no `items`, which ActivityStreams
permits. One collection covers `view`, `listen` and `read` together, and its
size is the number of distinct actors rather than the sum of the three. The
absent `items` is the policy, not an optimisation -- a member list would put on
the wire exactly the thing this section says is nobody's business.

### Attention records when an object goes away

- **A local object deleted by its author takes its attention records with it.**
  Keeping a list of who read a deleted post serves no feature and carries the
  weight of a record about readers that outlives the thing they read.
- **A remote object that becomes a tombstone keeps them.** That record is a
  local actor's own history in their own reader, and it follows the same line
  as every other observation here: what was observed outlives the cached copy.

A time-bounded retention is a reasonable third option and is deliberately not
written as one, because a number chosen without a reason reads as policy once
it is in this document.

## 2. Quiet activity

An actor who has chosen to act quietly produces **no shareable activity** — no
`Read`, `View` or `Listen` that could be federated, referenced or announced.

Their own reader still remembers what they have opened, because that is what
makes a reader usable. The distinction is where the record can travel: a local
read-state belongs to the person whose reader it is, and never becomes a
protocol event or a public count.

A record that exists but is merely filtered out of today's display is the thing
this avoids. Such a row can be counted by tomorrow's policy change, retroactively
undoing a choice made under different terms. Local state is marked as local in
the data, not only in the query that happens to read it today.

- Enforcement: none yet. Planned as an `evidence_type` of `local_state` that no
  federation path reads.

## 3. An author's own reading

An author's views of their own objects are not counted by default. On a
single-actor instance the number would otherwise be entirely the author's own
movements, which tells nobody anything.

This follows established practice: Jetpack Stats excludes a logged-in author's
visits to their own public site, and makes it a per-role setting.

- Enforcement: none yet.

## 3a. Interaction collections: published, and shown

What this server publishes to the world and what it shows a signed-in actor who
can already see an object are two different questions, and the answers differ by
interaction.

| Interaction | Federated collection | Local authenticated view |
|---|---|---|
| `Like` | count only | the actors this server knows about |
| `Announce` | count only | the actors this server knows about |
| `EmojiReact` | **members published** | same as published |
| `View`, `Listen`, `Read` | count only | **count only, for everyone** |

A like or an announce is an expressive act aimed at an object. Its author is
notified of it anyway, and the person performing it knows it is delivered, so
listing it to signed-in local actors who can already see the object discloses
nothing they could not otherwise learn. The published collection still carries
only a count, because the rest of the network has no such relationship to it.

Emoji reactions are the exception, and it is the protocol's doing rather than a
choice made here. FEP-c0e0 requires that collection URL to resolve to the
reaction activities themselves, so a count-only substitute would be unreadable
by the implementations it exists to interoperate with, and requiring public
addressing would empty it for the same ones. Blind-copy lists are stripped. The
honest consequence to tell people: reacting with an emoji is a signed public
act, while a like is not.

Attention is in neither category. A view is not an expressive act and nobody is
told about it, so no surface lists viewers -- not the author's, not an
administrator's.

Any such list is **what reached this server**, not what exists. A remote like
is known only if it was delivered here, so a list may be shorter than a count
taken elsewhere, and both can be right.

- Enforcement: the published collections are implemented
  (`axismundi-object-projections/includes/integrations/reactions.php`); the
  local list surface is not built yet.

## 4. What leaves this server

| Leaves | Does not leave |
|---|---|
| Published objects and their activities, to the audience they address | Drafts, and anything never published |
| `Delete` and `Tombstone` for objects removed after publication | Attention records (`View`, `Listen`, `Read`) unless outbound view is enabled |
| Profile facts the actor chose to publish | Local-only notes, private fields, and anything addressed to nobody |

**Outbound `View` is off by default.** Telling another server that someone here
read a specific post is a disclosure about a reader, not a feature, and it stays
a deliberate choice rather than a default. Reading a remote object is always
counted locally; sending the activity is the part that is optional.

Turning it on applies **from then onwards**. Local read-state recorded while it
was off is never promoted into published activity: a person who read quietly
last year did not consent to announcing it this year, and a migration that
"backfills" those rows would publish a history nobody offered.

- Enforcement: none yet. Default must be off at first install, not merely
  documented as off, and the backfill prohibition needs a check of its own
  because it is the kind of convenience a later migration reaches for.

## 4a. Uploads, storage and traffic

Storing files is the one thing this instance does that has a cost somebody
pays, so the policy says who pays and what the limits are rather than
discovering them when a disk fills.

### What an upload is

Uploading a file creates a media object. It does not publish anything: no
activity is emitted, nothing reaches a feed, and the file is attributed to
nobody until a person deliberately attaches it to an Actor's collection. A
media object that nobody has attributed is a stored representation, not a post.

### Who may upload

Today: people with a WordPress account on this site and the capability to
upload, through the editor and the site's own surfaces.

Not today: third-party ActivityPub clients. The `uploadMedia` endpoint is not
built and is deliberately not advertised in any Actor's `endpoints` mapping.
Advertising it is a capability declaration that cannot be quietly withdrawn,
and it turns storage into a surface any authenticated client may write to. The
four answers below are its precondition, not its follow-up.

### Limits, and who sets them

| Limit | Who sets it | State |
|---|---|---|
| Maximum file size | the host's PHP and web server configuration | inherited, not chosen here |
| Total disk | the hosting plan, declared by the operator in the admin app | **not yet collected** |
| Per-actor storage quota | this instance | **not implemented** |
| Upload rate per actor | this instance | **not implemented** |
| Bandwidth for serving media | the hosting plan, and any CDN in front | inherited |

The honest statement is that an authenticated uploader is bounded today by the
host's per-file limit and nothing else. A per-actor quota and a rate limit are
the work that has to exist before a write surface is opened to clients this
site did not install.

Capacity is declared, not discovered. `disk_free_space()` reports the
filesystem, which on shared hosting is the host's disk rather than the plan's
allowance, so the instance cannot learn its own ceiling and the operator states
it in the admin app instead. That number is a declaration and not a verified
fact: the instance measures what it is actually using and shows both, because a
declaration that disagrees with the measurement is the operator's signal that
one of the two is wrong.

What a declared capacity makes possible is a per-actor quota expressed as a
share of it, a soft threshold that warns, and a hard threshold that refuses an
upload. What it must never make possible is deletion: running out of room is
not permission to remove someone's media, and any deletion stays a decision a
person takes.

Hosting matters here in a way it does not elsewhere in this document. The
operator's plan decides how much can be stored and how much can be served, a
provider may remove or restrict content under its own terms regardless of this
policy, and a backup is the operator's arrangement rather than a promise made
here. Where this policy and a hosting agreement disagree about what may be
stored, the hosting agreement wins in practice.

### Orphans and retention

A file that is never attached to anything still occupies storage. This instance
does not delete unattached uploads today, and no retention period is defined
for them. Any such policy, when it exists, has to distinguish an upload that
nothing references from one whose referencing object is merely not published
yet -- deleting the second would destroy a draft's media.

### Serving what was uploaded

Files served from this domain are served by this instance, so moderation
decisions about them are the operator's responsibility whoever uploaded them.
Remote media is a different case and is covered by the cache layers in the next
section.

- Enforcement: none of the limits above are enforced by code today. The
  capability check on upload is WordPress's own; everything else in this
  section is a statement of the current state, not a guarantee.

## 5. Remote content this instance stores

Receiving or fetching a remote object stores a cached representation so it can
be displayed and threaded. Three layers are kept with different lifetimes:

1. **Identity and relations** — the object's URI, who it is attributed to, what
   it replies to, what it tags or mentions, when each was observed. Retained.
2. **Listing projection** — the minimum needed to show a reference to it in a
   list without its body.
3. **Body snapshot** — content, summary, payload, media. Evictable, and
   re-fetchable while the origin serves it.

When a cached body is discarded, the relations are **not** deleted: they are
marked stale, so a later fetch can tell an unchanged graph from a changed one.
A content warning is part of the body, not the listing, so it is discarded with
it rather than left behind as a residue of text the origin may have removed.

- Enforcement: `audit-relation-lifetime` (7 checks) holds the relation half,
  including that eviction marks relations stale rather than deleting them.

## 6. Deletion

- **A local object deleted after publication** federates a `Delete`, and the URI
  answers as a `Tombstone` to ActivityPub requests and as a 404 to browsers.
  That split is intentional and documented as NON-STANDARD where it deviates.
- **A remote object deleted at its origin** has its body discarded here and its
  identity marked as a tombstone. Relation facts recorded while it existed are
  retained; they describe what was observed, not what is currently served.
- **Nothing here claims to delete a copy on another server.** Federation sends a
  request, not a guarantee, and this policy does not promise what other
  software does with it.

- Enforcement: partial. The 410-versus-404 split is implemented; the rest is
  described, not checked.

## 7. Retention

| Data | Retention |
|---|---|
| Published objects | Until the author removes them |
| Activity records | Not yet bounded; compaction is planned and undefined |
| Attention records | Derived and recountable; no separate retention promise |
| Remote body snapshots | Evictable at any time, under lease |
| Relation observations | Retained, with first and last observation times |
| Delivery attempts and failures | Not yet bounded |

The honest statement today is that **this instance has no retention limit it
enforces**. Nothing prunes the activity record. When a compaction policy is
written, it has to know that one durable table is not a log — the architecture
document records that constraint, and the first compaction job is where it
either holds or fails.

## 8. Blocking, muting and moderation

- A block prevents interaction and delivery between the actors it names.
- A mute affects what the muting actor sees here; it is not communicated to the
  other party and must not be inferred from this instance's behaviour.
- Moderation decisions made here apply to what this instance serves and
  delivers. They do not reach copies that other servers already hold.

- Enforcement: blocks are implemented in the interaction policy layer; the
  statements about reach are descriptive.

## 9. Keys, signatures and identity

- Each actor has a key pair used to sign outbound requests.
- Private keys are never exported, never sent, and never written to a
  representation.
- An actor whose key is missing fails closed: requests are refused rather than
  sent unsigned.

- Enforcement: the fail-closed path is implemented and audited in the bridge.

## 10. What this document does not cover

- Hosting, backups and physical security of the server.
- Legal obligations of the operator in their jurisdiction.
- Any promise about third-party services the operator chooses to connect.

## Open questions

These are listed because an unanswered question in a policy is better visible
than implied:

1. Whether the operator may ever enable outbound `View`, and how a reader is
   told before it is switched on.
2. Whether activity compaction removes payload snapshots only, or rows.
3. Whether a time bound is wanted on retained attention records, and what
   would justify the particular bound.
4. Whether small counts are suppressed or banded, since a public count on a
   quiet instance can identify the people it deliberately does not name.
5. What happens to records about an actor when that actor is deleted here.
