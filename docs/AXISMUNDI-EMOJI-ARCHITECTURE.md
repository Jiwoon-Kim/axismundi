# Axismundi Emoji Architecture

> Status: **E1 and E2 closed — receiving and sending both work end to end.**
> Plugin: `axismundi-emoji` v0.1.0. E3 (block-editor picker) and E4 (reactions) remain.
>
> Two findings from implementation are recorded inline below rather than only here,
> because both changed a decision: `sensitive` is **not** on the ActivityPub wire at all
> (§3 predicted this; `:blobcat_hip:` confirmed it), and the same picture re-encoded by
> each instance's optimiser means **byte-level deduplication almost never fires** in
> practice — measured on three encodings of one Misskey logo differing only in `pHYs`,
> `tRNS`, and `IDAT` compression, with zero differing pixels.
> Review target: FEP-9098 conformance, Mastodon/Misskey interoperability, and
> WordPress core's two pre-existing emoji layers. Written before code so the
> boundaries can be reviewed while they are still cheap to move.

## 1. Purpose and scope

Custom emoji are instance-specific images addressed by a `:shortcode:` inside
otherwise plain text. They arrive declared in the `tag` array of Objects and
Actors, and they are the reason a federated display name currently reads
`:mastodon: 김지운` on our site.

```text
Existing substrate
├─ Actors                 identity, remote payload cache, binary asset cache
├─ Object Projections     local/remote view models, tag ingestion, rendering
├─ Media Library          local attachments, renditions, visibility
└─ Activities             activity ledger, relations

Emoji adds
├─ emoji registry (local + observed remote)
├─ per-authority binary cache
├─ shortcode → image decoration of visual surfaces only
├─ local emoji registration and outbound tag publication
└─ a block-editor picker
```

### In scope

Custom emoji only: ingest, cache, render, register, publish, pick.

### Three layers, one of them ours

A colon-wrapped name on a WordPress page can belong to any of three systems, and they
are routinely conflated. `s.w.org/images/core/emoji/…` and `s0.wp.com/…/twemoji/…`
look like a custom-emoji CDN and are not — they are Core's Unicode fallback.

| Layer | Looks like | Owner | This plugin |
| --- | --- | --- | --- |
| Unicode | `🇰🇷` | the OS font, with Core's `wp-emoji` swapping in Twemoji images where support is missing | never touched |
| Legacy smilies | `:cool:` `:mrgreen:` | Core `convert_smilies()`, serving `wp-includes/images/smilies` | undeclared names passed through untouched |
| Custom emoji | `:misskey:` `:blobcat:` | FEP-9098, declared per Object in `tag[]` | substituted after approval and caching |

The practical consequence is the **rendering order**:

```text
sanitize remote HTML  →  declared custom emoji  →  Core smilies
```

A declared `:cool:` becomes the custom image; an undeclared `:cool:` reaches
`convert_smilies()` and behaves exactly as the site owner configured. Nothing here
requires unifying WordPress's Unicode rendering first — that layer is complete
without us, and treating it as a prerequisite would have made this feature depend on
a problem it does not have.

### Explicitly out of scope

**Unicode emoji and their image substitutions.** Three separate layers already do
this and none of them is ours:

| Layer | What it does |
| --- | --- |
| WordPress core | ships `wp-emoji-release.min.js` (verified active, `7.0.2`) which replaces Unicode emoji with Twemoji images on non-supporting clients |
| Mastodon | serves its own Twemoji set, e.g. `mastodon.social/emoji/1f1f0-1f1f7.svg` |
| Misskey | serves its own, e.g. `misskey.io/twemoji/1f973.svg` |

Those URLs look like custom emoji and are not. A Unicode grapheme is stored and
federated as the grapheme; this plugin never touches it. The only place Unicode
appears here is as a picker tab that inserts the literal character (§9).

**Emoji reactions.** Deferred to E4 (§10), owned by Activities, not by this plugin.

## 2. Identity

> FEP-9098: *"The primary unique identifier of a custom emoji is a combination of
> its name and the domain name."*

The trap is which domain. Every sample we captured has an `icon.url` on a
different host from the declaring authority:

```text
:mastodon:   authority mastodon.social   icon host files.mastodon.social
:misskey:    authority misskey.io        icon host media.misskeyusercontent.com
```

`icon.url` is a CDN. It is a **download source, not an identity**, and it may
change without the emoji changing.

Worse, the declaring Object's authority is not always right either. Misskey
renders a reaction on a `misskey.io` note as:

```html
<img alt=":09_bird@hoto.moe:" title=":09_bird@hoto.moe:" src="https://proxy.misskeyusercontent.jp/…">
```

The note is from `misskey.io`; the emoji belongs to `hoto.moe`. Confirmed on the
wire — `misskey.io/api/notes/reactions` returns `type: ":09_bird@hoto.moe:"`
alongside plain Unicode reactions `"🐔"` and `"❤"`.

### Authority resolution order

```text
1. an explicit @domain inside the name    :bird@hoto.moe:  → hoto.moe
2. the host of the emoji's own `id`       id: https://misskey.io/emojis/misskey
3. the declaring Actor/Object authority   (bare name, no id)
```

