# v3.5.18 — Pre-Pilot Smoke Checklist

Status: Phase 2 smoke record  
Date: 2026-05-18  
Purpose: apply the v3.5.17 portal/overlay lesson before v3.6.0 Pilot entry

## §0. Standard

For any trigger/runtime/host split, smoke QA must verify:

```txt
□ trigger exists
□ runtime handler attaches
□ host/portal element exists
□ open/visible state works
□ close/dismiss path works
□ console/page errors are absent
□ source and generated mirror agree where applicable
```

## §1. Targets

```txt
styleguide/index.html
styleguide/blocks.html
styleguide/prose.html
products/reference-implementations/axismundi-lab/typography-axis.html
products/reference-implementations/axismundi-lab/modules/*/lab-*-pattern.html
```

Total pages checked: **20**.

## §2. Results

| Surface | Checks | Result |
|---|---|---|
| `styleguide/index.html` | Dialog basic/full, Sheet bottom/side, mobile drawer, no console/page errors | PASS |
| `styleguide/blocks.html` | Render, `theme.js` present, no console/page errors, overflow 0 at 390px | PASS |
| `styleguide/prose.html` | Render, `theme.js` present, no console/page errors, overflow 0 at 390px after local containment fix | PASS |
| `typography-axis.html` | Render, `.axis-control-shell` exists, overflow 0 | PASS |
| 16 lab pattern pages | Render smoke, no console/page errors, overflow 0 at 390px | PASS |

## §3. v3.5.17 Regression Guard

The styleguide live modal contract now passes:

```txt
PASS  [data-open-dialog="basic"] -> #sg-modal-basic
PASS  [data-open-dialog="full"] -> #sg-modal-full
PASS  [data-open-sheet="bottom"] -> #sg-sheet-bottom
PASS  [data-open-sheet="side"] -> #sg-sheet-side
PASS  Escape closes active surface
PASS  #sg-portal exists in generated mirror
```

## §4. Findings

### Finding 1 — prose.html mobile overflow

Severity: P2, fixed in-cycle.

```txt
Cause:
  `.sg-article` used a 65ch measure plus padding without enough grid/item
  containment at 390px.

Fix:
  Add local layout containment in `style-guide-prose.html`.

Result:
  styleguide/prose.html overflow = 0 at 390px.
```

### Finding 2 — blocks/prose shell consistency

Severity: P3, deferred.

```txt
blocks.html and prose.html do not yet use the v3.5.17 mobile top bar /
Sheet-style drawer shell. This is cosmetic/public-surface consistency, not a
Pilot-blocking spec problem.

Route:
  BACKLOG #39.
```

## §5. Non-Goals

- No screenshots committed.
- No baseline component CSS/tokens changed.
- No `blocks.html` / `prose.html` shell rebuild performed.
