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
Phase 2d Axismundi Actor representation              — shipped (0.0.9): Actor URL JSON-LD;
                                                       Bridge supplies transport fields only
Phase 2e Actor public Outbox representation          — shipped (0.0.10): neutral REST URI;
                                                       Activities public-safe query contract
Phase 2f Activity JSON-LD transport finalization     — shipped (0.0.11): canonical context;
                                                       immutable ledger remains unchanged
Phase 2g Object likes collection                     — shipped (0.0.12): count-only public
                                                       REST collection; no liker enumeration
Phase 2h FEP-b2b8 Article representation             — shipped (0.0.13): positive HTML
                                                       allowlist, summary/preview/image/Link
Phase 3a first-party Media Library adapter           — shipped (0.0.4): optional detection,
                                                       public service API boundary
Phase 3b Attachment → Image/Video/Audio/Document     — shipped (0.0.4): strict anonymous
                                                       visibility, bounded image rendition
Phase 3c Article media + Attachment usedIn           — shipped (0.0.13): relation-indexed
                                                       attachment/image and public reverse use
Phase 3d Media archive/folder OrderedCollections     — shared folder shipped (0.0.18): UUID
                                                       root + bounded pages; home/author later
Phase 3e FEP-1311 media renditions                   — shipped (0.0.16 + Media Library 0.0.27):
                                                       url[] Link array, media first, original
                                                       never advertised, max 4 existing
                                                       derivatives. Standalone name = title;
                                                       embedded name = alt. Media Library owns
                                                       selection (MEDIA-RENDITIONS.md).
Phase 3f shared-folder rendition consumer            — read-only feasibility shipped (0.0.18):
                                                       remote root + first-page metadata probe;
                                                       binary caching/shadow records deferred.
Phase 4a remote object repository                    — shipped (0.0.5): URI-keyed observed
                                                       cache table; no network/public route
Phase 4b remote fetch + administrator inspector      — shipped (0.0.6): metadata-only,
                                                       bounded fetch, 30-day retention
Phase 4c multi-reason object leases                  — shipped (0.0.12): DB v3 interaction,
                                                       collection, and shared-shadow holds
Phase 5a Core Post publish candidate                — shipped (0.0.8): post-commit,
                                                       single-owner, Create consumer seam
Phase 5b update/delete lifecycle events             — emit only; Activities stores them
Phase 6  extension & hardening                       — notes, collections, cache
                                                       invalidation, isolation, escaping
```

Phases 0 and 1 are combined into 0.0.1. Phase 2 adds standalone routing in 0.0.2 but
automatically disables it when the official ActivityPub plugin is active.

## Ordering beyond this plugin

```
Object Projections (this, including remote projection substrate)
  → axismundi-activities (public Outbox query + Create/Update/Delete store)
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
