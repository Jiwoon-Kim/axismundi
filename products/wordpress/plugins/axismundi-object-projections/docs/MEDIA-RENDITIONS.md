# FEP-1311 media renditions — locked contract

> Status: **Implemented.** Covers how a Media Library attachment advertises
> its available media versions. The Media Library rendition API, the Object Projections
> representation transition, and the shared-folder consumer are separate increments.
>
> Bilingual note (EN/KO): standalone 첨부는 **이미 생성되었거나 신뢰된 서비스가 주소화한** 파생본만 광고한다. Embedded Article/Note는 원본 자체가 1024px 이하의 모든 hard cap을 통과할 때에만 그 bounded source를 하나의 표시 rendition으로 쓸 수 있다.

## 1. Why adopt a "not yet supported" FEP

[FEP-1311](https://codeberg.org/fediverse/fep/src/branch/main/fep/1311/fep-1311.md) (DRAFT,
2024-12) documents media attachments. Its *multiple media versions* section openly says the
pattern is *"currently not supported in the Fediverse"* — true for the **general** fediverse,
and normally a reason to defer.

It does not apply here, because **Axismundi ↔ Axismundi shared folders make us the consumer**:
a peer building a shadow attachment must pick a version it can afford (a thumbnail must not
cost 5 MB) and must be able to derive its own copies without being handed the original.

Adopting FEP-1311's shape is therefore **early adoption of a drafted standard, not a private
extension** — the preferred outcome whenever a real need exists.

It is also semantically correct rather than a workaround. AS2 defines `url` as:

> "Identifies one or more links to **representations of the object**"

Renditions *are* representations, and so is an HTML page. One `url` array satisfies three
contracts at once (§2).

## 2. Target representation

```json
{
  "id": "https://example.com/?attachment_id=44",
  "type": "Image",
  "name": "독립 미디어 객체 제목",
  "url": [
    { "type": "Link", "href": "…/image-1024x683.jpg", "mediaType": "image/jpeg", "width": 1024, "height": 683, "size": 148320 },
    { "type": "Link", "href": "…/image-300x200.jpg",  "mediaType": "image/jpeg", "width": 300,  "height": 200, "size": 18420 },
    { "type": "Link", "href": "…/media/image/44/",    "mediaType": "text/html" }
  ]
}
```

Satisfied simultaneously:

| Contract | How |
|---|---|
| FEP-1311 | `url` is a Link array of media versions with `mediaType` / `width` / `height` / `size` |
| FEP-b2b8 | *"at least one Link SHOULD have mediaType `text/html`"* — present, position-independent |
| ROUTING §2 | `id` stays the stable `/?attachment_id={ID}`; the human page survives as a Link |

## 3. Frozen rules

1. **`id` is unchanged.** The canonical `/?attachment_id={ID}` identity never moves.
2. **Media Links first; the `text/html` page last.** An `Image` object's naive `url[0]`
   consumer expects media — an HTML page in that slot would be read as the image. FEP-b2b8's
   `text/html` SHOULD is satisfied regardless of position, so ordering costs nothing.
3. **Standalone original files are never advertised.** This is the same *bounded derivative,
   never the original by default* rule as REMOTE-ASSET-CACHE.md. **If no derivative exists,
   emit only the HTML Link — never fall back to the original.** An embedded Article/Note may
   opt into its source only when that complete image already satisfies its hard dimension,
   pixel, and byte caps; it is not a fallback for an oversized original.
4. **At most 4 versions**, largest first, deduplicated by URL **and** by dimensions.
5. **Only already-generated, provider-addressed trusted virtual derivatives, or a cap-fitting
   embedded source.** Projection
   never generates or fetches an image. A virtual derivative must use the exact HTTPS URL
   recorded by the rendition provider or resolved by WordPress image downsize for metadata
   that explicitly marks the size virtual, and must use an allowlisted image-service host.
   Runtime URLs remain candidates, not authority: trusted-host, dimension-transform,
   dimension, pixel, and source-byte gates still apply before advertisement.
   Provider discovery is scoped so an editor-originated REST request cannot suppress the
   federation lookup; any provider editor policy is restored immediately after that lookup.
6. **One rendition builder** serves all three roles: Attachment Single, an Article or Note
   `attachment[]`, and `preview.attachment`. Their canonical `id`, `type`, and `mediaType`
   must not drift. The standalone role serializes the resulting ladder as FEP-1311 Links;
   an embedded role selects one interoperable scalar media URL. Role-dependent descriptive
   fields such as `name` are applied by the projection layer (§5), not by the builder.
