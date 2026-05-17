---
source_url: https://developer.wordpress.org/themes/advanced-topics/plugin-api-hooks/
synced: 2026-05-12
handbook: theme
chapter: advanced-topics
slug: plugin-api-hooks
parent_order: 7
page_order: 10
title: "Plugin API Hooks"
code_quality: degraded
code_issue: pre_newline_loss
---

# Plugin API Hooks

A theme should work well with WordPress plugins. Plugins add functionality by using actions and filters, which are collectively called hooks (see [Plugin API](https://codex.wordpress.org/Plugin_API "Plugin API") for more information).

Most hooks are executed internally by WordPress, so your theme does not need special tags for them to work. However, a few hooks need to be included in your theme templates. These hooks are fired by special Template Tags:

- [wp_head()](https://developer.wordpress.org/reference/functions/wp_head/ "Function Reference/wp head")
> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
- Goes at the end of the <tt>&lt;head&gt;</tt> element of a theme’s *header.php* template file.
```

- [wp_body_open()](https://developer.wordpress.org/reference/functions/wp_body_open/ "Function Reference/wp head")
> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
- Goes at the begining of the <tt>&lt;body&gt;</tt> element of a theme’s *header.php* template file.
```

- [wp_footer()](https://developer.wordpress.org/reference/functions/wp_footer/ "Function Reference/wp footer")
```text
- Goes in *footer.php*, just before the closing <tt>&lt;/body&gt;</tt> tag.
```

- [wp_meta()](https://developer.wordpress.org/reference/functions/wp_meta/ "Function Reference/wp meta")
> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
- Typically goes in the <tt>&lt;li&gt;Meta&lt;/li&gt;</tt> section of a Theme’s menu or sidebar.
```

- [comment_form()](https://developer.wordpress.org/reference/functions/comment_form/ "Function Reference/comment form")
```text
- Goes in *comments.php* directly before the file’s closing tag (<tt>&lt;/div&gt;</tt>).
```

Take a look at a core theme’s templates for examples of how these hooks are used.
