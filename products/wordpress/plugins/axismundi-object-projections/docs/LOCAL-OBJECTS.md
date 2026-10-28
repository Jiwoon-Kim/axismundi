# Local Objects: who publishes, and what the body is

Status: historical decision 2026-09-28; local C2S Note bodies implemented 2026-10-29.

2026-10-29: C2S 원본 본문 저장소와 Create(Note)의 현재 설계안은
[Local Object Store](DESIGN-LOCAL-OBJECT-STORE.md)에 있다. 아래의 과거 시점 설명과
실제 코드의 현재 상태를 혼동하지 않는다. 구현 범위와 검증은
[Create(Note) implementation](IMPLEMENTATION-C2S-CREATE-NOTE.md)에 기록한다.
아래의 WP 본문 설명은 기존 WordPress 소스에 대한 역사적 설명이며 신규 C2S 본문 저장소가 아니다.

This covers two decisions that turned out to be one: where a local Object's publishing identity
comes from, and where its body comes from. Both were derived at projection time from the
`WP_Post`, and both needed to come from somewhere else.

Identity now does. This plugin no longer derives an Actor from `post_author` and no longer falls
back to the site Actor; it asks, through the `axismundi_op_local_object_attribution` filter it
owns, and Axismundi Activities answers from its ledger. This plugin does not read that ledger and
keeps working without it — an Object then simply has no attribution, which is a correct answer
rather than a degraded one. `axismundi-activities/docs/C2S.md` has the submission side.

The body half is unchanged: sections below that describe rendering `the_content` still describe
what happens today, and still describe what is wrong with it.

## 1. `post_author` is not the author

WordPress has one field for "who may edit this" and Axismundi needs another for "whose this is".
They coincide only while every account has exactly one Person Actor.

| | means |
|---|---|
| `post_author` | the WordPress account that may edit the post |
| `Object.attributedTo` | the Actor the Object belongs to |
| `Create.actor` / `Update.actor` | the Actor that performed this lifecycle Activity |

The moment somebody publishes as an Organization these separate. Worse, the separation is not
recoverable afterwards: changing `post_author` leaves nothing that records which Actor the Object
actually went out as, and no way to tell whether the new value should be a Person or an
Organization.

GitHub and Trac have the same split and keep it: a contributor may edit an issue without becoming
its reporter. A contributor editing an Organization's Article should produce

```txt
Update.actor         the contributor's Person Actor
Article.attributedTo the Organization Actor, unchanged
```

Changing attribution is therefore a deliberate reassignment that says so in the payload, never a
side effect of an ordinary edit, an import, or an administrator saving on somebody's behalf.

**Today** `axismundi_op_post_actor_uri()` resolves `post_author` on every projection, so editing
responsibility silently decides federated authorship. `axismundi-calendar` is the only product
that records a choice instead (`acting_actor_identity_id`), and its resolver already answers the
hard parts: a stored Actor stays, an explicit choice is re-verified with `can_act_as()` at save
time because the switcher ran earlier, and the acting Actor applies only when the author is saving
their own post. Its backfill writes down the answer that was already being derived, so nothing
observable changes — that is the shape any migration here has to have.

## 2. The ledger decides the actor, not the projection

The flow today runs backwards:

```txt
OP lifecycle.php  $actor_uri = axismundi_op_post_actor_uri( $post )
                  do_action( 'axismundi_op_object_publish_candidate', …, $actor_uri )
                          ↓
Activities        records what OP decided
```

Projection settles identity before the ledger sees it. ActivityPub puts it the other way round:
the Activity carries the `actor`, and §6.2 says the actor *SHOULD* be copied onto the object's
`attributedTo`. The intended layering is

```txt
Actors        offers the current acting Actor
C2S adapter   turns a publish/update into a command carrying that actor
Activities    verifies it with can_act_as() at the command boundary, records Create/Update
OP            observes local Create/Update and materializes the Object
```

Actors offers, Activities verifies and records, OP reads. No layer guesses.