7. **The rendition policy is role-dependent** — the builder is shared, the policy narrows it:

   | Role | Policy | Why |
   |---|---|---|
   | Standalone Attachment Single | the full ladder (max 4) | its consumer is an Axismundi peer choosing a size it can afford — the reason §1 exists |
   | Article/Note `attachment[]` / `preview.attachment` | **one scalar media URL, capped at 1024**; may use the local source only when it already fits every cap | Misskey requires a string URL for embedded files and Mastodon needs one directly fetchable representation; 1024 is also WordPress's own `large` default |

   A policy may only **narrow**. The embedded source exception is explicit and bounded by the
   role's caps; it cannot expose a larger original.

## 4. Ownership

**Media Library owns selection:**

```
axismundi_media_federation_renditions( int $attachment_id, array $policy = array() )
```

- enumerates **existing** derivatives only
- enumerates policy-selected names that exist in provider metadata; it does not require the
  same size to remain registered in the current request
- applies pixel / byte / dimension caps; max 4; dedupes
- excludes the original except for an explicit embedded policy whose source already fits every cap
- honors public / locked / sensitive policy (fail-closed)
- returns `url`, `mediaType`, `width`, `height`, and an accurate `size` when known
- omits an ordinary entry whose file is missing or whose `filesize` cannot be read
- may admit an explicitly marked, trusted virtual derivative without `size`; it never
  fetches the CDN response or substitutes the source file's bytes as the derivative size
- an embedded-only policy may ask a trusted provider for one bounded derivative based on
  the source dimensions even when that virtual size is not persisted in attachment metadata;
  the result still passes every host, transform, dimension, pixel, and source-byte gate
- the existing singular `axismundi_media_feed_rendition()` remains

**Object Projections serializes only.** It must never read `wp_get_attachment_metadata()`
internals or reconstruct size names — the same boundary as the rest of the Media Library
adapter (TRANSFORMERS.md).

## 5. Metadata and role-dependent `name`

FEP-1311's `name` guidance applies to a **media attachment embedded in another object**.
ActivityStreams' general `name` property instead names the object itself. Axismundi has both
roles, so `name` is deliberately context-sensitive while the rendition list remains shared:

| Projection role | `name` |
|---|---|
| Standalone Attachment Single (`/?attachment_id={ID}`) | the attachment's `post_title`; this is a first-class object with its own HTML page, likes, and `usedIn` collection |
| Article `attachment[]` | `_wp_attachment_image_alt`; omit when empty, and never substitute the title |
| `preview.attachment` | the same embedded-media descriptor as the Article attachment: alt text or omission |

The current accessibility gap is therefore **not** that Attachment Single uses its title. It
is that embedded media does not emit the WordPress alt text. A filename-like title must not be
invented as alt text for a screen reader.

There is no settled ActivityStreams property for carrying both the standalone object's title
and image alt text on that same standalone representation. A private extension is deferred
until the shared-folder consumer and binary substrate establish a demonstrated need (§11).

`id` is **not** role-dependent, and both roles state it. An embedded descriptor names the same
Object its standalone projection does, because an attachment here is an independent Object with
its own page rather than a part of the document carrying it: stating the identity is what lets a
consumer reach the renditions, `usedIn` and interaction collections that the embedded form
deliberately leaves out. The Media Library adapter already emitted it; the core profile does now
too, and an earlier assertion that embedded descriptors are anonymous -- the FEP-1311 reading --
was replaced rather than left to contradict the adapter. What still differs by role is `name`,
the `url` shape (one interoperable string embedded, the Link ladder standalone), and the
rendition policy.

ActivityStreams' own examples agree with the standalone half of that table: the `Image`,
`Video`, `Audio`, `Document`, and `Page` examples in the vocabulary all put a human title in
`name` -- "Cat Jumping on Wagon", "Puppy Plays With Ball", "4Q Sales Forecast". Alt text in
`name` is a convention of the **embedded attachment position**, not of the media types, which
is why the split is by role and not by type.

Two refinements, measured 2026-10-17 and pinned:

- **Alt text never falls back into the standalone `name`, in either direction.** The tempting
  rule -- use the title, but prefer alt text when it exists -- was rejected: it makes the same
  object's name change meaning the moment someone fills in an accessibility field, so a peer
  that cached the title sees an unexplained rename, and the title is then unreachable. An
  attachment whose alt text and title both exist publishes the title and keeps the alt text
  for the embedded descriptor.
