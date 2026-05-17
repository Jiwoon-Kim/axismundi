---
source_url: https://developer.wordpress.org/themes/getting-started/what-is-a-theme/
synced: 2026-05-12
handbook: theme
chapter: getting-started
slug: what-is-a-theme
parent_order: 1
page_order: 1
title: "What Is a Theme?"
---

# What Is a Theme?

A WordPress theme represents the design of your website. It can control everything from colors, to fonts, to the entire layout. In essence, what you see when viewing the front-end of your site is shaped by the theme.

There are 1,000s of free WordPress themes in the [WordPress.org Theme Directory](https://wordpress.org/themes/) and even more from third-party directories and shops. Many people and businesses also have bespoke (custom-made) themes for their sites.

## What can themes do?

Themes take the content stored by WordPress and display it in the browser. When you create a WordPress theme, you decide how that content looks and is displayed. There are many options available to you when building your theme. The biggest limit is your imagination. 

As a theme creator, you can:

- Create different layouts, such as one, two or more columns.
- Control the typography of the site with custom font choices.
- Skin the site with any color scheme you want.
- Put a sidebar on the left or right side of the page. Or, have no sidebar at all.
- Display featured images alongside posts.

The WordPress theming system is incredibly powerful. As with every web design project, a good theme is more than defining a layout or two and a few custom colors. The best themes improve engagement with a website’s content *in addition* to being beautiful.

There really are not many limits to the possibilities. Outside of your imagination, theme creation requires some baseline knowledge, which is covered in the [Reading this handbook](reading-this-handbook.md) page of this chapter. That’s what this handbook is all about—*teaching you what you need to know to build themes of your own*.

## Theme types

WordPress supports two primary types of themes: **block** and **classic**.

There is also a classic subtype that is called a **hybrid** theme, and you’ll learn about it below, too. But the most important distinction is block vs. classic.

Technically, you can even build your own theming system altogether. That’s outside the scope of this handbook, but it’s at least worth noting that WordPress lets you build pretty much whatever you set your mind to.

### Block themes

Block themes are the modern method of building WordPress themes. They generally follow a standard set of conventions and are built entirely out of blocks. This handbook will primarily focus on building themes using this method because it is the future of the WordPress project.

Block themes rely on HTML-based [block templates](../04-templates/index.md) that contain block markup. Both creators and users can edit the templates in the Site Editor. Users can also customize [global settings and styles](../03-theme-json/index.md) defined by the theme’s `theme.json` file through the Styles interface. 

It’s also possible to export a theme directly from the Site Editor without touching any code. Technically, you cannot create a new theme from scratch entirely from the editor, but you can modify the templates and styles of an existing theme—in essence, creating a custom theme of your own.

## Become familiar with themes

To build a WordPress theme of your own, you should familiarize yourself with how themes work from a user’s viewpoint. Before diving into the creation process, try [installing a theme](https://wordpress.org/documentation/article/work-with-themes/) and playing around with it.

WordPress comes with several default themes, titled *Twenty [Year]*, but you should also try other themes from the [Theme Directory](https://wordpress.org/themes/) just to get a feel for the possibilities.

## What are themes made of?

Themes can include many different folders and file types. The list below is non-exhaustive, but it includes some of common things you might see:

- Templates (`.html` in block themes and `.php` in classic themes)
- CSS Stylesheets
- JavaScript
- PHP
- Media (images, audio, video, etc.)
- JSON

You will learn more about the specific folders and files used to create a theme in the next chapter: [Core Concepts](../02-core-concepts/index.md).

## What is the difference between themes and plugins?

It is common for there to be overlap between features found in themes and plugins. However, best practices are:

- Themes control the *presentation* of content.
- Plugins control the behaviors and features of your site.

Any theme that you create should not add site-critical functionality. Doing so means that a user loses access to that functionality when they change their theme.

For example, say you build a theme with a portfolio feature. Users who build their portfolio with your feature will lose it when they change themes. By leaving critical features to plugins, you make it possible to change the design of a website while its features remain intact.

Remember, some users switch themes often. It is best practice to make sure any functionality their sites require, even if the design changes, is in a separate plugin.
