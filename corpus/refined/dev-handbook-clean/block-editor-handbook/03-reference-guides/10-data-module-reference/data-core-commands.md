---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-commands/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-commands
parent_order: 3
sub_order: 10
page_order: 6
title: "The Commands Data"
---

# The Commands Data

Namespace: `core/commands`.

## Selectors

### getCommandLoaders

Returns the registered command loaders.

*Parameters*

- *state* `Object`: State tree.
- *contextual* `boolean`: Whether to return only contextual command loaders.

*Returns*

- `import('./actions').WPCommandLoaderConfig[]`: The list of registered command loaders.

### getCommands

Returns the registered static commands.

*Parameters*

- *state* `Object`: State tree.
- *contextual* `boolean`: Whether to return only contextual commands.

*Returns*

- `import('./actions').WPCommandConfig[]`: The list of registered commands.

### getContext

Returns whether the active context.

*Parameters*

- *state* `Object`: State tree.

*Returns*

- `string`: Context.

### isOpen

Returns whether the command palette is open.

*Parameters*

- *state* `Object`: State tree.

*Returns*

- `boolean`: Returns whether the command palette is open.

## Actions

### close

Closes the command palette.

*Returns*

- `Object`: action.

### open

Opens the command palette.

*Returns*

- `Object`: action.

### registerCommand

Returns an action object used to register a new command.

*Parameters*

- *config* `WPCommandConfig`: Command config.

*Returns*

- `Object`: action.

### registerCommandLoader

Register command loader.

*Parameters*

- *config* `WPCommandLoaderConfig`: Command loader config.

*Returns*

- `Object`: action.

### unregisterCommand

Returns an action object used to unregister a command.

*Parameters*

- *name* `string`: Command name.

*Returns*

- `Object`: action.

### unregisterCommandLoader

Unregister command loader hook.

*Parameters*

- *name* `string`: Command loader name.

*Returns*

- `Object`: action.