Never `icon.url`. The column is named `emoji_authority`, never `origin_host`,
because a reviewer who sees "host" reaches for the URL and is then wrong.

### FEP-9098 compatibility recommendations

The spec's Compatibility section states what interoperable emoji look like:

```text
name ≥ 2 characters, from [a-zA-Z0-9_] only
media type is image/png, image/gif or image/webp
image size not greater than 256 KB
images should be square (some clients mis-render non-square)
shortcode sits between two characters that are not unicode alphanumerics,
  colons, or line endings
```

That last line is the spec's wording and we do **not** implement it literally: a
line ending is treated as a perfectly good boundary, as is the start or end of the
text. Mastodon's formatter agrees, and a shortcode alone on its own line is the
ordinary case in a written post. Read the rule as "not alphanumeric, not a colon";
§8 states the exact character set the tokenizer uses.

These bind **our outbound emoji** (§8) as hard rules. They cannot bind ingestion,
because reality already violates them:

- the measured `ai_acid_misskeyio.apng` is **1.92 MiB — 7.5× the 256 KB
  recommendation**;
- its declared media type is `image/apng`, which is not on the list at all,
  though the bytes are a valid `image/png` container.

So the rule is **strict on send, lenient on receive**: publish only what the
Compatibility section describes, and accept what actually arrives, subject to our
own capacity limits (§6). Rejecting a 1.92 MiB APNG because the spec recommends
256 KB would mean refusing to display a large fraction of a real instance's emoji.

### Shortcode normalization

- `shortcode` — the **original** `name` verbatim, including colons and any
  `@domain`. This is what `alt`/`title` must reproduce (§6).
- `shortcode_key` — normalized for lookup: colons stripped, `@domain` split off
  into `emoji_authority`, lowercased ASCII.
- Accept `[a-zA-Z0-9_]`, two characters minimum, per FEP-9098's compatibility
  guidance. Reject anything else rather than mangling it; an unparsable name is
  left as plain text.

Qualified names are primarily a **reaction-layer** phenomenon. Both content-layer
samples we captured are bare. The parser accepts both everywhere because doing so
costs nothing and assuming otherwise is what breaks later.

## 3. Wire contract

Captured from live instances, to be committed as fixtures:

```json
{ "id": "https://mastodon.social/emojis/1099845",
  "type": "Emoji", "name": ":mastodon:",
  "updated": "2025-01-22T12:57:33Z",
  "icon": { "type": "Image", "mediaType": "image/png",
            "url": "https://files.mastodon.social/custom_emojis/images/001/099/845/original/629dc18288be6387.png" } }

{ "id": "https://misskey.io/emojis/misskey",
  "type": "Emoji", "name": ":misskey:",
  "updated": "2023-11-05T00:11:48.702Z",
  "icon": { "type": "Image", "mediaType": "image/png",
            "url": "https://media.misskeyusercontent.com/emoji/misskey.png" },
  "_misskey_license": { "freeText": null } }
```

`type` and `name` and `icon` are REQUIRED; `id` is RECOMMENDED and may be absent;
`updated` is OPTIONAL. Unknown members (`_misskey_license`) are ignored, not
rejected.

### `updated` is the only invalidation signal

Because identity is `(emoji_authority, shortcode_key)` and that pair is stable
across re-uploads, a changed image does **not** change the key, and may not change
the URL either. `updated` is the sole standard way to learn that `:blobcat:` is a
different picture now.

Store both the raw string and a normalized timestamp. A strictly newer `updated`
for the same key replaces the cached rendition. This must ship in **E1**: added
later, every already-cached emoji has no recorded `updated` and the whole store
needs re-fetching to become trustworthy.

### Metadata: what the wire actually carries

An emoji `id` is dereferenceable, and that is a **standard AP fetch**, not a
vendor API. `GET https://misskey.io/emojis/ai_acid_misskeyio` with an AS2 `Accept`
returns:

```json
{ "id": "https://misskey.io/emojis/ai_acid_misskeyio",
  "type": "Emoji", "name": ":ai_acid_misskeyio:",
  "updated": "2023-10-29T23:47:38.256Z",
  "icon": { "type": "Image", "mediaType": "image/apng",
            "url": "https://media.misskeyusercontent.com/emoji/ai_acid_misskeyio.apng" },
  "_misskey_license": { "freeText": "©Misskey.io 2022 This emoji is exclusive to Misskey.io; usage in other platform is prohibited." } }
```

Mastodon's equivalent returns the same shape without `_misskey_license`.

This splits the desirable metadata cleanly, and the split is not the one it looks
like from a Misskey *about* page:

| Field | Source |
| --- | --- |
| `name`, `id`, `updated`, `icon.url`, `icon.mediaType` | **AP tag, or dereferencing the id** |
| license text | **AP** — `_misskey_license.freeText` (Misskey only) |
| `category`, `isSensitive`, `localOnly`, `aliases`, role gates | **`/api/emoji` only — not on the wire** |

So `isSensitive` and `localOnly` — the two flags most tempting to hang policy on —
are **not obtainable without a Misskey-specific REST call**, which is forbidden
during ingestion. Automatic policy must therefore run on what is federated: the
licence text, plus our own decisions. The registry keeps nullable columns for the
REST-only fields, filled only by the admin-initiated sync in §7, and nothing in
the ingestion path may depend on them being present.

