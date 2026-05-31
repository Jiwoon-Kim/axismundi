# Omphalos — `tokens.comp.css` admission audit

> **Scope**: Omphalos child theme only. The same token names live in the lab /
> styleguide / pilot copies and are actively used *there* — this audit is about
> what **Omphalos's own shipped CSS** consumes, so the runtime token surface
> isn't a warehouse of "maybe someday" handles.
>
> **Method**: every `--comp-*` / semantic-alias / `--layout-*` token defined in
> `assets/styles/tokens.comp.css`, cross-referenced against `var(--…)` uses in
> Omphalos's rendered CSS (`blocks.css`, `prose.css`, `foundation.css`) and
> `theme.json` / PHP. Intra-`tokens.comp.css` references (one comp token aliasing
> another) do **not** count as real use.
>
> **Last run**: 2026-05-31 (after the core/group Card contract, blocks.css §8).

---

## Admission policy

A token earns a place in `tokens.comp.css` (the runtime component-token layer
Omphalos ships and enqueues) when **one** of these holds:

1. It is referenced by active Omphalos CSS (`blocks.css` / `prose.css` /
   `foundation.css`) or `theme.json`, **or**
2. It backs an active Omphalos registered block-style / component contract.

Tokens that are defined but only meaningful to lab / app-shell / future component
work are **isolation candidates**: they stay for now (no deletion), but they are
tracked here and migrate to a `tokens.comp.future.css` (or are dropped from the
Omphalos copy) as each contract is settled — promoted back the moment a real
Omphalos contract consumes them. Promotion is per-phase, never a big-bang move.

---

## Findings — Omphalos runtime usage

Of ~50 tokens defined, **2** are consumed by Omphalos shipped CSS:

| Token | Consumed by | Disposition |
|---|---|---|
| `--comp-card-padding` | `blocks.css §8` (core/group card) | **KEEP** |
| `--comp-card-radius` | `blocks.css §8` (core/group card) | **KEEP** |

Everything else is unreferenced by Omphalos (defined-only).

---

## Isolation candidates (defined, not used by Omphalos yet)

### A. Core-block-relevant — likely promoted when the block contract lands

These map to WordPress core blocks Omphalos has not contracted yet (chiefly
`core/button` / `core/buttons`, pending its semantic route). Keep nearby.

```txt
--comp-button-height            --comp-button-radius
--comp-button-height-{xs,s,m,l,xl}
--comp-button-padding-inline-{xs,s,m,l,xl}
--comp-button-icon-size-{xs,s,m,l,xl}
--comp-button-outline-width-{xs,s,m,l,xl}
--comp-icon-size-{sm,md,lg,xl}
--comp-touch-target
```

### B. App-component — not a core block; isolate out of Omphalos runtime

These describe app-shell / composite surfaces (feed, avatar, nav rail, dialogs,
multi-column layout) with no core-block bridge in Omphalos. They belong to lab /
future app work, not the core-block theme runtime. Prime `tokens.comp.future.css`
candidates.

```txt
§9  --comp-feed-gap        --comp-feed-max
    --comp-avatar-size      --comp-avatar-radius
    --comp-rail-width       --comp-rail-narrow      --comp-rail-expanded
§10 --site-bg  --feed-bg  --card-bg  --card-bg-hover  --modal-bg
    --nav-rail-bg  --nav-rail-bg-modal  --nav-bar-bg
    --nav-item-active  --nav-on-item-active
    --action-primary-bg  --action-primary-fg
    --action-secondary-bg  --action-secondary-fg
§11 --layout-content-max  --layout-rail-width  --layout-rail-narrow
    --layout-rail-expanded  --layout-rail-expanded-max  --layout-feed-max
    --layout-aside-max  --layout-modal-max  --layout-bottom-sheet-max
```

> Note: these are **not dead** — `axismundi-pilot/assets/styles/components.css`
> and `wp-custom.bridge.css` consume the button / avatar / icon / touch-target
> sets today. This audit only says Omphalos's own runtime does not.

---

## Next steps (gradual, not now)

1. No deletion. Keep `tokens.comp.css` intact this phase.
2. When `core/button` (and friends) get their Omphalos contract, promote the
   exact button/icon/touch tokens consumed into the KEEP set; leave the rest.
3. When Bucket B is confirmed out of Omphalos's core-block scope, move it to
   `tokens.comp.future.css` (or drop from the Omphalos copy) in one focused
   commit, updating this audit.

---

## Related deferred decisions (recorded here per request)

- **Card style-variation scope** — intentionally `.wp-block-post-content`-scoped
  (blocks.css §8), consistent with the other Omphalos variations. Lifting the
  scope (so cards work in template parts / Query Loop / patterns) is deferred
  until that surface's card semantics are settled; the promotion target would be
  `components.css` or a global `.wp-block-group.is-style-card-*`.
- **Dark-mode elevated card shadow** — intentionally `none` (`tokens.sys.dark`
  `--md-sys-elevation-shadow-level1: none`). M3 dark elevation reads via surface
  tint / tonal separation, not shadow. Left as-is; revisiting is a token-level
  decision, not a card-contract one.
