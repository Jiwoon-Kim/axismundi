# WordPress Architecture

This directory keeps editable source diagrams for the WordPress substrate that
Axismundi reads and extends. Mermaid fenced blocks are the source of record:
GitHub renders them, a Mermaid editor can edit the same text, and no generated
SVG needs to be treated as architecture data.

## Conventions

- Diagrams name WordPress's logical references. Core schema does not declare
  foreign-key constraints for these relationships.
- `PK`, `UQ`, and `KEY` reproduce the keys declared by core. A line is not a
  database-enforced foreign key unless a diagram says so explicitly.
- A meta table has four physical columns. Named `meta_key` values are rows in
  that table, not additional SQL columns.
- These are substrate diagrams, not ActivityStreams mappings or Axismundi
  proposals. Those belong in later, separately labelled views.

## First views

- [ActivityStreams Vocabulary Registry](activitystreams-vocabulary-registry.md)
  records the core AS2 type hierarchy and property registry, with extension
  registration boundaries.
- [Core content schema](core-content-schema.md) covers posts, post meta, users,
  comments, and well-known core post-meta rows relevant to content projection.
- [Core taxonomy schema](core-taxonomy-schema.md) covers terms and the
  relationship table that classifies local objects.
- [Core post to AS2 Object mapping](core-post-to-as2-object.md) shows which
  Core facts become Object properties and which tempting one-to-one mappings
  are intentionally rejected.
- [ActivityStreams storage boundaries](activitystreams-storage-boundaries.md)
  classifies local authoring facts, local taxonomy, graph edges, remote cache
  state, and activity history before any new schema is introduced.
- [ActivityStreams collection model](activitystreams-collection-model.md)
  records the contract descriptor axes for every published collection, which
  pins cite a specification and which record a project choice, and the
  measurements behind both.
- [ActivityStreams Object contract](activitystreams-object-contract.md) fixes
  the single JSON-LD Object that the frontend application and the block
  presenters share: source readers, assembly by type hierarchy, value priority
  with its payload fallback, extension preservation, provenance and disclosure.

The source is WordPress core's
[`wp_get_db_schema()`](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-admin/includes/schema.php).
The diagrams were checked against its single-site/blog table declarations on
2026-10-15. Multisite network tables deliberately remain a later view.
