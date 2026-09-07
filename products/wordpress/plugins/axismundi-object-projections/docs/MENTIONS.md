# Mention contract

## Purpose and ownership

An ActivityStreams `Mention` is a Link to an Actor, not a second Actor object
and not a taxonomy term. Axismundi Actors owns Actor identity, canonical URI,
and handle lookup. Object Projections owns mention authoring conversion,
parsing, and object-to-Actor relation semantics.

`Mention` and ActivityStreams audience remain distinct:

```text
Mention tag = an Object refers to an Actor.
to / cc / audience = delivery and visibility policy.
```

Mentioning an Actor must not silently widen an audience. The Activities audience
policy may deliberately use explicit mention recipients for a mentioned-only
Object, but that is a separate policy decision.

## Authority and authoring

The canonical stored value is the resolved Actor URI. A handle is an editor
input and display value only.

```text
editor token: @alice@example.test
stored authority: https://example.test/actors/alice
AS tag: { type: Mention, name: @alice@example.test, href: canonical URI }
```

The editor accepts comma, Enter, and autocomplete selection as token boundaries.
Only Actors resolvable through the Actors registry may become authored tokens;
unresolved free-form handles are not serialized as Mentions. The token UI must
not expose raw URIs as its primary interaction.

Existing `a.mention[href]` links in saved block HTML remain a valid derived
source. Their URI is verified at the publish boundary. Plain `@name` text is
not auto-converted at render time.

## Relation model

The local-authoring authority remains the existing explicit URI list plus
content-derived anchors. `wp_ax_object_mentions` is a rebuildable directional
projection over that authority and remote payload snapshots:

```text
source Object URI
target Actor URI
origin: explicit | inline | remote
```

An Object may carry more than one edge to the same Actor when distinct
provenance exists. This lets an editor review explicit document-level mentions
separately from visible body anchors without changing ActivityStreams output.
The relation table is not an audience, notification, or public-discovery
authorization source.

Remote Object mentions retain an unresolved target URI because an observed
Object is valid even when its mentioned Actor has not been fetched. Actor
discovery is best effort and never a synchronous rendering dependency.

## Body behavior

The existing block-editor `@` completer is the only automatic inline behavior:
choosing an Actor creates a normal `a.mention` link. Axismundi does not scan
plain text, inject links while rendering, or use sidebar state to rewrite body
HTML. The sidebar is for document-level mention tokens and review, not a hidden
body transformation mechanism.

## Explicit non-goals for the first increment

- Do not turn an `@` string into a Mention without an autocomplete selection.
- Do not equate Mention with `to`, `cc`, or a notification.
- Do not identify Actors by handle alone.
- Do not manufacture an Actor row for an unresolved remote mention.
