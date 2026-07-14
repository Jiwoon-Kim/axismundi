# Roadmap

The plugin grows one small increment at a time; each phase ends green on its fixtures and
Plugin Check 0.

```
Phase 0  contract + scaffold                         — shipped (0.0.1): docs + plugin file
Phase 1  transformer registry + renderer             — shipped (0.0.1): no table, no route
Phase 2  content-negotiation router (standalone)     — next; Accept on the existing URL,
                                                       Vary/Link, single-negotiator gate
Phase 2  Core Post → Article transformer             — /?p={ID} id, permalink url, public gates
Phase 3  Article OrderedCollection (per Actor)       — registers the `articles` projection
Phase 4  lifecycle events (publish/update/delete)    — emit only; Activities stores them
Phase 5  extension & hardening                       — media/notes hooks, context registry,
                                                       cache invalidation, isolation, escaping
```

Phases 0 and 1 are combined into the first release (0.0.1): the contract docs plus the
working registry and renderer, with **no HTTP routing** so it cannot contend with the
official ActivityPub plugin for a URL yet.

## Ordering beyond this plugin

```
Object Projections (this) → axismundi-activities (Create/Update/Delete store)
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