This is also why §7 exists. NSFW cannot be judged from AP data at all, so an
emoji's suitability is not a question federation can answer — a person has to
look.

Dereferencing an emoji id is an **optional enrichment**, done by the same cron
worker that fetches bytes, never during render, and never for emoji we have not
observed in a `tag`.

Two further details from that document:

- `icon.mediaType` correctly says `image/apng` even though the CDN serves the file
  as `application/octet-stream` (§6). It is a useful hint and still not
  authoritative — Mastodon omits it and a remote may be wrong — so bytes decide.
- The `icon.url` host here is `media.misskeyusercontent.**com**` while the same
  emoji is advertised elsewhere on `media.misskeyusercontent.**jp**`. The same
  emoji, two CDN hosts. §2 again: the URL host is not identity.

### License

`_misskey_license` is a **vendor extension**, and its presence says nothing on its
own. Store the raw member and `freeText` verbatim, then classify into three
states — never infer `restricted` from "a license string exists".

```text
unknown     no license statement, or one we cannot classify
allowed     an explicit reuse grant (Public Domain, CC BY, …)
restricted  an explicit prohibition
```

A stratified 11-emoji sample of misskey.io shows why three states are required and
which one dominates:

| State | Count | Example |
| --- | --- | --- |
| `allowed` | 5 | `Public Domain`, `CC BY 4.0 Emoji by https://misskey.io/@tetekubo` |
| `unknown` | 4 | no licence member at all |
| `restricted` | 2 | `©Misskey.io 2022 … exclusive to Misskey.io; usage in other platform is prohibited.` |

`unknown` is not a rounding error — it is **twice as common as `restricted`**.
That is what rules out a binary:

- fold `unknown` into `restricted` and we withhold twice as many emoji as are
  actually restricted;
- fold it into `allowed` and we cache and re-use assets whose terms nobody stated.

Neither is a defensible default, so `unknown` stays its own state and routes to a
human (§7). Note also `fairy_tikubi`: **Public Domain and flagged NSFW.** Licence
and sensitivity are independent axes and must not be collapsed.

### Three independent axes

Licence, distribution flag, and review are **separate columns and separate
questions**. Collapsing any pair produces the wrong answer somewhere:

```text
review_status   pending | approved | rejected    -- has a human looked at it?
license_status  unknown | allowed  | restricted  -- may we re-use it?
local_only      null    | true     | false       -- may the origin's copy leave?
```

The direction that matters is **which side of the exchange each constrains**:

| | Rendering received content | Local import / picker / new outbound |
| --- | --- | --- |
| `license_status = restricted` | allowed once approved | blocked |
| `local_only = false` | allowed | per licence |
| `local_only = true` | **allowed** | **blocked** |

`localOnly` is a **send-side** constraint. It says the origin does not want its
emoji propagating to other instances — it does not say that content we have
already lawfully received should be rendered unreadable. Suppressing display
would degrade a message we were sent without protecting anything the flag was
about.

The same reasoning applies to a restrictive licence. *"Usage in other platform is
prohibited"* concerns re-use, not faithful display of a message its author chose
to send us. Showing it is not using it as ours.

So **neither `restricted` nor `local_only = true` may force `review_status` to
`rejected`.** They disable the Import and picker controls in the admin UI, with
the reason shown, and nothing more. Since we never hotlink (§6), an approved
render is always served from our own validated cache, and there is no path that
bypasses the review at all.

### Never enumerate a remote catalogue

Only emoji **observed in a `tag` array** are ingested. Instance-wide palettes are
a different, non-standard product surface and the scale forbids it:

| Instance | Custom emoji | Catalogue |
| --- | --- | --- |
| mastodon.social | 119 | 33 KB |
| misskey.io | **13,092** | **3.2 MB** |

`/api/v1/custom_emojis` and Misskey's admin endpoints are out of scope.

## 4. Ownership

| Owner | Responsibility |
| --- | --- |
| `axismundi-emoji` | registry, its own uploads, per-authority binary cache, shortcode decorator, REST search, picker, admin catalogue |
| Media Library | **nothing.** Emoji are catalogue entries, not attachments (§8) |
| Object Projections | keeps remote payloads intact; hands observed `tag[]` to the registry; calls the decorator when rendering Object HTML |
| Actors | calls the decorator for display name and bio; stores nothing emoji-specific |
| Activities | E4 reactions only |

Emoji are a cross-cutting decoration, not a domain object. They do not get an OP
transformer and are never promoted to a feed object.

## 5. Storage

