# Remote object projections

> Status: **Phase 4b metadata-only discovery and full-payload inspection plus DB v4 leases and quote relations implemented**. Binary media caching is
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

## 2. Tables (schema v4)

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

## 6. Object-relation projection

Quote discovery needs an indexed relationship between the independent quoting Object and
the quoted Object. Scanning bounded JSON payloads for every count is not a query contract,
so schema v4 adds a rebuildable `wp_ax_object_relations` projection. Its first
and only accepted `relation_type` is `quote`; reply and other relation semantics remain
deferred until their owning product contracts exist.

```text
id                       bigint unsigned PK
relation_type            quote
source_object_uri        text
source_object_uri_hash   char(64)
target_object_uri        text
target_object_uri_hash   char(64)
source_actor_uri         text nullable
source_actor_uri_hash    char(64) nullable
evidence_type            fep044f | misskey | legacy
consent_status           approved | legacy_unverified | rejected | revoked | ambiguous
authorization_uri        text nullable
authorization_uri_hash   char(64) nullable, indexed
created_at / updated_at  datetime
UNIQUE(relation_type, source_object_uri_hash, target_object_uri_hash)
```

Hashes are accelerators only and every read/write verifies both full Object URIs. This table
is a disposable index: remote rows can be rebuilt from `payload_json`, and future local Quote
Objects are projected from their canonical local source. It is not an Activity ledger or the
authority for QuoteAuthorization lifecycle; Activities owns that state.

Normalization accepts the canonical FEP-044f `quote` relation and the compatibility forms
`_misskey_quote`, `quoteUrl`, `quoteUri`, and the applicable FEP-e232 Link form. Multiple
aliases that resolve to the same URI are one relation. Conflicting aliases are retained as
`ambiguous`, must not silently choose a target, and are excluded from public counts.

The public quote count is the number of distinct, publicly visible source Object URIs that
quote one target Object. It is not a distinct-Actor count: one Actor may author several
independent quote posts. Deleted/Tombstone, private, followers-only, direct, blocked, and
ambiguous source Objects are excluded. Approved, legacy-unverified, rejected, and revoked
relations remain countable while their public source Object exists because consent status
does not rewrite the observed quote fact. No compatibility alias is treated as authorization
evidence, and the projection never fabricates `quoteAuthorization`.

`quoteAuthorization` declared by a remote Object is retained as an unverified mapping but
never upgrades consent. Only `axismundi_op_verify_quote_consent()` may mark the exact
source/target/authorization triple approved, rejected, or revoked. A later payload refresh
preserves that verified decision. A signature-verified inbound Delete can therefore revoke
the mapped relation without guessing from URI shape or fetching during Inbox processing.

Forwarding that remote Delete to a locally owned quote audience remains fail-closed. The
current delivery adapter signs as the local Axismundi Actor and enforces that the Activity
actor is that signer; re-signing a remote actor's Delete as local would falsify its actor and
break the verifier's actor/key-host invariant. The verified mapping and revocation event are
available, but no outbound forwarding Activity is fabricated until transport has an explicit
forwarding contract that preserves the original actor/signature semantics.
