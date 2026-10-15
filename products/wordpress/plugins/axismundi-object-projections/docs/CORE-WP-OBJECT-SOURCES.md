# Core WordPress Objects: source facts before domain policy

Status: decided 2026-10-14. **The boundary is decided; the shared Core adapter is
not implemented.**

`wp_posts` is the editable local source for more than Article. A core Post, a
core Attachment, and a first-party CPT all begin as a `WP_Post`. Object
Projections must read the WordPress source facts once before a product adds
ActivityStreams semantics or policy.

The body adapter receives `post_content` as authored source rather than the
output of `the_content`: it must support both Classic-editor and Block-editor
source forms before emitting an allowed static representation. A future
Markdown editor follows the same source-to-representation contract; it does
not create a competing Object source path.

This decision is deliberately narrower than `LOCAL-OBJECTS.md`:

- `LOCAL-OBJECTS.md` decides publishing identity and the authored body.
- This document decides who reads Core WordPress records, MIME facts, and Core
  post meta before those higher-level decisions are applied.

## 1. One Core source adapter

The shape is:

```text
WP_Post + Core post meta + Core attachment metadata APIs
    -> normalized local-object source facts
    -> base ActivityStreams Object projection
    -> product/domain decorators and policy
```

There must not be one source reader for `post` and a separately registered,
optional source reader for `attachment`. `attachment` is a Core `post_type`,
not a Media Library product record.

The base adapter owns only facts that WordPress Core owns:

| source | facts made available to a projection |
|---|---|
| `WP_Post` | ID, post type, MIME type, title, description, caption/excerpt, GMT dates, source status, parent, slug |
| attachment `postmeta` | `_wp_attached_file`, `_wp_attachment_metadata`, `_wp_attachment_image_alt` |
| post `postmeta` | `_thumbnail_id` through Core APIs |
| Core APIs | attachment URL, attachment metadata, generated image sizes, featured-image resolution |

Raw relative file paths and serialized metadata are not protocol output. The
adapter reads them through WordPress APIs to produce facts such as a media URL,
MIME type, width, height, and available generated rendition descriptors.

## 2. Core object classification

Core establishes the mechanical classification before a domain can claim extra
meaning:

```text
post_type=post                       -> Article
post_type=attachment + image/*       -> Image
post_type=attachment + video/*       -> Video
post_type=attachment + audio/*       -> Audio
post_type=attachment + other MIME    -> Document
```

The Core adapter does not guess the type of an arbitrary CPT. Its owner must
explicitly claim it, for example `ax_note -> Note`, a forum post -> Article
with Group context, or a calendar record -> Event. `page` remains undecided:
it may be `Page` or `Document`, but must not acquire one by fallback.

## 3. Identity, representation, and the disabled attachment page

An ActivityStreams Object identity is not its human-facing page:

```text
core Post id        /?p={ID}
core Attachment id  /?attachment_id={ID}
human URL           permalink or attachment page, when a usable HTML page exists
media URL            file or generated rendition Link
```

An attachment-page redirect, disablement, or theme decision therefore does not
make the Core attachment cease to be a projectable Image, Video, Audio, or
Document. A human HTML Link is optional representation data; it is not an
identity or source-availability gate.

The existing `id` callbacks remain compatibility seams. A product that owns
its routing may claim another stable URI, but it must not replace Core source
facts or make its presence a condition for recognizing an Attachment.

## 4. Media representations are not Article attachments

For a standalone Image, Video, Audio, or Document, the object's `url` may be a
Link array of its representations. Generated image sizes can contribute Links
with `href`, `mediaType`, `width`, and `height`; an HTML page Link, if any,
belongs in the same representation set.

For an Article, `attachment` names an Object attached to that Article. It is
not the place to repeat every rendition of the attached Image. The embedded
descriptor may select one usable rendition, while dereferencing the standalone
Image exposes its representation ladder.

`_thumbnail_id` is the Core source for an Article's `image`. It and an
in-content attachment must resolve through the same Attachment source adapter,
so their identity, MIME family, dimensions, and alt text cannot drift.

