---
source_url: https://developer.wordpress.org/themes/templates/template-parts/
synced: 2026-05-12
handbook: theme
chapter: templates
slug: template-parts
parent_order: 4
page_order: 4
title: "Template Parts"
code_quality: degraded
code_issue: pre_newline_loss
---

# Template Parts

[Templates](./templates.md) represent the top-level document structure for the front end of a website. But ***template parts*** represent smaller sections of content that can be included in one or more templates.

Some common parts are:

- Header
- Footer
- Sidebar
- Comments

You can have many more parts. These are generally pieces of the design that are reused within multiple top-level templates. Parts are not a requirement for your theme, but they are a nice-to-use feature that lets you better manage your files and code.

In [Introduction to Templates](introduction-to-templates.md), you learned about the basics of template parts. In this document, you’ll gain a deeper understanding of how they work.

## How do template parts work?

As you learned in the Templates documentation, WordPress locates a top-level template based on which page a visitor is viewing on the website. This located template is then loaded, and WordPress parses the block markup before sending it back to the browser.

Unlike templates, parts are not automatically loaded based on the currently-viewed page. They must be included as a *part* of the top-level template via the [Template Part block](https://wordpress.org/documentation/article/template-part-block/).

The Template Part block’s markup looks like this:


```html
<!-- wp:template-part {"slug":"your-template-part-slug"} /-->
```

There are more block settings that you can include, but the `slug` property **must** be set to load the correct part. When WordPress encounters the Template Part block markup during its parsing process, it will look for a file named `/parts/your-template-part-slug.html` in your theme folder. If found, it will load the file and parse its block markup.

Let’s look at a simple template that loads both a Header and Footer part:


```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /--> <!-- Other block markup goes here. --> <!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

As you can see, a `tagName` setting was also included for the Header and Footer parts. This sets the wrapping container elements to `<header>` and `<footer>`, respectively.

If this is the block markup included in a top-level template, WordPress would go through these steps:

1. Load the `/parts/header.html` file and parse its block markup.
2. Parse the template’s other block markup.
3. Load the `/parts/footer.html` file and parse its block markup.

## What’s in a template part?

Template parts in block themes contain block markup and nothing else.

Let’s look at a simple Footer template part that shows a Site Title block and a Paragraph block with a “Powered by WordPress” message. To recreate this, you would add a `/parts/footer.html` file in your theme with this block markup:


```html
<!-- wp:group {"align":"wide","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} --><div class="wp-block-group alignwide"> <!-- wp:site-title {"level":0} /--> <!-- wp:paragraph --> <p>Powered by WordPress.</p> <!-- /wp:paragraph --></div><!-- /wp:group -->
```

This is merely an example that shows block markup and how it *could* look in a part. Template parts can be even simpler or much more complex, depending on what you want to include in them.

For a more in-depth look at the architecture of a block, check out the [Key Concepts](../../block-editor-handbook/04-explanations/01-architecture/key-concepts.md) documentation in the Block Editor Handbook.

## Organizing template parts

With block themes, you must put template parts within your theme’s `/parts` folder. It should be structured like this:

- `parts/`
    - `comments.html`
    - `footer.html`
    - `header.html`
    - `sidebar.html`

None of those are required. In fact, you don’t even have to include any template parts at all.  
WordPress does not currently [support nested template parts](https://github.com/WordPress/gutenberg/issues/54279). For example, you cannot create a `/parts/header` folder and put multiple header parts within it. All template parts must be placed directly within your theme’s `/parts` folder.

Technically, WordPress will also look in the `/block-template-parts` folder if it exists in your theme. This is for backward compatibility with an older version of WordPress. But it is recommended to always use the `/parts` folder instead.

## Building template parts

It’s possible to manually write the block markup code for all of your template parts. But, in most cases, you will want to work directly within the WordPress admin and its visual editor. Then, migrate the block markup from the editor to your template part files as described in [Introduction to Templates](introduction-to-templates.md).

To explore working with the visual interface, read the support guides on using the Site and Template Editors:

- [Template Part Block](https://wordpress.org/documentation/article/template-part-block/)
- [Site Editor](https://wordpress.org/documentation/article/site-editor/)
- [Template Editor](https://wordpress.org/documentation/article/template-editor/)
```php
- [How to edit templates via the Site Editor](https://wordpress.org/documentation/article/template-editor/#how-to-edit-templates-via-the-site-editor)
- [How to use the Template Editor via the WordPress Block Editor](https://wordpress.org/documentation/article/template-editor/#how-to-edit-templates-via-the-post-editor)
```

### Registering template parts

While not required, you should almost always register template parts via `theme.json`. Doing so will ensure that they appear in the user interface for use with the Site and Template editors with nice labels that can be translated.

Registering template parts is covered in the [Template Parts](../03-theme-json/06-template-parts.md) documentation under the [Global Settings and Styles](../03-theme-json/index.md) chapter.

### Editing template parts

To access templates from the WordPress admin, open the **Appearance &gt; Editor** menu in the admin menu. Then click the **Patterns** item in the sidebar and scroll to find the **Template Parts** section:

Template Parts are categorized by template part areas (read “Template part areas” section below for more information). Each area lists the parts that are registered for it (note that **General** is the `uncategorized` area).

The template parts shown can come from three locations:

- User-created template parts saved in the database (these are stored as posts in the `wp_template_part` post type)
- Template parts from the theme’s `/parts` folder
- Template parts dynamically added by plugins

From this screen, you can make any customizations you want to the parts, adjusting them to fit your vision.

Remember that if you save the parts from this screen, they will be stored in the database and will overrule any templates in your theme. If you plan to distribute this theme to others or use it on another site, you must copy the block markup to the matching template in your `/parts` folder as described in [Introduction to Templates](introduction-to-templates.md).

### Adding new template parts

You can create a new template by clicking the **+** icon next to **Patterns** heading. This will display a dropdown with several options. Click the **Create template part** option as shown here:

Then a popup modal will appear for you to enter a custom template part name and select its area:

By default, you can select from the General, Header, and Footer areas (to learn more about creating custom areas, read the “Template part areas” section below).

From the next screen, you will be able to create an entirely custom template part. It can include any blocks that you prefer.

Again, any new parts you add via the editor are saved in the database. You must create the template part file inside your `/parts` folder and copy the block markup to it if you intend to distribute your theme.

## Template part areas

Template part areas are essentially a way to organize similar template parts. They also appear as navigational elements within the user interface. Below, you can see the **Header** area highlighted in the template-editing sidebar:

By default, WordPress has three areas that you can register your templates for:

- `uncategorized` (labeled as **General** in the admin)
- `header`
- `footer`

That will cover some common use cases (almost all themes need a header and footer, for example). But you may want to create custom areas for your themes to better organize your template parts and provide a nicer user experience.

### Registering custom areas

You can register as many custom areas you want by adding a filter to the [`default_wp_template_part_areas` hook](https://developer.wordpress.org/reference/hooks/default_wp_template_part_areas/). Your callback function accepts a single parameter of `$areas`, which must be an array of area definitions. Each area definition must be an array with these key/value pairs defined:

- **`area`:** The machine-readable slug for your template part area.
- **`area_tag`:** The wrapping HTML tag to use for template parts assigned to this area. Can be one of the following:
    - `div`
    - `article`
    - `aside`
    - `footer`
    - `header`
    - `main`
    - `section`
- **`label`:** A human-readable label for your area, which may be translated.
- **`description`:** A description of your area and what template parts belong to it, which may be translated.
- **`icon`:** The icon to use for the area. Note that only `header`, `footer`, and `sidebar` are currently supported with everything else falling back to a default icon, at least until [this ticket is addressed](https://github.com/WordPress/gutenberg/issues/36814).

Suppose you wanted to create an area named Loop to assign template parts used throughout your theme. You could do so by adding this code to your theme’s `functions.php` file:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
add_filter( 'default_wp_template_part_areas', 'themeslug_template_part_areas' ); function themeslug_template_part_areas( array $areas ) { $areas[] = array( 'area' => 'loop', 'area_tag' => 'section', 'label' => __( 'Loop', 'themeslug' ), 'description' => __( 'Custom description', 'themslug' ), 'icon' => 'layout' ); return $areas;}
```

This would register a new Loop area for your theme, but for it to be useful, you need to also register at least one template part for it as described in the [`theme.json` documentation on registering template parts](../03-theme-json/06-template-parts.md).

Suppose you also created a `/parts/loop-default.html` template part. You could assign it to your new `loop` area in `theme.json` with this code:


```json
{ "version": 2, "templateParts": [ { "area": "loop", "name": "loop-default", "title": "Loop - Default" } ]}
```

This screenshot shows what the **Loop** area would look like in the Site Editor:

You can register as many template parts for an area as you need via `theme.json`. For example, you could register a `loop-home.html` and `loop-author.html` to use in your Home and Author templates, respectively. But these are mere examples. The only limit is your imagination.

There are many reasons you might want to register custom areas. For a deeper dive into the benefits and features of this system, read [Upgrading the site-editing experience with custom template part areas](https://developer.wordpress.org/news/2023/06/upgrading-the-site-editing-experience-with-custom-template-part-areas/) from the WordPress Developer Blog.
