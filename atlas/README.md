# Atlas — B layer

Rule-based knowledge: 113 rules across 11 DDD bounded contexts.

- `wordpress/` — WordPress platform knowledge atlas
- DSL: 6-slot per rule (rule_id / domain / topic / WHEN / THEN / related)
- Cross-references via `related:` field across bounded contexts

**Rule**: atlas is rule-grain, not type-grain. If something is a typed entity (BlockType, ThemeToken), it lives in `core/` instead.

See `wordpress/_meta/dsl-spec.md` for the rule schema.
