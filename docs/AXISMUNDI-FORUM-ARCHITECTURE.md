# Axismundi Forum Architecture

## Scope

Forum adds community discussion to a local managed Group Actor. It does not create a
second community object, identity, public profile, or authority system.

```
Axismundi Actors       identity, Group profile, handle, manager delegation
Axismundi Activities   Follow/Accept ledger and relation state
Axismundi Forum        community policy, Topic and membership projections
Axismundi Note         replies and reply federation
Object Projections     public ActivityStreams representations and collections
```

The Group Actor is the public community page. `ax_forum` is intentionally not a CPT.
There is no `/forum/` route and no Group-to-Forum binding record.

## Community Enablement

A Group becomes a community when `wp_ax_forum_settings` has a row for its identity.
Only a local, managed `Group` can have that row, and only a user who manages the Group
through Actors can create or update it.

```
wp_ax_forum_settings
  group_identity_id PRIMARY KEY
  posting_policy       open | managers
  membership_policy    open | approval
```

Disabling a community deletes only its Forum settings row. It never alters the Actor.
Forum refuses to disable a community while it still has Topic entries or membership
projections.

## Topics

`ax_topic` is the local authoring container. An admitted Topic has exactly one entry:

```
wp_ax_forum_entries
  group_identity_id
  object_uri_hash
  source_post_id
  entry_type
  admission_state / moderation_state
  locked_at / sticky_position
```

The unique `(group_identity_id, object_uri_hash)` key prevents duplicate admission.
The Topic editor offers Communities the current user manages. Once admitted, its
community is immutable.

Outbound roots are ActivityStreams `Article` objects. The Group is `audience`; a
resolvable per-Topic URI is `context`. Replies are Notes with `inReplyTo` and inherit
the parent's context. Inbound roots accept Article and Page for interoperability;
bare Notes do not open threads.

## Membership

Membership is a Forum projection of Activities relations, never a second Follow ledger.

```
wp_ax_forum_memberships
  group_identity_id
  actor_identity_id
  membership_evidence_activity_uri
  membership_state
  PRIMARY KEY (group_identity_id, actor_identity_id)
```

A Follow to a local managed Group is evidence. `open` accepts a pending Follow;
`approval` leaves it pending until a Group manager accepts or rejects it. The source
relation is accepted before the projection states accepted. Rebuild replaces the full
projection from the Activities ledger without emitting federation traffic.

`Join` is reserved as future evidence syntax only. Activities does not currently
produce Join relations, so enabling that requires relation and Accept/Reject support
there first.

## Moderation Boundary

A local Group moderates its own community. Moderators are published through the Group's
`attributedTo` collection, as FEP-1b12 asks, and every local moderation decision passes
the Actors permission kernel.

**Remote Group moderation is not supported.** A moderation activity that arrives from a
remote community changes nothing here: there is no receiving path for it, and this is a
stated boundary rather than an oversight. A site that joins a remote community therefore
keeps showing what its own copy holds, whatever that community's moderators decide.

Supporting it means implementing both checks FEP-1b12 requires, together:

1. the activity's `actor` is listed in that Group's `attributedTo` collection, and
2. the activity arrived inside the Group's own `Announce`.

Neither check is optional. Applying a received ban without verifying the authority behind
it is the failure Lemmy published a security advisory about. The same rule holds for the
scope of a ban: a community ban and a site ban are different claims by different
authorities, so a receiving implementation has to read `target` rather than treat every
`Block` alike.

## Development Reset and Uninstall

Forum is pre-release. Its schema is Group-keyed from the beginning, with no upgrade
migration from the retired CPT design. A stale Forum schema is a development database
that must be reset.

The plugin uninstall handler removes only Forum-owned tables:

- `wp_ax_forum_settings`
- `wp_ax_forum_entries`
- `wp_ax_forum_memberships`
- the retired development-only `wp_ax_forum_bindings`, if present

It leaves Group Actors, Topics, Activity records, and relations untouched.
