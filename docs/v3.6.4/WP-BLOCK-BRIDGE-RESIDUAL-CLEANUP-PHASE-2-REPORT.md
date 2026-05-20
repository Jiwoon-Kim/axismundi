# v3.6.4 - WP Block Bridge Residual Cleanup - Phase 2 Report

Date: 2026-05-20

Phase: 2 - Quote/Pullquote Distinct Surface Cleanup

## Verdict

Phase 2 is complete.

The v3.6.3 quote/pullquote semantic route remains intact: `core/quote` maps to
prose quote styling, while `core/pullquote` maps to a distinct editorial
pullquote surface. This phase narrows the quote bridge selectors and adds
explicit pullquote bridge rules so pullquote is no longer a side effect of
generic `blockquote` styling.

## Entry Checkpoint

Before writing the Pilot bridge translation, Phase 2 re-read the lab source
authority:

```txt
products/reference-implementations/axismundi-lab/stylesheets/blocks.css §3
```

Inventory:

```txt
.wp-block-quote
.wp-block-quote cite
.wp-block-pullquote
.wp-block-pullquote p
.wp-block-pullquote cite
```

Lab route:

```txt
core/quote:
  prose quote; citation row only

core/pullquote:
  centered editorial surface
  top/bottom dividers
  headline-medium italic paragraph
  body-small citation
```

## Changed Files

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
```

## Patch

Quote selectors now exclude the pullquote's internal blockquote:

```css
.wp-block-post-content :where(
  .wp-block-quote,
  blockquote:not(.wp-block-pullquote blockquote)
) { ... }
```

The same narrowing was applied to:

```txt
quote root
quote direct paragraph color
quote cite
quote cite::before
```

Pullquote now has explicit bridge rules:

```txt
.wp-block-post-content .wp-block-pullquote
.wp-block-post-content .wp-block-pullquote blockquote
.wp-block-post-content .wp-block-pullquote p
.wp-block-post-content .wp-block-pullquote cite
.wp-block-post-content .wp-block-pullquote cite::before
```

## Before Evidence

Temporary DOM/computed probe was used instead of expanding the committed
specimen fixture. The probe appended this structure to a `.wp-block-post-content`
host:

```html
<blockquote class="wp-block-quote">
  <p>Quote probe.</p>
  <cite>Quote Cite</cite>
</blockquote>

<figure class="wp-block-pullquote">
  <blockquote>
    <p>Pullquote probe.</p>
    <cite>Pull Cite</cite>
  </blockquote>
</figure>
```

Before:

```txt
core/quote:
  padding-inline-start:     24px
  padding-block-start:      8px
  border-inline-start:      4px solid rgb(103, 80, 164)
  cite::before:             "-- "

core/pullquote figure:
  border-block-start/end:   1px solid rgb(202, 196, 208)
  text-align:               center
  paragraph font-size:      28px
  paragraph line-height:    36px
  paragraph font-style:     italic

core/pullquote inner blockquote:
  padding-inline-start:     24px
  padding-block-start:      8px
  border-inline-start:      4px solid rgb(103, 80, 164)
  cite::before:             "-- "
```

Interpretation:

```txt
Pullquote had its distinct figure-level treatment, but the inner blockquote
silently absorbed quote styling. This is exactly the R1 risk from the Phase 0
plan.
```

## After Evidence

After patch:

```txt
core/quote:
  padding-inline-start:     24px
  padding-block-start:      8px
  border-inline-start:      4px solid rgb(103, 80, 164)
  cite::before:             "-- "

core/pullquote figure:
  border-block-start/end:   1px solid rgb(202, 196, 208)
  text-align:               center
  color:                    rgb(29, 27, 32)
  padding-block-start:      24px

core/pullquote inner blockquote:
  padding-inline-start:     0px
  padding-block-start:      0px
  border-inline-start:      0px
  color:                    rgb(29, 27, 32)

core/pullquote paragraph:
  text-align:               center
  color:                    rgb(29, 27, 32)
  font-size:                28px
  line-height:              36px
  font-style:               italic

core/pullquote cite:
  margin-block-start:       16px
  color:                    rgb(73, 69, 79)
  font-size:                12px
  font-style:               normal
  cite::before:             none
```

## Lock 4 Check

```txt
Semantic route reopened:                        no
core/quote and core/pullquote collapsed:        no
pullquote inner blockquote absorbs quote style: no
custom block work:                              no
plugin behavior:                                no
fixture expansion:                              no
```

The cleanup is allowed by Lock 4 because the semantic mismatch was routed in
v3.6.3 before this visual bridge patch.

## Source / Asset Mirror

The same CSS was applied to:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

The files remain byte-identical for this bridge.

## Validation

```txt
diff source vs asset mirror: PASS
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A-G all 1.000)
npm run validate:computed: PASS
git diff --check: PASS
```

## Next

Proceed to Phase 2 review, then Phase 3 visual QA after GO.
