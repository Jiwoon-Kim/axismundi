# App Bar Measurement Audit

Status: v3.6.8 Phase 2 lab implementation.

## Measurements

- Small app bar target height: 64px specimen row.
- Medium app bar target height: 112px specimen row.
- Large app bar target height: 152px specimen row.
- Action target minimum: 48px square.
- Shape: app bar surfaces remain unrounded and full-width inside the specimen frame.

## Responsive Checks

- Desktop: actions remain on the trailing edge and title blocks remain readable.
- Mobile: action overflow wraps within the specimen surface instead of escaping the frame.
- Scrolled example keeps a visible bottom divider and compact density.

## Source Boundary

Measurements are captured in `lab-app-bar-pattern.html` and framed by `lab-app-bar.css`. The baseline `components.css` file remains unchanged for v3.6.8.