```text
wp_ax_emojis
  id, scope(local|remote),
  emoji_authority,          -- resolved per §2, never the CDN host
  shortcode,                -- original name, colons and @domain preserved
  shortcode_key,            -- normalized lookup key
  source_url,               -- icon.url; download source only
  declared_id,              -- the tag's `id`, when present
  updated_raw, updated_at,  -- §3 invalidation
  media_type,               -- sniffed from bytes, not from Content-Type
  declared_media_type,      -- icon.mediaType hint; never trusted alone
  animated, frame_count,    -- sniffed after download; AP carries neither
  byte_size, width, height,
  cached_path, cached_url,  -- original bytes, verbatim
  static_path, static_url,  -- first frame, for prefers-reduced-motion
  license_raw, license_text, -- vendor member and freeText, verbatim
  license_state,            -- unknown|allowed|restricted (§3)
  review_status,            -- pending|approved|rejected (§7); governs rendering
  review_reason, reviewed_at, reviewed_by,
  local_only,               -- NULL|0|1: send-side only, never gates rendering
  category, is_sensitive,   -- NULLABLE: REST-only, never federated
  picker_visible, local_attachment_id,
  fetched_at, last_seen_at, last_accessed_at, failure_count
  UNIQUE (emoji_authority, shortcode_key)

wp_ax_emoji_authorities         -- §7 scope-level decisions, present from E1
  emoji_authority, admission_default(pending|approved|rejected),
  reviewed_at, reviewed_by
  PRIMARY KEY (emoji_authority)

wp_ax_emoji_references
  emoji_id, subject_kind(object|actor), subject_uri_hash
  PRIMARY KEY (emoji_id, subject_kind, subject_uri_hash)
```

`review_status`, `license_state`, and `local_only` are three columns because they
are three questions (§3). None of them derives from another: a `restricted`
licence still permits an approved *render*, and an `unknown` licence forbids
nothing by itself while still needing a human. Collapsing any pair would make
"nobody has looked at this yet" indistinguishable from "this was refused", or
would let a send-side flag silently blank out received content.

`wp_ax_emoji_authorities` ships in E1 even though only per-emoji review has a UI:
approval decisions are the one thing that cannot be reconstructed by re-fetching,
so the scope they attach to must be right before any are recorded.

`animated` is **derived from the fetched bytes**, not from the tag: AP `icon`
carries no such flag. Mastodon exposes `url`/`static_url` only through REST, which
we do not read. misskey.io serves `.apng`.

## 6. Rendering

### Cache-first, never hotlink

Emoji appear in names, bios, and body text — on nearly every page. Hotlinking
would disclose every viewer's IP to arbitrary third-party CDNs across the whole
site, and let a remote silently swap the image behind a URL we already published.

```text
observe tag[] → queue → cron worker downloads → render the cached rendition
                                              → on failure, plain :shortcode:
```

No HTTP during render, matching the rule the Actor asset cache already states.
Until the worker completes, the shortcode stays as text — which is exactly what
the site shows today, so there is no regression window.

### Only declared shortcodes are replaced

A `:foo:` that the object did not declare in `tag[]` is plain text. This is what
resolves the WordPress smiley collision without touching site settings.

WordPress core claims 19 shortcodes in the same `:word:` form — `:cool:` `:sad:`
`:idea:` `:evil:` `:mrgreen:` and others — and `use_smilies` defaults on. Verified
hook surfaces:

```text
the_content 20 · comment_text 20 · the_excerpt 10
the_post_thumbnail_caption 10 · widget_text_content 20
```

Actor display names and bios are **not** on that list, so no collision exists
there at all. On the five filters above, declared emoji are replaced before
`convert_smilies` runs and become `<img>`, which `convert_smilies` skips; an
undeclared `:cool:` still becomes 😎 exactly as the site owner configured.

### Text nodes only

Replacement happens **after** sanitization, on HTML text nodes only. Never inside
attributes, URLs, or `<code>`.

### Plain-text surfaces keep the shortcode

`<title>`, OpenGraph, `alt`, feeds, and admin screens keep the literal
`:shortcode:`. This is not a fallback, it is the contract — Mastodon does the same
for its own profiles:

```html
<title>:mastodon: 김지운 (@thaumiel999@mastodon.social) - Mastodon</title>
<meta content=":mastodon: 김지운 (@thaumiel999@mastodon.social)" property="og:title">
```

Decoration is a separate view concern; the stored name is never rewritten.

### Emitted markup

```html
<img class="ax-emoji" src="{cached_url}" alt=":09_bird@hoto.moe:"
     title=":09_bird@hoto.moe:" loading="lazy" decoding="async" draggable="false">
```

`alt` and `title` are the **original** shortcode, so screen readers, copy-paste,
and text extraction all degrade to the same string the plain-text surfaces carry.

### Limits

A per-object and per-actor cap on declared emoji, and a queue cap, enforced at
ingestion. A remote may declare hundreds on one object.

### Formats and animation

PNG, WebP, GIF, APNG. **Remote SVG is neither cached nor displayed** without a
dedicated sanitizer — it falls back to plain text.

**Original bytes are stored and served verbatim.** They are the default `<img>`
source. Nothing is transcoded on the way in.

This is not a preference, it is what the environment permits. Measured against a
real misskey.io emoji (`ai_acid_misskeyio.apng`, 2,011,792 bytes, `acTL` = 19
frames, infinite loop):

```text
wp_get_image_mime()          image/png        ← APNG passes as PNG
Imagick::queryFormats('APNG')  NOT SUPPORTED
wp_get_image_editor()        WP_Error NoDecodeDelegateForThisImageFormat
GD fallback                  saves 26,817 bytes, acTL absent → first frame only
```

Two consequences the Actors pipeline does not survive:

1. **Imagick cannot decode APNG at all** here, so any path that assumes an image
   editor is available for emoji will error, not degrade.
