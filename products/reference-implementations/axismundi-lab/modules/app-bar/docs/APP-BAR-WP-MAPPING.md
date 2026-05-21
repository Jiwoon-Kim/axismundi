# App Bar WordPress Mapping

Status: v3.6.8 Phase 2 lab implementation.

## Mapping

- Candidate WordPress surfaces: site header templates, navigation header patterns, and editor preview shells.
- Current v3.6.8 artifact: lab-only pattern page.
- No Pilot theme binding is introduced.

## Boundary

- This module does not reopen WP core/button semantic routing.
- This module does not create a custom block contract.
- Action slots remain ripple CANDIDATE until a future review explicitly promotes them.

## Future Notes

If App bar later enters a WordPress binding, it should route through a dedicated header/template integration plan rather than leaking lab selectors into Pilot theme CSS.