An Object with no Actor is a valid state rather than a failure: `attributedTo` is not required by
AS2, so OP can project a readable Object from a `WP_Post` alone. It simply cannot be published,
because a `Create` has no actor to carry. Such an Object is reachable at its own URL and belongs
to no collection, outbox, or delivery.

### Attribution arrives when an Actor exposes the media, not when it is uploaded

An attachment is the clearest case of that unattributed state, and it is not permanent.
Uploading a file is storage: the Media Library is where a *user* manages files, so the upload
records who may edit the file and nothing about whose Object it is, and
`axismundi_op_attachment_actor_uri()` therefore defaults to empty and must never be made to
read `post_author`. What changes that is an **Actor adding the media to a collection** in order
to display it publicly. That Add is the publication, it names an Actor, and from then on the
media has an author.

So the two surfaces are different things with different owners:

| Surface | Unit | What it records |
|---|---|---|
| Media Library | the WordPress user | which files exist and who may edit them |
| A media collection | an Actor | what that Actor puts on public display |

One consequence is already visible and is recorded here rather than discovered later.
Quoting is a decision only an author can make, so an Object with no `attributedTo` has nobody
to grant consent, and Activities refuses the request: `axismundi_act_resolve_quote_request_target()`
returns its fail-closed default, which is why an inbound QuoteRequest against an unpublished
Object is declined rather than auto-approved. **That stays fail-closed.** An attachment page
appearing in the Object registry does not make it quotable; being added to an Actor's
collection does. The alternative -- letting the embedding Article's policy decide for the
media inside it -- would move policy ownership from the Object to whatever document happens to
carry it, and is not what we do.

Two things to settle in the unified engine work, both named now:

- `axismundi_op_resolve_quote_request_target()` answers only for `'post' === $post_type`, so
  `page`, `attachment` and the other registered profiles cannot be quote targets for a reason
  that has nothing to do with policy. The resolver belongs on the shared source reader, where
  it answers for any registered local profile and the policy is read per type.
- Exposure attribution is a **second** read, not a widening of the first.
  `axismundi_act_get_object_attribution()` means something narrow and stable: the actor of the
  `Create` that began this object's current generation. Teaching it about `Add` would put
  `Create` and `Add` in competition for one answer and leave "which collection's Add" with no
  rule, so it keeps its meaning and a separate projection answers "which Actor exposed this,
  and when". A media Object reads as unattributed under the Create query, correctly, and is
  attributed through the exposure read.

### Open: one author, several exposures

An `Add` confers **no authorship**, and that is the specification's position
rather than a preference. ActivityPub defines its side effect as putting the
object into the collection named by `target` and nothing more, and FEP-400e --
final since 2022-02-04 -- makes the separation explicit: in a publicly
appendable collection the `Add` is sent by the **collection owner's** server
while the object keeps the `attributedTo` of whoever created it. The actor of an
Add is the one who owns the collection, which in the general case is precisely
not the author. So "the first Add fixes attribution", recorded here earlier as a
recommendation, was wrong as stated.

The case that shows it is already on our roadmap: a server-to-server binary
cache materialised as a shadow attachment page can be added to a local Actor's
collection. Attributing it to the Actor who added it would reassign a remote
author's work on the strength of somebody filing it, and this plugin already
disclaims exactly that -- a remote object's URI stays its identity and a local
shadow is never a replacement identity.

What remains true is narrower. Attribution is settled at **origin**: by a
`Create`, whose actor the ActivityPub Recommendation says SHOULD be copied to
`attributedTo` -- a SHOULD, so even that is policy resting on a recommendation
-- or, for a remote object, by whatever its origin declared, which we observe
and never overwrite. The open question is only what settles it for a **local
file whose origin recorded nobody**, and the candidate rule has three
conditions rather than one: there is no attribution yet, the object is local,
and the Actor exposing it owns the file. Anything outside those three is
membership only.

| Shape | Consequence |
| --- | --- |
| First exposure settles it, under the three conditions above | One author; later exposures and other Actors' collections add no claim |
| Attribution follows the most recent exposure | The author of a file changes when somebody else displays it, and a cached payload disagrees with the current one |
| `attributedTo` carries every exposing Actor | Spec-legal -- AS2 defines it as "one or more entities", with no functional declaration -- but consent decisions and peer display both lose their decider |

