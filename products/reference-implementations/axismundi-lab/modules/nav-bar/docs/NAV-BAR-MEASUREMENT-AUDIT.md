# Nav Bar Measurement Audit

Status: v3.6.8 Phase 2 lab implementation.

## Measurements

- Container height target: 80px.
- Destination minimum width: 64px.
- Icon target: 24px Material Symbols glyph inside a stable destination area.
- Active indicator target: compact pill behind the icon.
- Touch target: destination host is large enough for pointer and keyboard inspection.

## Responsive Checks

- Desktop specimen constrains the bar so mobile proportions remain visible.
- Mobile specimen keeps all five destinations within the viewport width.
- Badge and disabled states do not change the bar height.

## Source Boundary

Measurements are captured in `lab-nav-bar-pattern.html` and framed by `lab-nav-bar.css`. The baseline `components.css` file remains unchanged for v3.6.8.
