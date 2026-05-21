# Nav Rail Measurement Audit

Status: v3.6.8 Phase 2 lab implementation.

## Measurements

- Collapsed rail width target: 80px.
- Expanded rail width target: 256px.
- Destination minimum height: 56px.
- Active indicator target: rounded container behind icon or icon+label group.
- FAB / leading action area remains separate from destination list.

## Responsive Checks

- Desktop specimen shows collapsed and expanded rails side by side.
- Narrow viewport stacks rail examples without horizontal overflow.
- Badge and disabled states do not change rail width.

## Source Boundary

Measurements are captured in `lab-nav-rail-pattern.html` and framed by `lab-nav-rail.css`. The baseline `components.css` file remains unchanged for v3.6.8.
