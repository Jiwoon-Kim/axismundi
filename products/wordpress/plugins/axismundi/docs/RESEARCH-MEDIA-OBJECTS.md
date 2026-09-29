# Media Objects Research

Status: open research. This note is not an implementation decision.

This document records questions raised while defining the future Frontend
Composer and M3 media picker. It is intentionally separate from
`CAPSTONE.md`, which only records established application boundaries.

## Problem

The Frontend application consumes JSON representations rather than rendered
block-theme HTML. Local WordPress attachments may therefore enter the app as
ActivityStreams media objects. The correct local projection policy is not yet
settled.

The broad ownership boundary is already decided:

```text
WordPress attachment / Media Library  storage, metadata, edits, image sizes
ActivityStreams media object           application and federation projection
Frontend media picker                  M3 selection and composition surface
```

What remains open is the shape and type policy of the projection.

## Interoperability observation

A Mastodon `Note` observed on 2026-07-26 attached an MP3 as:

```json
{
  "type": "Document",
  "mediaType": "audio/mpeg",
  "url": "https://files.mastodon.social/.../original/...mp3",
  "width": 360,
  "height": 360,
  "duration": "PT197.479979S",
  "icon": {
    "type": "Image",
    "mediaType": "image/png",
    "url": "https://files.mastodon.social/.../thumbnail/...png"
  }
}
```

This demonstrates that an attachment's ActivityStreams `type` is not a
reliable substitute for its rendering medium. `Document` plus
`audio/mpeg` still needs an audio presentation. Other servers may instead use
`Audio`, `Image`, or `Video` for otherwise comparable resources.

ActivityStreams semantic type and application rendering kind are not a subtype
hierarchy and must not be treated as one:

```text
Document + audio/mpeg  -> audio renderer
Document + image/jpeg  -> image renderer
Audio + audio/mpeg     -> audio renderer
Image + image/webp     -> image renderer
```

The Mastodon audio dimensions (`width: 360`, `height: 360`) are also not proof
of intrinsic audio dimensions. They may instead describe an associated visual
presentation or thumbnail. Required and optional fields need corpus validation.

## Working normalization hypothesis

For remote objects, preserve the received ActivityStreams type and derive a
separate UI rendering kind from the complete representation:

```text
remote type + mediaType + available capabilities
                    |
                    v
             rendering kind
             image | audio | video | document | unknown
```

For example, `Document` with `audio/mpeg` would render as audio without
rewriting its federated source type. This is a hypothesis to validate against
Mastodon, Misskey, Lemmy, GoToSocial, and other supported peers.

A possible normalized shape preserves both concepts explicitly:

```yaml
MediaProjection:
  sourceType: Document
  mediaType: audio/mpeg
  renderingKind: audio
  url: https://example.invalid/media.mp3
  dimensions: null
  duration: PT197.479979S
  preview: null
  capabilities: []
```

This is illustrative, not a proposed API contract.

## Wire and application representations

JSON-first application design does not require the Frontend to consume an
ActivityPub wire document unchanged. A local attachment can share
normalization code while retaining two representations with different
contracts:

```text
wp_attachment
     |
     |-- ActivityPub projection       federation and interoperability contract
     `-- application media projection React and Media Picker contract
```

The application representation may need local image sizes, permission state,
private-attachment handling, upload state, or editing information that has no
place in an outbound federation document. Whether either response is the
Frontend's canonical input remains open.

## Open decisions

1. What ActivityStreams type should a local `wp_attachment` publish as?
   Candidates include specific `Image`/`Audio`/`Video` types and `Document`
   paired with an authoritative `mediaType`.
2. Should the Frontend consume a local attachment's canonical ActivityPub JSON
   representation directly, or a dedicated media projection response derived
   from the same attachment authority?
3. Which fields are stable enough to require for each rendering kind:
   `url`, `mediaType`, `name`, dimensions, duration, poster/icon, blurhash,
   focal point, accessibility text, and alternate sizes?
4. How should local and remote media identity, caching, and permission checks
   interact with the Composer picker?
5. Which attachment forms are interoperable in outbound ActivityPub delivery?
6. Is a media item in `Note.attachment` the same identified ActivityStreams
   object as an attachment's standalone representation, an embedded projection
   of it, or peer-dependent serialization? How do identity and `id` behave in
   each case?

## Research sequence

```text
real-world federation corpus
        |
        v
type/mediaType combinations
        |
        v
identity and embedding behavior
        |
        v
required/optional field matrix
        |
        v
normalization contract
        |
        v
outbound local policy
```

Receiving normalization and outbound local type policy are independent
decisions. A Mastodon example is evidence for the corpus, not a declaration
that `Document` is the required local outbound type.

## Non-decisions

- Do not implement a local attachment-to-ActivityStreams type mapping yet.
- Do not assume `Note.attachment` always contains `Image`, `Audio`, or `Video`.
- Do not make the Frontend Media Picker depend on legacy `wp.media` UI.
- Do not rewrite a remote attachment's source type merely to select a local
  renderer.

The current Composer boundary remains valid regardless of the eventual policy:
it may select WordPress-backed media through a future M3 media picker, while
the Media Library retains storage authority.