- **An untitled attachment omits `name`.** It previously published a localized
  `(untitled media)` placeholder, which federates one install's UI string as an object's name.
  Omission is the honest answer, and WordPress fills `post_title` from the filename at upload,
  so the case is rare by construction.

### WordPress's own attachment fields

These are the fields core itself can fill, and where each one lands on the standalone object:

| Media Library field | WordPress storage | Standalone object |
|---|---|---|
| Title | `post_title` | `name`, omitted when empty |
| Alternative Text | `postmeta._wp_attachment_image_alt` | not emitted; it is the embedded descriptor's `name` |
| Image Caption | `post_excerpt` | `summary` |
| Description | `post_content` | `content`, through `wpautop()` |
| File URL | `_wp_attached_file` + uploads | the `url` Link ladder |

One collision is known and left as it stands: a sensitive attachment's content warning also
occupies `summary`, and it wins over the caption. A viewer warning outranks a caption, and
fail-closed is the rule the rest of sensitivity handling follows, so the caption is dropped
rather than concatenated. Anything better needs a separate property, not a merged string.

The `axismundi_op_core_attachment` and `axismundi_op_media_attachment` filters are where a
theme or the Media Library plugin adds what core does not store -- EXIF, credit, a license,
dimensions core never recorded. That is the intended way to enrich this object, and it is also
why the base profile stays narrow: it projects only what every WordPress install actually has.

**The two paths must agree.** The core profile registers at priority 20 and the Media Library
adapter at 10, so the adapter wins wherever it is active. If only one of them is changed, the
same attachment means different things depending on whether a plugin is active. Both are
pinned: `tests/audit-core-attachment.php` for the core profile, and
`tests/audit-media-library.php` for the adapter and for the embedded side.

- **`size`** on every locally measurable Link; omitted rather than guessed for a trusted
  virtual derivative.
- **`duration`** for Video / Audio, from WordPress metadata.
- `sensitive`, the content warning, license, and `usedIn` keep their existing contracts.

## 6. Per-MIME policy

- **Image** — standalone objects are derivatives only. Embedded Article/Note media may use a
  complete source that already fits its explicit 1024px, pixel, and byte caps; otherwise no
  derivative → HTML Link only.
- **Video / Audio** — there is no transcoding substrate, so **no multiple versions are
  invented**. Whether the original is publicly downloadable is a *separate download policy*,
  not a rendition question.
- **Document** — HTML-page centric. Original download only under an explicit permission policy.
- "Max 4" is **not** blanket-enforced across every type. The first increment targets images.

## 7. BlurHash

- **Never computed during a render request.**
- Computed asynchronously on upload / metadata regeneration; the Media Library stores the value
  and its processor version; Object Projections emits stored values only.
- Ships **separately** from the first FEP-1311 increment.

## 8. Shared-folder consumer (the reason this exists)

- Pick the **smallest suitable** rendition.
- **Re-validate** on receipt: MIME by file signature, pixels, bytes.
- No suitable rendition → **metadata-only**.
- **Never auto-fallback to an original; never hotlink.**
- Canonical identity is the **remote object URI**; the local shadow attachment ID is never
  exposed externally.

## 9. Breaking change (acceptable at alpha)

Today an attachment emits `url` = the HTML page and `attachment` = a single file Link. That
double structure **merges into `url[]`**. Because every affected package is alpha, this is a
**direct transition** — no long-lived duplicate compatibility fields.

Inbound tolerance is unchanged: remote objects that carry a **single-valued `url`** must keep
parsing, since that is what the rest of the fediverse sends today.

## 10. Test gates

- `url` is a media-Link array with the `text/html` Link last.
- An embedded descriptor and its standalone projection state the same `id`, in both the core
  profile and the Media Library adapter.
- No original URL appears anywhere in `url`.
- At most 4 entries; dimension and URL dedupe hold.
- An image with **no** derivatives emits the HTML Link only — no original leak.
- Attachment Single keeps `name = post_title`, omits `name` when the title is empty, and
  never substitutes alt text even when alt text exists.
- The caption reaches `summary` and the description reaches `content` on Attachment Single;
  a content warning displaces the caption rather than joining it.
- Article `attachment[]` and `preview.attachment` use alt text for `name`; an empty alt omits
  `name`, with no title fallback.
- `size`, when present, is accurate; a trusted virtual derivative omits it.
- Attachment Single, Article `attachment[]`, and `preview.attachment` produce identical
  canonical IDs and ordered rendition links; their role-dependent `name` values follow §5.
- private / locked media stay fail-closed.
- A remote object with a single-valued `url` still parses.

## 11. Deferred