2. **GD decodes it and silently discards the animation** while keeping the PNG
   MIME. A re-encoding cache would therefore turn every animated emoji into a
   still with no error and no way to tell after the fact.

The Actors asset cache does exactly this today by design: it accepts
`jpeg|png|gif|webp|avif` and normalizes any non-`jpeg/png/webp` source to
**`image/jpeg`** (`axismundi_actors_asset_baseline_mime()`), and its extension map
knows only `webp|jpg|png`. A GIF avatar becomes a static JPEG. That behaviour is
correct for avatars and wrong for emoji.

### What E1 owns

- **Original bytes** — content-addressed, served as the default source.
- **Static rendition** — first frame, PNG or WebP, generated for
  `prefers-reduced-motion: reduce`. Generated with GD, which is the engine that
  can actually read APNG here; Imagick cannot.
- **Transparency** — emoji are composited over unknown backgrounds, so a flattened
  alpha channel is a defect, and one the source format would not have shown. When
  checking it through GD, note that its alpha is 7-bit and **inverted** relative to
  PNG or CSS: `0` is fully opaque and `127` is fully transparent.
- **`animated`, real MIME, frame count** — recorded *after* download by sniffing
  bytes (`acTL` for APNG, `NETSCAPE2.0`/multi-frame for GIF), never from the tag
  and never from the HTTP `Content-Type`: media.misskeyusercontent.jp serves
  `application/octet-stream` for `.apng`.
- **Failure policy** — if the static rendition cannot be produced, do **not**
  serve the animation unconditionally. Fall back to the plain shortcode, so a
  reduced-motion viewer is never handed an animation we could not offer an
  alternative to.
- **Capacity limits** — see below. That one emoji is 1.92 MiB.

A still emoji is a bare `<img>`. It is already an inline replaced element, so
`inline-size: 1em; block-size: 1em; vertical-align: …` in CSS is all that is needed to
sit it in a line of text:

```html
<img class="ax-emoji" src="{blob_url}" alt=":axismundi:" title=":axismundi:"
     loading="lazy" decoding="async" draggable="false">
```

`<picture>` appears **only** for an animation, and only to carry the reduced-motion
alternative — CSS cannot swap an `img`'s `src` on an accessibility preference:

```html
<picture>
  <source srcset="{static_url}" media="(prefers-reduced-motion: reduce)">
  <img class="ax-emoji" src="{blob_url}" alt=":shortcode:" title=":shortcode:"
       loading="lazy" decoding="async" draggable="false">
</picture>
```

A `<span>` with a background image would lose `alt`, and with it screen-reader output,
copy-paste, and the images-disabled fallback. Wrappers belong around this markup for
tooltips or reaction buttons, not instead of it.

Animated-WebP transcoding is a **later optimization only**, and only if a
dedicated encoder is shown to preserve frame count, delays, and loop, and to
produce a materially smaller file. WordPress's image editors do not.

### Capacity

Preserving original bytes is what makes animation work, and it is also what makes
this store expensive. The measured sample is **1.92 MiB for a single emoji** whose
static frame is 26 KB — a 75× difference. Most emoji are tens of KB; the problem
is not the average but that a 2 MiB APNG sits in the same set.

Nothing here syncs a catalogue (§3), so the realistic ceiling is what our own
federation actually references. But 100 observed emoji of that size would be
~200 MB of originals, so the limits are contractual, not advisory:

- **Per-file byte cap**, default `2 MiB`. The measured sample passes; anything
  larger falls back to the plain shortcode and is not stored.
- **Dimension, total-pixel, and frame-count caps**, applied independently. A small
  file can still be a decompression bomb, so bytes alone are not a safe gate.
- **Total store quota**, default `256 MiB`, filterable. On exceeding it, LRU
  eviction by `last_accessed_at`; evicted emoji fall back to plain text and may be
  re-fetched if referenced again.
- **Content-hash deduplication.** The same bytes under two `(authority,
  shortcode)` keys are stored once; two CDN hosts for one emoji (§3) make this
  more than theoretical.
- **Reference-counted GC.** An emoji with no rows in `wp_ax_emoji_references`
  after its grace period is removed with its renditions.

### Deliberate display fallbacks

Content-addressed storage deduplicates identical bytes, but two Misskey instances can
ship visually identical `:misskey:` images with different palette ordering and hence
different hashes. The registry keeps both identities; it never calls them the same
emoji merely because their names or NodeInfo software match.

An operator may nevertheless choose a **display fallback authority** with a positive
priority. For one unqualified, non-ambiguous declaration, presentation resolves in
this order:

1. a renderable site-local emoji with the same shortcode;
2. a renderable emoji with that shortcode from an explicitly configured fallback
   authority (lowest priority number wins);
3. the declaring authority's own renderable emoji;
4. the literal shortcode.

This is a presentation policy, never an identity rewrite: the source document and
registry row retain their declaring authority. Qualified `:name@authority:` text and
an Object that declares two authorities for the same bare name never use a fallback.
The fallback setting is independent of automatic review: trusting an authority to
download its new emoji is not, by itself, permission to display it for another
instance's same-named declaration.

### What E1 reuses from the Actors cache, and what it must not

