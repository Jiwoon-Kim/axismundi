# Carousel — Visual QA Checklist

> Manual QA checklist for the carousel lab module. Open
> `products/reference-implementations/axismundi-lab/lab-carousel-pattern.html`
> in a browser (or visit `/styleguide/lab-carousel-pattern.html` if it gets
> published) and walk this list. Fill in **Pass / Fail / N/A** in the
> right-hand column. Anything Fail becomes a blocker on the promotion
> criteria in `CAROUSEL-AUDIT.md`.

Run the checklist twice — once in light theme, once in dark theme. The
theme switcher chips at the top of the page toggle this.

---

## 1. Render & layout — Multi-browse subsection

| # | Check | Result |
|---|---|---|
| 1.1 | Track scrolls horizontally with mouse wheel / trackpad swipe | |
| 1.2 | First item is large, mid items are medium, trailing items are small (the multi-browse mix) | |
| 1.3 | All item corners are `corner-extra-large` (visibly the same radius as M3 large surfaces) | |
| 1.4 | Korean labels (`서울의 하루`, `디자인 노트`, `개발 일지`) render correctly with no missing-glyph rectangles | |
| 1.5 | No horizontal scrollbar artifact below the carousel (carousel handles its own overflow) | |
| 1.6 | Page background is `surface` and carousel sits on it cleanly (no surface-tone collision like the v3.3.1 visual-QA stripes / `.sg-demo` case) | |

## 2. Render & layout — Hero subsection

| # | Check | Result |
|---|---|---|
| 2.1 | First item is large (the "hero"), remaining items are queued as smaller items | |
| 2.2 | Gap between hero and queue is `8px` (smaller than multi-browse) | |
| 2.3 | Hero item retains its aspect / corner radius on resize | |

## 3. Render & layout — Uncontained subsection

| # | Check | Result |
|---|---|---|
| 3.1 | Items bleed past the trailing edge of their container (intentional — content-first browsing) | |
| 3.2 | Leading edge respects `16px` padding (does not bleed past the leading edge) | |
| 3.3 | Items keep their corner radius even when partially clipped at the trailing edge | |

## 4. Material You morph slider (interactive)

| # | Check | Result |
|---|---|---|
| 4.1 | Layout chip group (Multi / Hero / Uncontained) updates the slider layout on click | |
| 4.2 | Slides-per-view chip group (1 / 2 / 3 / 4 / 5) updates the visible-slide count in Multi mode | |
| 4.3 | Compact toggle (checkbox) shrinks the item size profile | |
| 4.4 | Pointer drag (mouse / touch / pen) advances the slider with momentum-style feel | |
| 4.5 | Item shape morphs (small ↔ large) as items pass through scroll positions — this is the Material You "morph" effect | |
| 4.6 | Prev / Next buttons in `__nav` advance one item per click | |
| 4.7 | Dots in `__nav__dots` reflect the current slide index | |
| 4.8 | Selected dot (`.is-selected`) is wider and uses `primary` color; unselected dots use a faded `on-surface-variant` | |

## 5. Keyboard operability (Criterion 4 of CAROUSEL-AUDIT.md)

| # | Check | Result |
|---|---|---|
| 5.1 | `Tab` reaches the carousel viewport with a visible focus indicator | |
| 5.2 | `ArrowRight` / `ArrowLeft` advance / retreat by one item when carousel has focus | |
| 5.3 | `Home` / `End` jump to first / last item | |
| 5.4 | `Tab` continues into the demo nav (prev, next, dots) — focus order is logical | |
| 5.5 | Chip group buttons (Multi / Hero / Uncontained, 1–5 SPV) are reachable by keyboard and toggleable by `Space` / `Enter` | |
| 5.6 | Focus is never trapped inside the carousel | |

Any **Fail** here blocks Criterion 4.

## 6. Reduced motion (Criterion 3 of CAROUSEL-AUDIT.md)

Enable `prefers-reduced-motion: reduce` at the OS level (macOS: System Settings
→ Accessibility → Display → Reduce motion; Windows: Settings → Accessibility
→ Visual effects → Animation effects off; Chrome DevTools: Rendering pane →
Emulate CSS media feature `prefers-reduced-motion`).

| # | Check | Result |
|---|---|---|
| 6.1 | When reduce-motion is on, the morph animation does not run — items appear at their final shape instantly | |
| 6.2 | Drag still works (drag is interaction, not animation — should not be disabled) | |
| 6.3 | Prev / Next button clicks still advance — but they snap instantly instead of animating | |

If any of 6.1 / 6.3 fail, that is the known blocker from CAROUSEL-AUDIT.md
Criterion 3 — the module lacks an explicit `@media (prefers-reduced-motion:
reduce)` rule. Note the fail; it's expected until a fix lands.

## 7. JS-disabled fallback (Criterion 1 of CAROUSEL-AUDIT.md)

In the browser's dev tools, disable JavaScript and reload the page.

| # | Check | Result |
|---|---|---|
| 7.1 | Page loads (no JS errors block CSS) | |
| 7.2 | Carousel viewport renders with all items in a horizontal row | |
| 7.3 | Horizontal scroll still works via wheel / trackpad / scrollbar | |
| 7.4 | Scroll-snap still snaps to item boundaries | |
| 7.5 | Demo bar buttons (Multi / Hero / etc.) are present but inert (clicking them does nothing — this is acceptable as long as the no-JS render is usable) | |

Any **Fail** on 7.1–7.4 blocks Criterion 1.

## 8. `.prose` isolation (Criterion 5 of CAROUSEL-AUDIT.md)

| # | Check | Result |
|---|---|---|
| 8.1 | Wrap a copy of the carousel in `<div class="prose">…</div>` (or paste it inside the prose styleguide) and confirm the carousel still renders identically — no `.prose` rules override carousel styling | |
| 8.2 | The carousel does not change `.prose` typography for sibling content (no leak in the other direction either) | |

## 9. Light / Dark theme parity

Re-run sections 1–4 in the opposite theme via the page's theme switcher.

| # | Check | Result |
|---|---|---|
| 9.1 | All `var(--md-sys-color-*)` tokens resolve in dark theme — no hard-coded colors visible | |
| 9.2 | Contrast between item label text and item background is readable in both themes | |
| 9.3 | Selected dot color (`primary`) is visible against the page background in both themes | |
| 9.4 | Drag-state hover tints (state layer) are visible in both themes | |

## 10. Korean / mixed-script text

| # | Check | Result |
|---|---|---|
| 10.1 | Item labels in Korean (`서울의 하루`, `디자인 노트`, `개발 일지`) render with no glyph fallback rectangles | |
| 10.2 | Mixed-script labels (Korean + Latin in one label, if you add one) wrap correctly with `word-break: keep-all` / `overflow-wrap: anywhere` per base.css §7 | |
| 10.3 | Label container does not clip Korean descenders | |

---

## Summary

Total Pass: ___  
Total Fail: ___  
Total N/A: ___  

Top failures to address before this module can be promoted into
`components.css §G3 Carousel`:

1. _(fill in after walking the list)_
2.
3.

Re-run this checklist whenever the module changes. Append the date and the
git commit / version tag to the bottom of this file each time:

| Date | Version | Reviewer | Outcome |
|---|---|---|---|
| _(not yet QA'd)_ | v3.3.2 | | |
