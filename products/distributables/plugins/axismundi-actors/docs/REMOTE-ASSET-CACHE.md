# Remote Actor Asset Cache — locked contract (DB v9)

> Status: **Implemented in DB v9.** Scope: caching the binary avatar
> (`icon`) and header (`image`) of **remote** actors as locally-served, resized
> derivatives. This is a *remote binary cache subsystem*, not "download an image."
> Implemented **before** any image-bearing remote profile preview so the preview never
> hotlinks a remote host. Local Person/Site avatars are unaffected (they stay
> `WP_User` / core attachments, §8.1).
>
> Bilingual policy note (EN/KO): 원격 actor의 아바타/헤더만 대상. 로컬 아바타는 무관.
> preview는 이 캐시가 준비된 뒤에만 이미지를 노출한다.

The **storage identity, path derivation, GC unit, no-synchronous-download rule, and
default derivative caps** are frozen. Encoding quality remains tunable by processor
version.

---

## 1. Storage layout — content-addressed + directory sharding

```
wp-content/uploads/axismundi-cache/actors/v{processor}/
└─ {ab}/                 # first 2 hex of content_hash
   └─ {cd}/              # next 2 hex of content_hash
      └─ {content_hash}/ # full sha256 of the SOURCE BINARY
         ├─ avatar-96.{webp|jpg|png}
         ├─ avatar-192.{webp|jpg|png}
         ├─ avatar-384.{webp|jpg|png}
         ├─ header-640.{webp|jpg|png}
         └─ header-1024.{webp|jpg|png}
```

Frozen rules:

- **`{content_hash}` is the sha256 of the fetched source *bytes*, never of the URI.**
  This is what makes change-detection (same URI, new bytes) and cross-actor dedup work.
- **The `v{processor}` top segment IS `processor_version`.** There is no per-file
  processor token; when processing rules change, bump `v1 → v2` and derive into a fully
  separate tree. GC therefore operates on **`(content_hash, processor_version)` pairs**.
- No domain, username, or local DB id appears anywhere in the path. Domain moves and
  actor re-fetches never move an existing physical file.
- Identical source bytes across actors land in the same directory automatically — the
  **filesystem is the dedup layer**, so no separate blob/refcount table is needed.
- Chosen over date-partitioned (`YYYY/MM`), per-instance, and per-index-page layouts:
  those help upload journaling but hurt dedup, cache refresh, and LRU eviction, and a
  per-instance tree concentrates one large host and is fragile under domain changes.

Absolute paths are **never stored**; they are derived from
`(processor_version, content_hash, asset_role, size)`.

---

## 2. Table — single `wp_ax_actor_asset_cache` (DB v9)

```
wp_ax_actor_asset_cache
- id
- identity_id                                     -- remote actor
- asset_role            avatar | header
- source_uri
- source_uri_hash                                 -- sha256(source_uri), for lookup
- content_hash                                    -- sha256(source bytes); NULL until ready
- source_etag
- source_last_modified
- source_mime_type
- source_width
- source_height
- source_byte_size
- variants_json                                   -- which sizes exist + each w/h/bytes (NOT paths)
- processor_version                               -- = the v{n} path segment
- fetch_status          pending | ready | stale | error
- fetched_at
- expires_at
- next_refresh_at
- last_accessed_at
- failure_count
- last_error_code
- created_at
- updated_at

UNIQUE(identity_id, asset_role)
KEY(source_uri_hash)
KEY(content_hash, processor_version)
KEY(next_refresh_at, fetch_status)
KEY(last_accessed_at)
```

Invariants:

- **Single map/cache table.** No separate blob table until a real need appears
  (millions of rows, frequent cross-domain byte sharing, per-blob legal/quarantine
  state, external object-storage lifecycle, or realtime refcounting).
- `content_hash` is **`NULL` in `pending`/`error`**, **required once `ready`**.
- Not a `WP_User` attachment — remote cache never enters the Media Library (avoids
  ownership / folder / visibility confusion). Resizing uses `wp_get_image_editor()`.
- The record is per `(identity_id, asset_role)`; the physical binary is per
  `(content_hash, processor_version)`. GC reconciles them (§4).

---

## 3. Refresh contract — stale-while-revalidate

1. If the Actor JSON `icon` / `image` URI changes, **do not delete** the existing
   derivative immediately — mark the row `stale`.
2. Download the new source **asynchronously** (cron / queue), never during a page render.
3. Write to a temp file; validate MIME (file signature), byte size, dimensions, total
   pixels.
4. Generate derivatives; on success **atomic rename** into the content-addressed dir.
5. Flip the DB row to the new `content_hash`, `fetch_status = ready`.
6. The previous binary is GC'd after the grace period **only when unreferenced** (§4).

Render-time rules (frozen):

- The render path **never blocks on a fetch**. Missing/`pending` cache → default avatar
  / no header.
- On download failure, keep serving the **last good** cache (stale-while-revalidate);
  bump `failure_count` / `last_error_code`, back off via `next_refresh_at`.

---

## 4. Garbage collection & reset

- **GC unit:** a `(content_hash, processor_version)` directory whose referencing rows =
  0, after a grace period, is deleted.
- **Actor tombstone:** mark that actor's rows `stale` → GC after grace.
- **Instance block:** purge all assets for a host in bulk.
- **Quota:** LRU by `last_accessed_at`.
- **Orphans:** a binary with zero DB references past the grace period is removed.
- **Admin tools:** dry-run + purge at three scopes — one actor / one instance / whole
  cache.
- **Plugin deactivation:** preserve the cache.
- **Uninstall:** preserve by default; remove **only** if the admin explicitly chooses
  "delete data and cache."

---

## 5. Security limits (reuse the existing remote-fetch boundary)

- HTTPS only; reuse the discovery SSRF defenses (`wp_safe_remote_get`, private-network
  rejection, `wp_http_validate_url`, no redirects — or re-validate every hop; bounded
  response size).
- **MIME by real file signature**, not the response header.
- **Reject SVG** in the initial version.
- Enforce max bytes, max width/height, and max total pixels.
- Normalize animated images to a **static first frame** initially.
- **Originals are not retained** — validate + derive, then delete the source file.
- **Strip EXIF from the generated derivatives** (never mutate a remote original — we
  don't keep it).
- Never forward cookies or auth headers.

---

## 6. Derivative sizes

```
avatar: 96×96, 192×192, 384×384 caps
header: 640px, 1024px maximum widths (aspect ratio preserved)
format: normalized JPEG/PNG; WebP only when the resulting file is smaller
```

Images are never upscaled. An avatar is square-cropped only when both source
dimensions can supply that cap; otherwise its aspect ratio is preserved. If two caps
produce the same actual dimensions (a small source), only one physical derivative is
published. Headers are never cover-cropped. A focal point or a changed encoding policy
requires a `processor_version` bump (new `v{n}` tree).

---

## 7. Ordering

```
DB v8  follower/discovery policy axes (NULL ≠ false)             shipped
→ DB v9  wp_ax_actor_asset_cache (this doc)                       shipped
→ admin-only remote profile preview using the same profile template shipped
→ DB v10 keys / fetch-state / relations
→ DB v11 managed actors
```

See DATA-MODEL.md §9.5 (this table) and PHASES.md for the roadmap.