| Reuse | Do not reuse |
| --- | --- |
| content-hash directories, atomic publish | `avatar\|header` role hardcoding |
| `processor_version` regeneration gate | resize/crop rules |
| cron-only network access | baseline-MIME normalization to JPEG |
| LRU touch, reference-counted GC | the assumption that an image editor can open the source |

## 7. Admission: observed ≠ cached

Custom emoji are not ordinary remote images. They arrive with reuse restrictions,
NSFW flags, and third-party copyright attached, and the sample above shows all
three in one catalogue. E1 therefore **does not cache on sight**.

```text
Emoji tag observed
  → registry row, metadata only            state = pending
  → rendered as :shortcode: text
  → admin review
     ├─ Approve  → download, validate, cache, render as image
     └─ Reject   → stays text; not re-queued
  updated changes → approved row returns to pending
```

`updated` reverting an approved emoji is deliberate: the key is
`(authority, shortcode_key)` and survives a re-upload, so an approved
`:blobcat:` can be replaced with a different picture under the same name. The
reverted row goes to a **`changed` queue distinct from `new`**, because an image
that silently turns back into text is a regression an admin must be told about,
not left to notice.

**Cost, stated plainly.** Mastodon and Misskey cache automatically; we will not,
so remote emoji read as text until someone acts. On a single-admin instance that
is a reasonable trade. It does not scale to many editors, so the policy is a
setting — `manual` (E1 default), `auto-allowed` (cache when licence is `allowed`),
`auto` — rather than a hardcoded behaviour.

**Approval is stored at two scopes from the start**: per emoji and per
`emoji_authority`. Only per-emoji review ships in E1, but retrofitting an
authority scope later means migrating decisions, so the columns exist on day one.

**Queue limits are mandatory.** One Misskey post can declare dozens of emoji and a
busy timeline floods the queue. Dedupe on `(emoji_authority, shortcode_key)`, cap
pending rows overall and per authority, and stop enqueuing from an authority whose
emoji have only ever been rejected.

### Admin sync and import

The catalogue endpoints excluded from *ingestion* (§3) are legitimate **here**:
§3 forbids automatic enumeration during federation, not an operator explicitly
asking to browse a named instance. This is also the only way to obtain
`isSensitive`, `localOnly`, and `category`, which are never federated — and NSFW
in particular cannot be judged from AP data alone.

1. **Sync** — fetch **metadata only** for a named instance. No binaries.
2. **List** — shortcode, authority, licence text, MIME, size, animation, NSFW and
   `localOnly` flags, and an `Open original` external link. **No image is loaded.**
   Hotlinking previews would disclose the admin's IP to the remote CDN and turn
   opening a 13,092-row list into 13,092 requests.
3. **Preview** — explicit, per row. The server downloads to a *staged* cache
   through the same E1 validation path (byte cap, pixels, frames, MIME sniffing)
   and displays the local copy. A preview is an unapproved local cache, never a
   hotlink.
4. **Import** — promotes the validated staged asset into the local registry as an
   attachment (§8).

`localOnly`, or a `restricted` licence, leaves the row visible with its reason
shown and the Approve and Import controls **disabled**.

## 8. Local registration and outbound (E2)

Local emoji are `scope = local` and are governed by local registration rules alone.
The `review_status` axis of §7 is about a third party's asset — its licence, its
distribution wishes, its suitability — and none of those questions exist for an
emoji we made. A local row is never `pending`.

The plugin ships one such asset already: `:axismundi:` at `emoji/axismundi.webp`,
200×200 WebP, 5.6 KB, transparency intact. **At E0 it is a reference asset only** —
not a registry row, so it appears in no picker and in no outbound `tag[]`. E2 is
where it becomes a registered local emoji.

One size is deliberate: FEP-9098 gives an Emoji exactly one `icon.url`, so a second
file would federate nothing. 200px covers a picker tile and scales down to the
~1.2em inline case in CSS. Local-only derivatives can be added later if a `srcset`
bottleneck is ever measured; the published `icon.url` stays single either way.

### The registry is the record, not an attachment

An emoji is not a picture that happens to be reused. It is a catalogue entry with a
shortcode, aliases, a category, a licence, a `localOnly` flag, an approval state,
and a decision about whether it may be sent — and an attachment post can hold none
of that. Managing emoji through the Media Library would scatter the fields that
matter across post meta and leave the actual questions unanswerable in the place a
user goes to ask them.

So **local emoji do not create attachment posts.** Uploads land in
`uploads/axismundi-emoji/blobs/` and the `wp_ax_emojis` row is the record. The
model is Misskey's emoji catalogue, not a media grid.

### One blob store, two kinds of owner

Local uploads share the content-addressed store with cached remote emoji rather than
getting a directory of their own. There is no per-instance layout either: where bytes
came from is already recorded in `emoji_authority`, `source_url`, and `source_kind`,
and putting it in the path too would state the same fact twice in places that can
disagree. It would also store one image twice when two identities happen to share it —
`hoto.moe/:misskey:` and `misskey.io/:misskey:` are different emoji that may be the
same picture.

What is **not** shared is the ceiling. The quota bounds how much of other people's
content we accumulate, so it counts `scope = 'remote'` rows only; a site's own uploads
are deliberate assets bounded by the flow that created them, and charging them here
would let a large local set stop remote caching — the opposite of what the operator
who uploaded them intended. Reference-counted GC is scope-blind by contrast: a claim
is a claim, so a blob two owners share survives until neither claims it.

