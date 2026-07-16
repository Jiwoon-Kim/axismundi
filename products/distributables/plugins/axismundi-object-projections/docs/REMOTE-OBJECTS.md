# Remote object projections

> Status: **Phase 4b metadata-only discovery and full-payload inspection plus DB v3 leases implemented**. Binary media caching is
> deliberately deferred to the shared-blob/shadow-attachment design.

## 1. Purpose and ownership

`wp_ax_remote_objects` stores a rebuildable observation of a remote ActivityStreams
object. It is not an Activity ledger, inbox, delivery queue, local post, or authority
over the remote object. The canonical remote URI is always the identity; the local row
id is private implementation detail and must never appear in JSON-LD or public URLs.

Object Projections owns this cache because it is the inverse of a local transformer:

```
local WordPress source -> normalized AS projection
remote AS document     -> normalized local observation
```

Activities may later reference the canonical object URI. Actors may best-effort resolve
an `attributedTo` URI, but neither plugin is required for storing the object snapshot.
Resolution is a deferred, deduplicated WP-Cron event for the primary Actor only; object
fetch never waits on Actor discovery and never fans out through mentions or audience members.

## 2. Tables (schema v3)

```text
wp_ax_remote_objects
  id                         bigint unsigned PK
  object_uri                 text
  object_uri_hash            char(64) UNIQUE, sha256(canonical URI)
  object_type                varchar(64)
  object_status              active | tombstone
  attributed_to_uri          text nullable
  attributed_to_uri_hash     char(64) nullable, indexed
  in_reply_to_uri            text nullable
  in_reply_to_uri_hash       char(64) nullable, indexed
  human_url                  text nullable
  name                       text nullable
  summary                    longtext nullable
  content                    longtext nullable
  content_language           varchar(35) nullable
  media_type                 varchar(127) nullable
  is_sensitive               tinyint nullable (NULL = not declared)
  published_at               datetime nullable
  remote_updated_at          datetime nullable
  payload_json               longtext
  payload_hash               char(64)
  etag / last_modified       varchar(191) nullable
  fetched_at / last_success_at / next_refresh_at datetime nullable
  expires_at / last_accessed_at datetime nullable
  failure_count              int unsigned
  last_error_code            varchar(64) nullable
  created_at / updated_at    datetime
```

Long URIs are indexed only through SHA-256 hashes. Hash lookup is always followed by an
exact URI comparison. The table is InnoDB and schema version is recorded only after the
table and required unique/index keys are verified.

`wp_ax_object_leases` stores independent retention reasons without copying object payloads:

```text
object_uri + object_uri_hash
lease_type       transient | interaction | collection | shared_shadow
lease_ref + lease_ref_hash
expires_at       nullable; NULL means explicit release is required
UNIQUE(object_uri_hash, lease_type, lease_ref_hash)
```

Hash lookup always verifies both full URI/reference strings. An active lease prevents expiry
maintenance from deleting the observation; releasing the final lease makes an already expired
observation eligible again.

## 3. Repository contract

- `axismundi_op_remote_object_store($payload, $fetch)` validates then atomically upserts
  by canonical `id`. Invalid input never deletes or overwrites the last good snapshot.
- The default type allowlist is Object, Article, Audio, Document, Event, Image, Note,
  Page, Place, Profile, Question, Relationship, Tombstone, and Video. Actor types belong
  to Axismundi Actors; Activity types belong to Axismundi Activities; Collection types
  use collection projections. A filter may add a genuine object extension type.
- `axismundi_op_remote_object_get($uri)` reads by hash plus exact URI.
- `axismundi_op_remote_object_delete($uri)` deletes only the cache row, never a remote
  resource, WordPress post, Actor, or binary.
- `Tombstone` is retained as `object_status=tombstone`; it is not treated as absence.
- The original bounded JSON is preserved in `payload_json`; normalized display fields
  are conveniences, not a lossless replacement.
- `sensitive` preserves three states: undeclared (`NULL`), explicit false, explicit true.
- Scalar `attributedTo`, `inReplyTo`, and `url` are normalized when present. Arrays and
  richer objects stay losslessly in `payload_json` until a justified relation table exists.
- Metadata expires 30 days after last successful fetch or administrator inspection by
  default (filterable to 1–365 days). Daily maintenance deletes an unleased observation,
  including sensitive metadata; it never contacts or mutates the remote resource.

## 4. Validation and security

- Canonical ids must be absolute HTTP(S) URIs with no embedded credentials. Storage
  validation does not fetch the URI and therefore is not an SSRF boundary.
- Payloads are capped at 1 MiB after JSON encoding. Network code must impose its own
  byte cap before decode as well.
- `name` is plain text; normalized `summary` and `content` use `wp_kses_post`. Raw JSON
  is never printed directly.
- Fetching is administrator-initiated or background work only: HTTPS, public-network
  validation, no cookies/auth forwarding, 1 MiB response cap, strict content type, and
  redirects disabled. ETag/Last-Modified drive conditional refresh; failures use capped
  exponential backoff while preserving the last good payload.
- HTTP 401/403 is reported as signed-fetch-required. Object Projections does not fabricate
  signatures; a future Federation fetcher must explicitly provide that capability.
- Rendering never performs a synchronous remote fetch. Cache miss uses a placeholder.
- Phase 4a creates no public mirror route. The administrator inspector is admin-only and
  `noindex`; remote media is not hotlinked. It displays Tags/Mentions, audience properties,
  attachment descriptors, unknown extension properties, and the full payload only as
  escaped text. If an Actor URI already exists in Axismundi Actors, references use its
  internal administrator page; otherwise they remain external canonical links.

## 5. Cache levels and binary boundary

The cache-level vocabulary is reserved as follows:

```text
metadata-only  shipped default; URI/text/author/license/sensitive/remote links
preview        future shared-blob thumbnails for admin/board use
display        future bounded front-end derivative; original remains remote
original       future explicit policy only; never the default
```

Phase 4b is strictly `metadata-only`. Content HTML may remain in the payload, but the
administrator preview removes `img`, `video`, `audio`, `source`, iframe, and embed markup.
No remote media URL is loaded by the browser. A future hotlink option, if added, is an
explicit site policy and defaults off; sensitive media must not silently opt into it.

This table stores object metadata, not media bytes. Remote avatar/header and future
attachment binaries use the separate remote-blob substrate. A later Media Library shadow
attachment points to the canonical remote object/blob; it does not convert this cache row
into a second local identity.