The first is recommended, with its three conditions stated, and it is the
owner's call. Two consequences come with whichever is chosen, and neither is
free. Attribution appearing later means the stored Object projection changes
after the fact, so there has to be a rule for whether peers who already hold the
object are told -- an `Update` -- or whether they see it only on a re-fetch; and
this project has already decided not to pollute feeds with Updates about media
metadata. And a quote approval needs one decider, which is the local requirement
that rules out the array even though AS2 permits it.

FEP-400e is out of scope and its mechanism is not being adopted. What is worth
taking from it now is the shape: a collection's owner has authority over the
collection, including who may add and what may be removed, and that authority is
a different thing from authorship of what sits inside it.

### Media visibility is pre-Actor, and that is legacy

Follow the Add rule one step further and the current media visibility tiers stop lining up
with it: an unpublished media Object has no audience, so a tier on the file row is answering a
question about a publication that has not happened. The field belongs to the Media Library, so
the argument is recorded with it, in that plugin's `docs/DATA-MODEL.md` section 6.1 -- one copy,
beside the resolver it describes.

What matters on this side is why the tiers are still load-bearing here. Measured 2026-10-17:
they are the only thing withholding a private file, and the core attachment profile was
republishing what they withheld until both descriptor paths were made to ask through
`axismundi_op_attachment_descriptor_visible`. The default there is permissive because core has
no way to say a file is for some readers and not others; a product that owns media visibility
answers it.

## 3. Attribution is not locked; the origin domain is the authority

An earlier draft added a local attribution lock. It is not needed and it is not the standard's
model. ActivityPub gives the origin server authority over its own objects, so a receiver should
trust what the object's own domain says about it, and a publisher should not maintain local
machinery that second-guesses its own origin.

Decoupling `attributedTo` from `post_author` removes the hazard a lock was defending against: the
only way attribution changes is that somebody deliberately reassigns it, which is a legitimate
Update.

Inbound, origin and permission are two different questions and both have to be asked.

```txt
origin      host( activity.actor ) === host( object.id )     necessary, never sufficient
permission  activity.actor relates to the object's recorded author
```

An earlier revision of this document proposed the host comparison **as** the authority test for
Update and Delete. That is wrong, and it is wrong in the dangerous direction: everyone on a shared
instance passes it, so Alice could edit or delete Bob's Object because they happen to have the same
hostname. The host test only establishes that the activity came from the server that speaks for
that URL. It cannot say which of that server's Actors may act on it.

**Today** the checks are inconsistent, and the stricter one is the better one:

| activity | test today |
|---|---|
| Create / Update | actor compared to the payload's own `attributedTo` |
| Delete | actor compared to the **stored** `attributed_to_uri` |

Delete asks the right question. Create and Update compare the document against itself, which a
sender controls on both sides, so the check passes for a payload that claims a different author
than the one already recorded. They should compare against the stored author too, and all three
should also carry the origin test.

That leaves a question this document does not answer. Our own model deliberately allows
`Update.actor` to differ from `attributedTo` — a contributor editing an Organization's Article —
and ActivityPub does not require them to match. A conservative inbound rule that demands
`actor === stored attributedTo` therefore refuses exactly the case we intend to send. Accepting a
third party's Update from the same origin needs a delegation model to say who else may act on an
Object, and there is none yet. Until there is, the conservative rule stands and the gap is known
rather than accidental.

Reassignment of `attributedTo` is a separate motion in any case: the existing author, or a defined
delegation, has to authorise it. It is never a side effect of an ordinary edit.

`includes/remote-collections.php` already compares hosts correctly for collection pages — lowercase,
exact match, never a suffix test — and is the implementation to reuse for the origin half.

## 4. The body is authored blocks, not rendered HTML

`Article.content` is built by running the whole `the_content` pipeline. That was modelled on Core's
feeds, which is a reasonable thing to have copied — Core does exactly this, in `feed.php` — but
Core also has a second model for the same data, and it is the right one here: WXR export ships
`post_content` itself.