They are also **not scoped to the site Actor.** Custom emoji belong to the
instance, and every local Actor must be able to send `:axismundi:`. The site Actor
is one sender among several; making it the owner would be an ownership boundary
that does not match how the asset is used.

### Uploading without the Media Library

Permitted, and unremarkable — provided Core does the accepting. The rule that
matters is not "must create an attachment" but "must not bypass WordPress's upload
validation", so `wp_handle_upload()` is mandatory and `move_uploaded_file()` is
not an option.

```text
Axismundi > Emoji
  → nonce + upload_files capability
  → wp_handle_upload()            Core validation, real image sniffing
  → upload_dir filtered to        uploads/axismundi-emoji/blobs/
  → dimension / frame / size checks
  → wp_ax_emojis row, scope = local
```

The accepted types need **no widening of WordPress's MIME allowlist**, which is
what usually gets a plugin into trouble. The three formats FEP-9098 recommends are
already the three WordPress permits by default:

```text
get_allowed_mime_types()   png ✓   gif ✓   webp ✓   apng ✗   svg ✗
FEP-9098 Compatibility     png     gif     webp
```

`apng` and `svg` being absent from both lists is the same answer arrived at twice:
neither is accepted for local upload in the first version. Remote APNG is still
cached and rendered (§6) — receiving is not publishing.

**But that agreement is not a boundary we control.** `get_allowed_mime_types()` is
filtered, and any other active plugin can widen it:

```text
before                                       svg denied   apng denied
after another plugin filters upload_mimes    svg allowed  apng allowed
```

An SVG-enabling plugin — a common thing to install — would silently make SVG a
valid emoji upload on a site that never decided that. So the plugin keeps its
**own hard allowlist and applies it in addition to Core's**:

```text
accept  image/png, image/gif, image/webp
reject  everything else, whatever get_allowed_mime_types() currently says
```

`wp_handle_upload()` is still the mechanism; our allowlist is the policy on top of
it. Core validates that the bytes are what they claim; we decide which claims we
are willing to entertain.

The `upload_dir` filter is likewise added **immediately before the single
`wp_handle_upload()` call and removed in a `finally`**. Left registered for the
rest of the request, it would divert any other plugin's upload in the same request
into `axismundi-emoji/blobs/` — a bug that only appears when two plugins upload in
one request, which is exactly the kind that ships.

What is genuinely given up by not creating an attachment, stated so it is not
discovered later: these files are invisible to the Media Library, to attachment
queries, and to anything that cleans up by attachment id. Deletion, GC, and
orphan-sweeping are therefore ours to implement, and the directory needs its own
`index.php` guard.

### Outbound rules

- A site-wide unique shortcode, `[a-zA-Z0-9_]{2,}`.
- **The FEP-9098 Compatibility recommendations are enforced here** (§2):
  `image/png`/`image/gif`/`image/webp`, **≤ 256 KB**, square. Being lenient about
  what we accept is interoperability; being lenient about what we publish is just
  making other implementations render us badly.
- A shortcode is recognised only when neither neighbour is in `[a-zA-Z0-9_:]`.
  This is what separates an emoji from punctuation that resembles one: `10:30:00`
  and `https://x/a:bb:c` both contain colon-delimited runs that are not emoji, and
  a match without the boundary check declares `:30:` as one.

  **A line ending is a valid boundary, and so is the start or end of the text.**
  §2 paraphrases FEP-9098 as excluding line endings; measured against both our
  tokenizer and Mastodon's, that reading is wrong — a shortcode alone on its line
  is declared by both. The paraphrase is corrected there.

  Note the `_`. It is a legal shortcode character, so it cannot also be a boundary,
  and any surface that inserts a shortcode has to use *this* set rather than a
  simplified `[A-Za-z0-9:]` — otherwise a picker declines to add a space after
  `foo_`, and the tokenizer then declines to declare what it inserted.

### Admin surface

`Axismundi > Emoji`, not `Media > Library`, with the three questions separated:

| Tab | Purpose |
| --- | --- |
| **Local** | Category grid, search, upload, shortcode and alias editing, outbound toggle |
| **Review** | The `pending` / `changed` queues (§7), staged preview, licence and flags |
| **Authorities** | Per-instance defaults, queue depth, caps, admin-initiated sync |
- Local `:shortcode:` occurrences in a Note or Article are tokenized and the
  matching `Emoji` objects are added to the outbound `tag[]` per §3.
- The emoji attachment is never promoted into the media archive or a feed.

## 9. Picker (E3)

Inserts **plain `:shortcode:` text**, never image HTML — and for Unicode, the literal
character.

**Unicode is the default tab, not an afterthought.** It needs no approval, no cache,
and no registry row, and it is the only kind a reaction can carry without an asset
(§10). Custom emoji are the addition to it:

```text
[Unicode]  Recent · Smileys & people · Nature · Food · Activities ·
           Travel · Objects · Symbols · Flags
[Custom]   Local · Imported
```

The picker does not reimplement Core's Twemoji fallback. It inserts the Unicode
character and lets the browser and `wp-emoji` render it; only custom emoji go through
this plugin's markup.

