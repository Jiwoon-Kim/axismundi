# Tabs Measurement Audit

Status: v3.6.8 Phase 2 lab implementation.

## Measurements

- Tab minimum height target: 48px.
- Primary indicator target: 3px active underline.
- Secondary indicator target: 2px active underline.
- Icon tab glyph target: 24px Material Symbols glyph.
- Focus outline remains visible around the active tab host.

## Responsive Checks

- Desktop specimen keeps tablists readable and panel content aligned.
- Mobile specimen allows horizontal scrolling for dense tablists without page overflow.
- Disabled tab does not affect indicator placement.

## Source Boundary

Measurements are captured in `lab-tabs-pattern.html` and framed by `lab-tabs.css`. The baseline `components.css` file remains unchanged for v3.6.8.
