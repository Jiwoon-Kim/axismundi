# Switch - WordPress Mapping (v3.6.10 Phase 2)

## Status

No WordPress binding is implemented in v3.6.10.

## Theme Boundary

The Switch lab module is a reference implementation. It does not create:

```txt
theme.json controls
core block bridge mappings
editor sidebar controls
form submission handlers
custom blocks
plugin runtime
```

## Future Mapping Notes

Switch can eventually represent boolean editor settings or plugin options, but
that is editor/plugin territory. It is not implemented in the theme bridge.
