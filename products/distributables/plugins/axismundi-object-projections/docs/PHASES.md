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
Phase 3  Article OrderedCollection (per Actor)       — registers the `articles` projection
Phase 4  lifecycle events (publish/update/delete)    — emit only; Activities stores them
Phase 5  extension & hardening                       — media/notes hooks, context registry,
                                                       cache invalidation, isolation, escaping
Phase 6  remote object projections                   — URI-keyed observed cache + admin
                                                       inspector; no Activity required,
                                                       no render-time fetch or media hotlink
```

Phases 0 and 1 are combined into 0.0.1. Phase 2 adds standalone routing in 0.0.2 but
automatically disables it when the official ActivityPub plugin is active.

## Ordering beyond this plugin

```
Object Projections (this, including remote projection substrate)
  → axismundi-activities (Create/Update/Delete store)
  → axismundi-notes → media object transformer
  → axismundi-federation → official ActivityPub compatibility adapter spike
```

## Release gate (per phase, from Phase 2 on)

- Plain + pretty endpoints both work under negotiation.
- No private / password / draft leakage.
- Object `id` and `attributedTo` agree with the Actor URI.
- Collection pagination is stable.
- Works standalone (no Activities plugin, no official ActivityPub plugin).
- Plugin Check: 0 errors.