Running `the_content` drags the web presentation along with the document:

- custom emoji become `<img>`, which receiving sanitizers drop, taking the emoji with them
- dynamic blocks render this server's current state, so a re-fetch can differ with no `updated`
- adverts, sharing controls and related-post filters attach themselves to federated bodies
- any plugin filter becomes part of the trust boundary

The summary path already knew this and avoids `the_content`, calling `do_blocks()`, `wptexturize()`
and `wpautop()` directly. Generalizing that caution to the body is the whole change.

```txt
post_content
  ├─ parse_blocks()
  │    ├─ attachment ids           → attachment[]
  │    ├─ embed intent             → FEP-8967 Link attachment
  │    └─ emoji/mention/hashtag use → tag[]
  └─ allowed blocks → static HTML → FEP-b2b8 allowlist → content
```

One parse, three outputs, so the same AST answers every question about the document.

### Block policy

Three classes, by exact block name. `is_dynamic()` must not decide this: `core/image` has a render
callback and still has stored HTML that belongs in an Article.

| class | blocks | treatment |
|---|---|---|
| publish | paragraph, heading, list, quote, code, preformatted, verse, image, gallery, audio, video, footnotes | stored HTML, normalized |
| transparent | group, columns, column | drop the wrapper, keep allowed children in document order |
| omit | query, latest-posts, navigation, post/theme/widget blocks | not document content |
| link | embed | a URL-only paragraph, never an iframe |

Layout containers are flattened rather than preserved. Article and long-form interoperability is
the goal, not a round trip of Gutenberg layout; anything that only means something with CSS or
JavaScript does not survive.

### The allowlist

FEP-b2b8 permits, verbatim: `p span h2–h6 br a del pre code em strong b i u ul ol li blockquote
img video audio source ruby rt rp`. It does **not** include `table`, `figure`, `div`, `h1`, `sup`
or `sub`, and forbids CSS, JavaScript, navigation, like/bookmark affordances and "Read more".

`axismundi_op_allowed_html()` is currently wider than that — `h1`, `figure`, `figcaption`, `hr`,
`div`, the table elements — because it was serving WordPress-to-WordPress structure preservation
from the same string. With that job removed it should narrow, keeping at most a documented
Markdown-shaped extension (`table` family, `hr`, `sup`, `figure`/`figcaption`).

### Gallery is the one exception

Pasted HTML re-blocks well: `core/image` recovers its id from the `wp-image-N` class, and tables,
lists and quotes have raw transforms. `core/gallery` does not — its only `from` transforms are
`shortcode` and `files` — so a gallery arrives as a row of separate images and loses its columns
and order.

So a small, allowed set of block comments stays inside `content` as a structure hint. `wp_kses`
preserves comments, so this survives our own sanitizer. Two rules keep it from growing:

1. A comment is allowed only for a container **proven** unrecoverable from HTML alone. Today that
   is `core/gallery` and nothing else.
2. A receiver must never run them. `do_blocks()` on a remote document would let it read this
   site's database.

The sending site's numeric attachment ids are meaningless elsewhere and are not sent; an importing
site fetches from `attachment[]` or the image URL and assigns its own.

There is no `source` property carrying full Gutenberg serialization. The gallery hint is enough for
WordPress-to-WordPress structure, and a document that loses the comments still shows its images.

## 5. Two layers: a current Object, and a record of what was published

Only two layers are decided here.

```txt
wp_post       the editable source, current
OP            reads the current wp_post and projects the current AS2 Object, dynamically
Activities    what was published or interacted with: actor, object URI, operation, time,
              and the exact serialized payload for as long as delivery needs it
```

What leaves the site is always a sufficient snapshot — a receiver cannot be assumed to hold any
earlier revision, so an outgoing `Update` carries the whole Object. Keeping that payload internally
is a different question, and the answer is "for as long as delivery needs it": while a send is
pending or retrying, the exact bytes; afterwards, compacted or dropped under a retention policy,
leaving at most the light facts above plus a payload hash.

Deliberately **not** decided here:

