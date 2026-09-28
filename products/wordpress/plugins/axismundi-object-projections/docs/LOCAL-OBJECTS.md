# Local Objects: who publishes, and what the body is

Status: **design, not implemented.** Decided 2026-09-28. Nothing in this document describes
current behaviour; the sections marked "today" describe what is there now and why it is wrong.

This covers two decisions that turned out to be one: where a local Object's publishing identity
comes from, and where its body comes from. Both are currently derived at projection time from the
`WP_Post`, and both need to come from somewhere else.

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

The site Actor is not a fallback for this. It is an Application/Instance identity — the server, not
the author of somebody's Article. `axismundi_op_local_author_actor_uri()` currently falls back to
it both when the user has no Actor *and* when the user's Actor is not public, which overrides a
deliberate choice to stay private. Measured 2026-09-28: the site Actor is itself non-public, so the
fallback already yields '' and the path is dead — but it is one setting away from attributing every
Actor-less user's posts to the server.

## 3. Attribution is not locked; the origin domain is the authority

An earlier draft added a local attribution lock. It is not needed and it is not the standard's
model. ActivityPub gives the origin server authority over its own objects, so a receiver should
trust what the object's own domain says about it, and a publisher should not maintain local
machinery that second-guesses its own origin.

Decoupling `attributedTo` from `post_author` removes the hazard a lock was defending against: the
only way attribution changes is that somebody deliberately reassigns it, which is a legitimate
Update.

Inbound, the authority test should be the same one in every direction:

```txt
host( activity.actor ) === host( object.id )
```

`includes/remote-collections.php` already compares hosts this way for collection pages: lowercase,
exact match, never a suffix test. **Today** the checks are inconsistent — Create/Update compare the
activity's actor against the payload's own `attributedTo`, while Delete compares against the
*stored* `attributed_to_uri`. Delete is the stricter of the two; both should become the host test.

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

## 5. Activities is a Git-shaped ledger, not a re-render

A past Activity must never be rebuilt from the current `wp_post`. Themes, sanitizers, the allowlist
and attribution all move, and a rebuilt Create would quietly become a different thing than what was
published.

So each recorded revision carries the actor, the object URI, its parent revision, the source
WordPress revision, and a canonical snapshot of the Object as published — or a content-addressed
hash that restores it exactly. Storage can behave like Git rather than SVN: `Create` holds a full
snapshot, `Update` may hold a structured diff against its parent with periodic new snapshots,
identical payloads deduplicate by hash, and old revisions squash under a retention policy that
keeps the current snapshot and a verifiable lineage.

What leaves the site is always a sufficient snapshot: a receiver cannot be assumed to hold any
earlier revision.

## Order of work

1. C2S vertical slice: publish/update → command → ledger → OP materialization.
2. Remove the site Actor fallback; allow the Actor-less projection.
3. Make the inbound authority test the host comparison, in all three of Create, Update and Delete.
4. Replace the body pipeline with the block serializer above.
5. Move Note, Forum and finally Calendar onto the recorded actor.

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
