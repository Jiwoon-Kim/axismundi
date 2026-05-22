# Menu WordPress Mapping

Status: v3.6.9 Phase 2 lab implementation.

## Mapping

No WordPress block binding is introduced in v3.6.9.

Menu remains a lab component module and a consumer of the lab `popover/`
provider. It is not mapped to a core WordPress block, custom block, or plugin
runtime in this cycle.

## Boundaries

- `theme.json` unchanged.
- `functions.php` unchanged.
- Pilot bridge files unchanged.
- WordPress specimen fixtures unchanged.
- BACKLOG #41 shared WordPress ripple runtime packaging remains unchanged.

## Future Notes

Potential future WordPress or plugin work should decide whether Menu surfaces
come from Navigation, command palettes, plugin menus, or custom blocks. That is
not part of the v3.6.9 Menu lab closure.