Offers **Unicode plus locally registered emoji only.** Observed remote emoji are
evidence for faithfully rendering received content, not assets to reuse in new
outbound objects — their provenance, licensing, and availability are somebody
else's. A later explicit "copy this remote emoji into our local registry" flow is
the correct way to want one.

Server-side REST search; the catalogue is never bulk-localized into the page.
Shared component and endpoint across Post and Note.

## 10. Reactions (E4, deferred)

FEP-c0e0: `EmojiReact`, with `Undo` for retraction, and *"Implementations MUST
process `Like` with `content` in the same way as `EmojiReact`."*

Owned by Activities. It cannot reuse the existing Like state, which is one row per
`actor × object`; reactions need `actor × object × reaction_key`, with
`unicode:👍` and `custom:{authority}:{shortcode_key}` as distinct keys and the
plain Like left untouched. Emoji assets come from this plugin's registry.

**A Unicode reaction is a different class of interoperable.** It travels as the
grapheme itself in `content` and needs no `tag`, so any implementation that
understands the same sequence aggregates it without an asset in common:

```json
{ "type": "EmojiReact", "content": "🧡", "object": "https://example.net/notes/123" }
```

A custom reaction cannot do that: `content: ":misskey:"` alone is meaningless
without an accompanying `Emoji` tag naming its authority. So the identity key differs
by kind, and only one kind touches this plugin at all:

| Reaction | Identity | Registry involved |
| --- | --- | --- |
| Unicode | the exact grapheme sequence | no — no cache, no review queue |
| Custom | `(emoji_authority, shortcode_key)` | yes |
| Plain Like | the `Like` activity | no |

Even Unicode is not universal agreement, only asset-free agreement: Akkoma treats
`Like` and `EmojiReact` as separate and permits several reactions per actor, Misskey
collapses them into one choice, and an implementation that knows only `Like` ignores
the activity. Never identify or aggregate a reaction by image URL.

## 11. Phases

Fixtures live in `products/distributables/plugins/axismundi-emoji/tests/fixtures/`
with their own README. Two rules hold there:

- The 1.92 MiB Misskey APNG is **not committed** — it is large and its own licence
  forbids off-instance use. A generated 204-byte 2-frame APNG stands in for it, and
  is verified to reproduce the same `image/png` typing, the same Imagick decode
  failure, and the same GD frame-flattening.
- The optional integration checks read that original from
  `AXISMUNDI_EMOJI_REFERENCE_APNG`; absent, they skip. No code may depend on the
  path.

```text
E0  this document + plugin scaffold + captured fixtures + harness   ← done
E1  a received remote emoji renders, end to end:
    E1a  observe tag[] → registry upsert, references, `updated` invalidation
    E1b  Emojis > Review — pending/changed lists, metadata, Open source,
         Approve/Reject. No Local tab yet.
    E1c  authority-side enrichment worker — queued hearsay Emoji ids only;
         exact authority/id/shortcode match promotes canonical metadata
    E1d  download worker — approved rows only; original bytes + static rendition
    E1e  HTML renderer — substitute only when approved AND cached; else shortcode  ← done
E2  local upload, registry editing, outbound tag publication, picker groundwork  ← done
E3  block-editor picker (Unicode + local only)
E4  emoji reactions (Activities)
```

The order inside E1 is forced by the default. Everything observed starts
`pending`, so building the worker or the renderer first would produce a worker
with nothing to process and a renderer that shows plain text forever. **The review
panel has to exist before either becomes observable.** It is the smallest thing
that makes the rest testable, not a UI nicety deferred to the end.

### E1 verification

- Declared `:cool:` renders as the custom image; undeclared `:cool:` stays 😎.
- Shortcodes inside URLs, attributes, and `<code>` are untouched.
- `<title>` and `og:title` keep the literal shortcode.
- A newer `updated` for the same `(authority, shortcode_key)` replaces the cached
  rendition; an older or equal one does not.
- Qualified `:name@domain:` resolves to `domain`, not to the declaring object's
  host and not to the CDN host.
- An unreachable icon leaves plain text and does not retry per request.
- No HTTP request is issued during rendering.
- A 19-frame APNG survives the cache with its `acTL` chunk intact and animates in
  the browser; the byte count matches the source.
- The same emoji yields a static first-frame rendition, and a
  `prefers-reduced-motion: reduce` client receives it.
- An emoji whose static rendition cannot be generated renders as plain text, not
  as an unguarded animation.
- A source served as `application/octet-stream` is still classified correctly.
- A file over the size cap is rejected without being stored.
- A newly observed emoji is `pending` and renders as text; nothing is downloaded
  until it is approved.
- A qualified or otherwise hearsay emoji must be verified by its authority before it
  becomes an ordinary pending row. The worker follows no redirects and requires the
  queued URI, returned `id`, authority, and shortcode identity to agree.
- Approving permits the later download worker to validate and cache the bytes; only
  then does the surface switch to the image.
- A `restricted` licence may still be approved for faithful received-message
  rendering; it is blocked from local import, picker use, and outbound publication.
- A changed `updated` on an approved emoji returns it to the `changed` queue and
  its surfaces to text.
- A rejected emoji is not re-enqueued when observed again.
