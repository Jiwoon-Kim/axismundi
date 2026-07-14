# Routing & URI contract

> Status: **Standalone object negotiation implemented in 0.0.2.** Collection routing and
> the official ActivityPub adapter remain later phases.

## 1. Principle — negotiate on the existing WordPress URL

An object that already has a single canonical WordPress URL (a post, an attachment) must
**not** get a second `/objects/...` REST resource — that would fork its id, permissions,
and cache contract. Instead the *existing* URL answers with JSON-LD under
`Accept: application/activity+json` (or the ActivityStreams-profiled `application/ld+json`),
and renders the normal theme HTML otherwise. This mirrors the official ActivityPub plugin,
which uses `/?p={ID}` as a post's stable `id` and the pretty permalink as its `url`
(`activitypub/includes/transformer/class-post.php` `get_id()` / `get_url()`).

Collections (author/media archives, folders, shared folders) are **not** single canonical
objects, so they do get their own stable collection endpoints.

## 2. URI table

| Target        | ActivityStreams `id` (stable)          | HTML `url` (human)          |
|---------------|----------------------------------------|-----------------------------|
| Post          | `/?p=123`                              | pretty permalink            |
| Attachment    | `/?attachment_id=7822`                 | `/media/image/7822/`        |
| Media home    | `/?ax_media_archive=landing`           | media landing archive       |
| Media author  | ID-based plain archive endpoint        | author media archive        |
| Folder        | owner-ID + term-ID plain endpoint      | folder archive URL          |
| Shared folder | identity **UUID**-based Collection URI | shared-folder page          |
| Actor         | `/actors/{uuid}` (Actors plugin)       | `/@handle/`                 |

Rules:

- **`id` is plain and stable** (query-arg form), independent of permalink structure, so a
  slug or permalink change never splits federated identity.
- **`url` is the pretty/human permalink.** A pretty URL under an AS `Accept` returns the
  same object, but the JSON `id` stays the plain stable URI.
- **Shared folders never key their `id` on a path/slug** — they use the identity UUID
  assigned when the shared folder is registered as an identity (Media Library Phase 5b).
  The exact URI form is fixed together with that identity-registration contract, not here.
- This supersedes the earlier reserved `?ax_media_object=` idea in the Media Library
  routing notes — attachments negotiate on their own `?attachment_id=` URL instead.

## 3. Negotiation rules

- Only a precise `Accept` triggers JSON-LD: `application/activity+json`, or
  `application/ld+json` **with** the ActivityStreams profile. A bare `application/json`
  does **not** hijack the URL.
- Emit `Vary: Accept` and a `Link: rel="alternate"` pointing at the counterpart
  representation.
- A browser (HTML `Accept`) always gets the existing theme render.
- `GET` and `HEAD` are supported; other methods pass through untouched. JSON responses
  use `application/activity+json`, CORS GET/HEAD, and `nosniff`.
- An unsupported source passes through to WordPress. A supported but hidden source is
  indistinguishable from missing (404); a transformer failure is 500.
- The standalone router runs before core canonical redirects so `/?p={ID}` remains
  fetchable under AS negotiation even when pretty permalinks are enabled.

## 4. Feed & REST

- **Atom / MRSS feeds stay** as subscription / media syndication — they are **not** the
  canonical ActivityStreams representation of an object.
- **REST is optional** (admin inspector, editor preview, tests) and its URL is **never**
  used as an object `id`.
- An Actor's activity feed is later an **outbox `OrderedCollection`** owned by Axismundi
  Activities — not an Atom feed.
