---
source_url: https://developer.wordpress.org/themes/global-settings-and-styles/patterns/
synced: 2026-05-12
handbook: theme
chapter: theme-json
slug: patterns
parent_order: 3
page_order: 5
title: "Patterns"
---

# Patterns

The `patterns` property in `theme.json` lets you bundle patterns from the WordPress [Pattern Directory](https://wordpress.org/patterns/) with your theme. This is a neat system that lets you provide a wide variety of patterns that you’ve personally selected without having to design and build them yourself. Any pattern in the directory is available to you.

And if you’re feeling adventurous, you can even submit your custom-designed patterns to the directory. This will let you both bundle them with your theme and let other theme creators and users use your patterns, even when your theme is not installed.

In this document, you will learn how to include directory patterns for your theme’s users with just a few lines of code in `theme.json`.

## Adding patterns from the directory

`patterns` is an optional property that lets you bundle as many or as few patterns as you’d like with your theme. The property accepts an array of pattern slugs, and as long as those patterns exist in the Patterns Directory, they will appear in the **Patterns** inserter in the WordPress editors.

Here is a look at the `patterns` property in the default `theme.json`:


```json
{ "version": 2, "patterns": []}
```

Let’s take a look at one of the patterns from the Pattern Directory: [Hero banner with overlap images](https://wordpress.org/patterns/pattern/hero-banner-with-overlap-images/). To find the slug for the pattern, you need to look in the address bar of your browser, which should give you this URL:


```text
https://wordpress.org/patterns/pattern/hero-banner-with-overlap-images/
```

The slug is the part of the URL that comes after `https://wordpress.org/patterns/pattern/`. In this case, the slug is `hero-banner-with-overlap-images` (note that the final slash is not included).

To include this pattern with your theme, you need to pass only the slug to the `patterns` array in `theme.json`:


```json
{ "version": 2, "patterns": [ "hero-banner-with-overlap-images" ]}
```

Now that you’ve got the basics down, pick out a couple of other patterns and add them to your `patterns` array in `theme.json`:


```json
{ "version": 2, "patterns": [ "fullscreen-cover-image-gallery", "hero-banner-with-overlap-images", "mixed-shape-gallery" ]}
```

Now you should see your chosen patterns in the **Patterns** inserter in the UI:

The patterns you include will automatically appear under the categories they are assigned to in the Pattern Directory. These are mapped to the existing patterns registered within WordPress. The patterns from the above example code all have the `gallery` pattern category, so they appear under the **Patterns &gt; Gallery** tab in the inserter.
