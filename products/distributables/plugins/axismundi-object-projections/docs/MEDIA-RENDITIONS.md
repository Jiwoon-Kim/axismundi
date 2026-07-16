# FEP-1311 media renditions — locked contract

> Status: **Contract locked; not built.** Covers how a Media Library attachment advertises
> its available media versions. The Media Library rendition API, the Object Projections
> representation transition, and the shared-folder consumer are separate increments.
>
> Bilingual note (EN/KO): 첨부는 **이미 생성된** 파생본만 광고한다. 원본은 광고하지 않는다.

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
  "name": "이미지 대체 텍스트",
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
3. **The original is never advertised.** This is the same *bounded derivative, never the
   original by default* rule as REMOTE-ASSET-CACHE.md. **If no derivative exists, emit only
   the HTML Link — never fall back to the original.** (This closes the existing fallback
   where a missing intermediate size silently served the full-size file.)
4. **At most 4 versions**, largest first, deduplicated by URL **and** by dimensions.
5. **Only already-generated derivatives.** Projection never generates an image; it enumerates
   what WordPress already produced.
6. **One descriptor builder** serves all three roles: Attachment Single, an Article's
   `attachment[]`, and `preview.attachment`. They must not drift.

## 4. Ownership

**Media Library owns selection:**

```
axismundi_media_federation_renditions( int $attachment_id, array $policy = array() )
```

- enumerates **existing** derivatives only
- inherits the site's registered image sizes
- applies pixel / byte / dimension caps; max 4; dedupes
- excludes the original
- honors public / locked / sensitive policy (fail-closed)
- returns `url`, `mediaType`, `width`, `height`, `size`
- omits any entry whose file is missing or whose `filesize` cannot be read
- the existing singular `axismundi_media_feed_rendition()` remains

**Object Projections serializes only.** It must never read `wp_get_attachment_metadata()`
internals or reconstruct size names — the same boundary as the rest of the Media Library
adapter (TRANSFORMERS.md).

## 5. Metadata

- **`name` = `_wp_attachment_image_alt`** (FEP-1311: *"alternative plain text description"*;
  Mastodon renders attachment `name` as alt text). **Omit `name` when the alt text is empty —
  never fabricate it from the post title.** The current projection emits the title here, which
  is both wrong for consumers and an accessibility loss.
- **`size`** on every Link.
- **`duration`** for Video / Audio, from WordPress metadata.
- `sensitive`, the content warning, license, and `usedIn` keep their existing contracts.

## 6. Per-MIME policy

- **Image** — derivatives only. No derivative → HTML Link only (never the original).
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
- No original URL appears anywhere in `url`.
- At most 4 entries; dimension and URL dedupe hold.
- An image with **no** derivatives emits the HTML Link only — no original leak.
- `name` is the alt text; an empty alt omits `name` entirely.
- `size` is accurate.
- Attachment Single, Article `attachment[]`, and `preview.attachment` produce identical
  descriptors.
- private / locked media stay fail-closed.
- A remote object with a single-valued `url` still parses.

## 11. Deferred

`digestMultibase` and `focalPoint` land **with the shared-folder binary substrate**, where
they pay for themselves: `digestMultibase` lets a peer verify a fetched file against what was
advertised and pairs naturally with the blob substrate's `content_hash`
(REMOTE-ASSET-CACHE.md), and `focalPoint` needs the processor-version story.

## 12. Order

```
1. this contract (docs)
2. Media Library plural rendition API
3. Object Projections representation transition (url[])
4. shared-folder consumer
5. BlurHash, then digestMultibase / focalPoint
```
