---
source_url: https://developer.wordpress.org/block-editor/how-to-guides/curating-the-editor-experience/
synced: 2026-05-12
handbook: block-editor
chapter: how-to-guides
sub_chapter: curating-editor-experience
slug: index
parent_order: 2
sub_order: 4
page_order: 1004
title: "Curating the Editor Experience"
---

# Curating the Editor Experience

Curating the editing experience in WordPress is important because it allows you to streamline the editing process, ensuring consistency and alignment with the site’s style and branding guidelines. It also makes it easier for users to create and manage content effectively without accidental modifications or layout changes. This leads to a more efficient and personalized experience.

The purpose of this guide is to offer various ways you can lock down and curate the experience of using WordPress, especially with the introduction of more design tools and the Site Editor.

In this section, you will learn:

1. [**Block locking**](block-locking-api.md): how to restrict user interactions with specific blocks in the Editor for better content control
2. [**Patterns**](patterns.md): about creating and implementing predefined block layouts to ensure design and content uniformity
3. [**theme.json**](theme-json.md): to configure global styles and settings for your theme using the theme.json file
4. [**Filters and hooks**](filters-and-hooks.md): about the essential filters and hooks used to modify the Editor
5. [**Disabling Editor functionality**](disable-editor-functionality.md): about additional ways to selectively disable features or components in the Editor to streamline the user experience

## Combining approaches

Remember that the approaches provided in the documentation above can be combined as you see fit. For example, you can provide custom patterns to use when creating a new page while also limiting the amount of customization that can be done to aspects of them, like only allowing specific preset colors to be used for the background of a Cover block or locking down what blocks can be deleted.

When considering the approaches to take, think about the specific ways you might want to both open up the experience and curate it.