- Whether a reader can see a previous version of a post. That is a history feature with its own
  design, not a side effect of how federation records things.
- Whether old bodies are kept as snapshots, diffs, or not at all.
- Any use of WordPress revisions as the anchor. Core revisions are an editor feature with
  configurable retention (`wp_revisions_to_keep`), autosave semantics, and meta that is only
  revisioned when registered as such. They are not a federation durability guarantee and tying
  Activity records to them would make the ledger depend on an editor setting.

An earlier revision of this document specified a Git-shaped revision store — parent revisions,
periodic snapshots, structured diffs, squashing — and a link to Core revisions. That was a design
for a history product written inside a document about federation, before anyone had decided to
build one. Recording every Update's full body forever also grows roughly with the number of edits,
which is a cost worth choosing on purpose rather than inheriting.

The outbox is a projection of the ledger, not the ledger itself. ActivityPub §5.1 leaves how much
of it to expose to the implementation, so it can be filtered by audience and bounded by retention
without the stored record having to match it.

### A ledger that keeps bodies is a second cache

This is not a hazard to avoid in a future design; it is already happening. `ax_activities` has a
`payload_json longtext NOT NULL`, and an inbound `Create` stores the whole activity including its
embedded object. Measured 2026-09-28 on 86 inbound rows: the largest `Create` carries an embedded
object with its `content` in the payload, while `EmojiReact` rows reference their object by URI and
carry no body.

`wp_ax_remote_objects` is where a remote body lives, with a retention window, leases that hold a
row past expiry while something still refers to it, and a Delete observer that replaces the body
with a Tombstone. None of that reaches a copy sitting in `payload_json`. Expiring the cache, or
honouring a remote author's Delete, leaves the body, the author and the attachments in the ledger —
which is both a retention policy that does not do what it says and a copy of somebody's deleted
post that we keep.

So the two directions store different things:

```txt
inbound    what we observed and how we handled it: activity URI, actor, type, time,
           the object URI as a reference, the outcome
           the body belongs to wp_ax_remote_objects and expires with it

outbound   the exact serialized payload while a send is pending or retrying,
           because the bytes have to survive a retry
           afterwards compacted under retention; the current body is OP's projection
```

Fixing this is its own piece of work and needs a migration for the rows that already carry bodies.
It is written here because it is the same question this section answers for local objects, and
because a design that says "the ledger does not keep bodies" should not be read as describing what
the code does today.

## Order of work

1. ~~C2S vertical slice~~ — the command service exists and the editors submit into it; the
   trigger is still a projection observing a save. `docs/C2S.md` §9 carries the rest.
2. ~~Remove the site Actor fallback; allow the Actor-less projection.~~ Done. An unattributed
   local Object is readable, addressed publicly, omits `attributedTo`, and earns no Create,
   outbox entry or delivery. The permission is declared by the transformer (`local`), so an
   unattributed *remote* payload is still refused.
3. Make the inbound tests ask both questions — same origin, and the stored author — in Create,
   Update and Delete alike.
4. Replace the body pipeline with the block serializer above.
5. Move Note, Forum and finally Calendar onto the recorded actor.
6. Stop the ledger keeping remote bodies, and migrate the rows that already do.

Calendar goes last because it is the only one that works today.

## Measurements behind this

All taken 2026-09-28 unless noted.

- FEP-b2b8's allowlist, and its exclusions, read from the FEP itself.
- FEP-9098: the shortcode belongs in `content`; replacing it is the receiver's job; `<code>`/`<pre>`
  are excluded and replacement happens in text nodes only.
- `core/gallery` has no `raw` transform; `core/image` reads `wp-image-\d+`; `core/embed`'s raw
  transform needs a `P` whose text is one `https://` URL that does not end in a file extension.
- `wp_kses` keeps HTML comments, run against `axismundi_op_allowed_html()`.
- The site Actor is non-public, so the author fallback already returns ''.
- W3C's Activity Streams primer puts mention linkification on the receiving implementation, so
  sending plain `@name` with a matching `tag[].name` is correct; Mastodon's own issue #19101 asks
  for exactly that substring matching and is still open.
