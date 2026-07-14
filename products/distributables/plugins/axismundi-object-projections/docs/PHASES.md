# Roadmap

The plugin grows one small increment at a time; each phase ends green on its fixtures and
Plugin Check 0.

```
Phase 0  contract + scaffold                         — shipped (0.0.1): docs + plugin file
Phase 1  transformer registry + renderer             — shipped (0.0.1): no table, no route
Phase 2a content-negotiation router (standalone)     — shipped (0.0.2): exact Accept,
                                                       Vary/Link, single-negotiator gate
Phase 2b Core Post → Article transformer             — shipped (0.0.2): /?p={ID} id,
                                                       permalink url, Actor/public gates
Phase 2c explicit representation selector            — shipped (0.0.3): ?activitypub
                                                       for browser inspection; id unchanged
Phase 3a first-party Media Library adapter           — shipped (0.0.4): optional detection,
                                                       public service API boundary
Phase 3b Attachment → Image/Video/Audio/Document     — shipped (0.0.4): strict anonymous
                                                       visibility, bounded image rendition
Phase 3c Media archive/folder OrderedCollections     — home/author/folder collection views
Phase 4a remote object repository                    — shipped (0.0.5): URI-keyed observed
                                                       cache table; no network/public route
Phase 4b remote fetch + administrator inspector      — bounded fetch, refresh/purge,
                                                       no render-time fetch/media hotlink
Phase 5  lifecycle events (publish/update/delete)    — emit only; Activities stores them
Phase 6  extension & hardening                       — notes, collections, cache
                                                       invalidation, isolation, escaping
```

Phases 0 and 1 are combined into 0.0.1. Phase 2 adds standalone routing in 0.0.2 but
automatically disables it when the official ActivityPub plugin is active.

## Ordering beyond this plugin

```
Object Projections (this, including remote projection substrate)
  → axismundi-activities (Actor outbox + Create/Update/Delete store)
  → axismundi-notes
  → axismundi-federation → official ActivityPub compatibility adapter spike
```

An Actor profile's primary feed is the Activities-owned outbox and may contain Note,
Article, media, Announce, and other activities. An `articles` profile projection may be
added later as an optional filtered tab; it is never the Actor's primary feed.

## Release gate (per phase, from Phase 2 on)

- Plain + pretty endpoints both work under negotiation.
- No private / password / draft leakage.
- Object `id` and `attributedTo` agree with the Actor URI.
- Collection pagination is stable.
- Works standalone (no Activities plugin, no official ActivityPub plugin).
- Plugin Check: 0 errors.
