# Date/Time Picker WordPress Mapping

Status: v3.6.12 Route A completion

## Current Mapping

Date+Time is a lab module only. It has no WordPress block binding, editor sidebar
binding, post-meta persistence, or Pilot bridge integration in v3.6.12.

## Explicit Non-Goals

- WordPress editor sidebar date control
- post meta date binding
- ActivityPub Event object integration
- recurring event date logic
- timezone normalization
- locale calendar systems
- shared WordPress ripple/runtime packaging

## Future Plugin Territory

If Date+Time later needs persistence, locale-aware calendars, timezone handling,
or editor-binding behavior, that work belongs to a plugin/application strategy
cycle. It should not be folded into the lab module completion route.

## Lock Boundaries

v3.6.12 does not touch:

- `theme.json`
- `functions.php`
- Pilot bridge source or assets
- Pilot fixtures
- WordPress validators or generator contracts