Standalone alt-text representation, `digestMultibase`, and `focalPoint` land **with the
shared-folder binary substrate**, where
they pay for themselves: `digestMultibase` lets a peer verify a fetched file against what was
advertised and pairs naturally with the blob substrate's `content_hash`
(REMOTE-ASSET-CACHE.md), and `focalPoint` needs the processor-version story. Standalone alt
text will be added only if the consumer proves it cannot preserve that value from the
embedding Article or collection item without a private vocabulary extension.

### Embedded media: whose audience, whose alt text, and which shape

Design direction recorded 2026-10-17, implementation not attempted. The useful
starting assumption is that **the embedded descriptor is what a WordPress
install with no Media Library plugin emits.** Tiers, locked folders and
collections are that plugin's policy and belong to its collection model; the
core profile reading a post's own blocks has no such concepts and should not
acquire them.

Two audiences, inherited from two different places:

| Form | Audience |
| --- | --- |
| Embedded descriptor inside a Note or Article | the embedding object's. The author put this file in this document; the document's addressing decides who sees it |
| Standalone media page | inherited from the collection that exposes it, which is the same `Add` that gave it an author |

**This contradicts something already shipped**, and the contradiction is the
owner's to resolve rather than ours to quietly keep. The core descriptor now
asks the Media Library whether a file may be embedded at all
(`axismundi_op_attachment_descriptor_visible`), which closed a measured case
where a public Article advertised a file its owner had put in a
password-protected folder. Under the model above that is not a leak but an
authored act: the author embedded it in a public post, and the document decides.
The argument for leaving it as it stands is that federated publication actively
distributes the URL. The argument for the model above is that the gate never
protected the bytes -- DATA-MODEL.md dropped download policy precisely because
core cannot restrict file delivery -- so withholding the descriptor costs the
author their own embedding and buys no protection. Recorded, not changed.

The Media Library's architecture is pre-acting-actor and this is where that
shows: its folders and permissions are keyed on the WordPress user, while the
collection a reader should see has to change when the acting Actor changes.
That is the same legacy recorded in that plugin's DATA-MODEL.md section 6.1, and
the collection model is where it gets answered.

#### Shape: a typed media Object, not a Link

`attachment` has domain `Object` and range `Object` or `Link`, so a Link is
spec-legal and the instinct that embedded media is "really a reference" is
sound. Two things settle it the other way.

A `Link` cannot carry an audience. Its declared properties are `href`, `rel`,
`mediaType`, `name`, `hreflang`, `height`, `width` and `preview` -- no `to`,
`cc`, `bto`, `bcc`, no `attributedTo`. That turns out not to be an objection to
Link, because the embedded form inherits its audience from the document and
needs none of its own; it just means the audience argument cannot decide the
shape.

What decides it is interop, already measured here: peers parse `attachment[]`
expecting a typed media object with a fetchable file. An Image descriptor whose
`url` was the HTML page was read as broken media by Mastodon and ignored by
Misskey, and Misskey needed `url` as a scalar string rather than a list. So the
embedded form stays a typed `Image` / `Video` / `Audio` / `Document` with a
scalar `url`, and the reference the Link instinct wants is carried by `id`,
which it now states. One shape for Note and Article both: two shapes for the
same thing is the duplication being removed, and nothing about a Note's
attachment differs from an Article's.

#### Alt text belongs to the embedding, and WordPress already stores it there

Measured 2026-10-17, and it is a defect rather than a question. WordPress keeps
the alt text of a *particular embedding* in the block's own markup -- the
`alt` attribute of the `<img>` inside the image block -- so two posts embedding
one file carry two different author-written values. Our descriptor reads neither:
it reads `_wp_attachment_image_alt`, the Media Library's single shared field.

```text
post A block markup   alt="alt written in this post"
post B block markup   alt="a different alt in post B"
library postmeta      "library-wide alt"
our emitted name      "library-wide alt"   (both posts)
```

So two documents with different authored descriptions publish the same `name`,
and editing one field in the Media Library rewrites `name` in every object that
ever embedded that file. For a federated object that is worse than a display
bug: copies already delivered disagree with the new one, and making them agree
would mean an `Update` for every embedding object -- the feed pollution this
project specifically decided not to cause for media metadata.

It is the same shape as `post_author` standing in for `attributedTo`: a shared
row answering a per-use question. The per-use value already exists in the post
body, which is also the source the projection already treats as authoritative
for a post's media. The standalone object keeps using the library field, because
there the file *is* the subject.

### Core upload metadata: what AS2 can express and we do not emit

