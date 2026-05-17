# Legacy notice — axismundi-prototype

> This directory was demoted from active visual authority to legacy
> reference in v3.3.0 (2026-05-13). It is preserved for historical
> reference only.

## Why demoted

The prototype was authored under a social-CMS / ActivityPub-frame
assumption. Patterns and templates here mix federation concerns into
theme territory, which violates Axismundi Constitution Article 2
(Platform ≠ Design system ≠ Federation).

## Current visual authority

`products/reference-implementations/axismundi-lab/` is the active
visual QA surface as of v3.3.0. The root `/styleguide/` publish mirror
also reflects lab.

## Future

A clean `axismundi-pilot/` reference implementation will be authored
after lab QA stabilizes. That pilot will draw from this archive
**selectively** — patterns proven via lab visual QA will be promoted;
patterns tied to social-CMS-frame assumptions will not.

## What lives here

Everything that was in `axismundi-prototype/` at the time of v3.3.0
freeze. Assets that were moved to `core/design-systems/material3/assets/`
in the same release are still present here as historical copies.


## What was removed from this archive

- `assets/fonts/` and `assets/icons/` — these were moved to
  `core/design-systems/material3/assets/` in v3.3.0 (single source of truth).
  The current Material 3 design system assets are still available there.
  The Roboto subset variants that existed at this prototype's snapshot
  (the v3.2.0 Latin-only subset) are NOT preserved here — they were
  superseded by the v3.2.3 full-coverage assets.
