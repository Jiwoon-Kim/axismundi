# Hashtag contract

## Purpose

`ax_hashtag` is Axismundi's shared, non-hierarchical social-discovery
vocabulary. It is deliberately separate from WordPress `post_tag`.

`post_tag` remains a WordPress compatibility and editorial taxonomy. It is not
automatically federated and Axismundi post types do not need to attach it.

Object Projections owns the vocabulary, ActivityStreams conversion, and
cross-source query contract. Domain plugins decide whether their object types
support hashtags and whether those hashtags are public or federated.

## Identity and storage

- The identity of a hashtag is its normalized name, not an ActivityPub `href`.
- Normalization removes one leading `#`, trims whitespace, applies Unicode NFC
  when available, and case-folds only for comparison. The selected display name
  is retained separately.
- No semantic rewriting is permitted. `busan` and `부산`, or `부산여행` and
  `부산_여행`, are separate terms unless a future relation product connects them.
- Local WordPress objects use the normal taxonomy relationship table.
- Remote cached Objects use a dedicated rebuildable remote-object-to-term index.
  The remote tag's original `name` and `href` remain observation evidence; a
  remote `href` never becomes the local hashtag's canonical URL.

The initial shared object types are `post` and `attachment`. Other products opt
in through `axismundi_op_hashtag_object_types`; Axismundi Note adds `ax_note`.

## Visibility is not assignment

One relationship does not grant every representation right:

```text
hashtag assignment != local search != HTML exposure != JSON-LD tag != outbox item
```

Attachments may use `ax_hashtag` for local media discovery, but are not made
federated or independent public feed items merely by receiving a hashtag.
Automatic propagation between an attachment and a containing post is forbidden.

## ActivityStreams conversion

For a federated local object, each assigned term is emitted as:

```json
{
  "type": "Hashtag",
  "name": "#busan",
  "href": "https://example.test/hashtag/busan/"
}
```

Remote Object ingestion accepts `tag` members whose type is `Hashtag`,
materializes the shared term, and records the object relation. Invalid, blank,
or overlong names are ignored without discarding the remote Object snapshot.

## Archive direction

`/hashtag/{term}/` is an Object archive, not an Activity timeline. It merges
public local supported Objects and public remote cache Objects by effective
object time, de-duplicates by canonical Object URI, and renders the common
Object View Model. The `type` filter narrows the result to posts, Notes, media,
or remote Objects. The default mixed result is Post/Note plus remote Objects;
media remains available through its explicit filter so attachment volume cannot
overwhelm the primary discovery feed.

## Archive template integration

The archive participates in the normal WordPress template hierarchy
(`taxonomy-ax_hashtag → taxonomy → archive → index`). It is deliberately not a
`template_redirect` render: that bypassed the hierarchy, and because
`get_header()`/`get_footer()` are classic-theme APIs it also dropped every
block-theme template part, leaving the page with no site header, navigation, or
footer.

```text
taxonomy main query             = term context and hierarchy only
axismundi/hashtag-archive block = mixed local/remote results, type filters, paging
```

A cached remote observation is not a `WP_Post`, so the mixed result set cannot be
driven by a Core Query Loop, and the block owns its own selection and paging. The
main query is left intact rather than short-circuited, so a remote-only term
still resolves as a normal taxonomy archive.

Object Projections registers `axismundi-object-projections//taxonomy-ax_hashtag`
as a bundled default via `register_block_template()`. Precedence is enforced by
audit:

```text
Site Editor customization (wp_template, source: custom)  overrides
OP bundled default (source: plugin)
```

A theme file of the same slug likewise overrides the bundled default. Note the
usual consequence: once the template is customized in the Site Editor, that
stored copy shadows the bundled default, and later plugin updates to the default
stay invisible until the customization is reset.

Cursor pagination and per-type result counts are not part of this increment.

## Explicit non-goals for the first increment

- Do not create a new plugin or overload `post_tag`.
- Do not parse plain `#words` in normal Post or Note bodies.
- Do not inject hashtag links at render time.
- Do not auto-map editorial tags, translations, or related concepts.
- Do not expose attachment tags in ActivityPub by default.