## 5. What is not Core source truth

These facts are not inferred from a `WP_Post`:

| concern | owner |
|---|---|
| `attributedTo` | Proposed per-post Actor identity selection; Activities records the later Create/Update actor and delivery event |
| audience and delivery eligibility | Activities/policy owner |
| whether a core file may be federated or listed | visibility policy owner |
| which rendition is safe to expose | media policy owner |
| license, sensitivity, content warning | media/domain policy owner |
| folders, usage relations, ordering | Media Library or the owning product |
| `inReplyTo`, thread root/depth, replies count | URI thread graph, not `post_parent` or `comment_count` |

In particular, a readable Core attachment and a publicly listable/federatable
attachment are different facts. The shared adapter supplies the first; a policy
owner must decide the second.

## 6. Required refactor direction

Today `core-post-article` reads Core Posts while the Media Library integration
conditionally registers the only standalone Attachment transformer. This
creates an invalid split:

```text
Media Library absent  -> an Article can describe embedded media, but the same
                         Attachment has no standalone Object projection.
Media Library present -> Attachment identity, type, and visibility appear.
```

The replacement direction is:

1. Object Projections registers a Core Attachment transformer unconditionally.
2. That transformer reads the Core source facts described above and owns stable
   identity plus Image/Video/Audio/Document classification.
3. Media Library becomes an enricher and policy provider. It may replace
   rendition selection, add license/sensitivity/folder/usage data, and answer
   federation visibility; it does not decide whether `post_type=attachment`
   exists as an Object source.
4. Core Post Article, standalone Attachment, featured image, and embedded
   media share the same Attachment source resolver.

No schema migration follows from this decision by itself. It repairs source
interpretation first; only later query contracts decide whether an Object index
needs additional normalized columns.

## 7. Module boundary and names

The shared reader is a source adapter, not a generic ActivityStreams Object
fallback. It must therefore have a source-oriented name:

```text
includes/core-post-source.php
    WP_Post + Core postmeta + Core APIs -> normalized CorePostSource facts

includes/post-article.php
    CorePostSource -> Article-specific body, preview, and FEP representation

includes/post-attachment.php
    CoreAttachmentFacts -> Image / Video / Audio / Document representation

includes/integrations/media-library.php
    Core attachment facts -> policy and enrichment
```

`post-object.php` would imply that every unclaimed `WP_Post` has already become
a generic AS2 Object. That is not this contract: an arbitrary CPT has no
fallback type. Its owning plugin must explicitly claim it as Note, Event,
Place, Article-with-context, or another supported type. `page` remains
unclaimed until Page-versus-Document is decided.

The adapter's normalized facts include source identity candidates, post type,
MIME type, title/body/excerpt source, GMT timestamps, parent and thumbnail
references, and attachment metadata/rendition candidates. It deliberately
does not include federation actor, addressing, public eligibility, or a final
choice of what bytes may be exposed.

## 8. Refactor sequence

The refactor must preserve current public output until each replacement path is
measured.

1. Add `core-post-source.php` with read-only source-fact helpers and tests for
   Core Posts and Attachments. No transformer registration changes yet.
2. Make `post-article.php` consume those helpers while preserving its existing
   public Article JSON byte-for-byte where its current body policy permits.
3. Extract `post-attachment.php` from the Media Library adapter. It owns the
   stable attachment URI and MIME-family classification, but delegates
   visibility and rendition selection through policy hooks.
4. Turn the Media Library adapter from a conditional transformer registrar into
   an attachment policy/enrichment provider. Its folder, visibility, rights,
   sensitivity, and rendition rules remain its own.
5. Route featured media and embedded post media through the same attachment
   resolver; remove the duplicate Core descriptor in `post-article.php` only
   after identity and descriptor tests agree.
6. Rebuild and test local Object listing projections after a public visibility
   policy is chosen. This decision does not choose that policy.

Each phase must test Media Library present and absent, attachment pages enabled
and disabled, every MIME family, generated image metadata, and a private or
gated attachment. A source resolver succeeding must not itself prove that the
anonymous federation route is allowed to serve the Object.
