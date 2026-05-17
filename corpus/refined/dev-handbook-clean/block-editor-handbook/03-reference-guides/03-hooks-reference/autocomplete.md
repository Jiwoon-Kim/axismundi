---
source_url: https://developer.wordpress.org/block-editor/reference-guides/filters/autocomplete-filters/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: hooks-reference
slug: autocomplete
parent_order: 3
sub_order: 3
page_order: 5
title: "Autocomplete"
code_quality: degraded
code_issue: pre_newline_loss
---

# Autocomplete

The `editor.Autocomplete.completers` filter is for extending and overriding the list of autocompleters used by blocks.

The `Autocomplete` component found in `@wordpress/block-editor` applies this filter. The `@wordpress/components` package provides the foundational `Autocomplete` component that does not apply such a filter, but blocks should generally use the component provided by `@wordpress/block-editor`.

### Example

Here is an example of using the `editor.Autocomplete.completers` filter to add an acronym completer. You can find full documentation for the autocompleter interface with the `Autocomplete` component in the `@wordpress/components` package.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
// Our completerconst acronymCompleter = { name: 'acronyms', triggerPrefix: '::', options: [ { letters: 'FYI', expansion: 'For Your Information' }, { letters: 'AFAIK', expansion: 'As Far As I Know' }, { letters: 'IIRC', expansion: 'If I Recall Correctly' }, ], getOptionKeywords( { letters, expansion } ) { const expansionWords = expansion.split( /\s+/ ); return [ letters, ...expansionWords ]; }, getOptionLabel: acronym => acronym.letters, getOptionCompletion: ( { letters, expansion } ) => ( <abbr title={ expansion }>{ letters }</abbr>, ),}; // Our filter functionfunction appendAcronymCompleter( completers, blockName ) { return blockName === 'my-plugin/foo' ? [ ...completers, acronymCompleter ] : completers;} // Adding the filterwp.hooks.addFilter( 'editor.Autocomplete.completers', 'my-plugin/autocompleters/acronym', appendAcronymCompleter);
```