Surveyed 2026-10-17 against two real attachments on the development site, an Opus
audio file and a JPEG. Two findings are standard AS2 properties whose values core
already holds, and one of them is a divergence between the two attachment paths.

**Cover art is recorded and not published.** Audio attachment 25 carries
`_thumbnail_id = 26`, a real JPEG that core extracted from the file at upload,
and its projection emits neither `icon` nor `image`. Both are Object properties
in our own snapshot. The mapping should be **`image`**, not `icon`, for a reason
internal to this plugin rather than from the spec: `_thumbnail_id` on a post
already becomes `image` in the Article projection, and the same source meta
answering to two different properties depending on post type is the kind of
divergence being removed elsewhere. (The spec permits either; `icon` prefers a
1:1 aspect and a small size, which cover art often satisfies, so this is a
consistency argument and not a correctness one.)

**`duration` is emitted by one path and not the other.** Measured on the same
attachment:

```text
core attachment profile    duration = "PT150S"
Media Library adapter      duration = NULL        <- this is the one that serves
```

Core records `length = 150` and `length_formatted = "2:30"`. The core profile
reads it; the adapter does not, so the live document for an audio file with a
known duration states none. Same family as the `name` and member-order
divergences: two paths, one of them richer, and the adapter wins wherever it is
active.

One smaller observation, offered as a question rather than a defect: core
detected `mime_type = "audio/ogg; codecs=opus"` while `mediaType` publishes
`audio/ogg`, because the projection reads `get_post_mime_type()`. The codecs
parameter is legitimate in a media type, so core knows more than we say --
whether saying it helps a peer or confuses one is untested.

#### Correctly unconnected, with the reason

Recorded so that nobody later maps these by resemblance. AS2 has no property for
any of them, and inventing one is an extension decision, not a gap:

| Core metadata | Why it stays unmapped |
| --- | --- |
| audio `artist`, `album` | `attributedTo` takes an Actor identity, not a name string. An artist credit is neither this instance's Actor nor a URI, so mapping it there would publish a false identity claim. Needs an extension or a schema.org term |
| audio `title` (ID3) | `name` already comes from `post_title`, which is what the operator sees and edits. Letting an embedded tag override it would change a published name from inside a file |
| audio `description` (ID3) | `content` already comes from `post_content`, and the Media Library's own fields are the editable copy |
| audio `bitrate`, `channels`, `sample_rate`, `dataformat`, `lossless`, `compression_ratio` | no AS2 properties; technical facts about a file rather than about an object |
| image `image_meta.camera`, `aperture`, `iso`, `focal_length`, `shutter_speed` | the same, and additionally a privacy surface: EXIF is stripped or kept by policy, not published by default |
| image `image_meta.created_timestamp` | **not** `published`. Capture time and publication time are different facts, and conflating them would backdate a federated object to when a photo was taken |
| image `image_meta.copyright`, `credit` | already answered by this project's own rights model, which publishes a license through `schema:license` |
| `original_image`, `sizes` | the rendition policy owns these, and the original is deliberately excluded from `url` |

### Open: `usedIn` may not be the lasting shape

`usedIn` is a **projection**, not an authority. Its facts live in the Media Library relation
index, and the collection is regenerated from those rows, so the property can be renamed,
reshaped, or retired without losing anything -- the same argument that let the thread tree stay
a derived view. Once Note's attachment postmeta becomes ordinary object relations under the
shared registry, the reverse direction is answerable from the relation ledger for every object
type rather than only for Media Library attachments, and a single inverse-of-`attachment`
property would then describe all of them.

Two things to settle before that, neither urgent:

- **There is no `attachedTo` in ActivityStreams.** The vocabulary defines 69 properties
  (checked 2026-10-17) and that is not one of them; `attributedTo` exists and means the
  opposite thing -- who the object is attributed to, the parent property of `actor`. So an
  inverse-of-`attachment` property is our extension either way, needing a declared
  `@context` to be more than local decoration. `usedIn` is in exactly that position today.
- **A name one letter from `attributedTo` is a hazard.** In a payload, "where this media
  appears" and "who authored this" would differ by a single character while meaning opposite
  things. If the property is replaced, the replacement should be harder to confuse, not easier.

Changing it also changes what a published URL serves (`/axismundi/v1/media/{ID}/used-in`), so
it is an owner decision rather than a cleanup.

## 12. Order

```
1. this contract (docs)
2. Media Library plural rendition API
3. Object Projections representation transition (url[])
4. shared-folder consumer
5. BlurHash, then digestMultibase / focalPoint
```
