---
source_url: https://developer.wordpress.org/apis/settings/
synced: 2026-05-12
handbook: common-apis
chapter: settings
slug: settings
parent_order: 17
page_order: 0
title: "Settings"
code_quality: degraded
code_issue: pre_newline_loss
---

# Settings

## Overview

The **Settings API**, added in [WordPress 2.7](https://developer.wordpress.org/support/wordpress-version/version-2-7), allows admin pages containing settings forms to be managed semi-automatically. It lets you define settings pages, sections within those pages and fields within the sections.

New settings pages can be registered along with sections and fields inside them. Existing settings pages can also be added to by registering new settings sections or fields inside of them.

Organizing registration and validation of fields still requires some effort from developers using the Settings API, but avoids a lot of complex debugging of underlying options management.

NOTE: When using the Settings API, the form posts to `wp-admin/options.php` which provides fairly strict capabilities checking. Users will need `manage_options` capability (and in MultiSite will have to be a Super Admin) to submit the form.

The functions are found in `wp-admin/includes/plugin.php` and `wp-admin/includes/template.php`

## Function Reference

**Setting Register/Unregister:**

- [register_setting()](https://developer.wordpress.org/reference/functions/register_setting/)
- [unregister_setting()](https://developer.wordpress.org/reference/functions/unregister_setting/)

**Add Field/Section:**

- [add_settings_field()](https://developer.wordpress.org/reference/functions/add_settings_field/)
- [add_settings_section()](https://developer.wordpress.org/reference/functions/add_settings_section/)

**Options Form Rendering:**

- [settings_fields()](https://developer.wordpress.org/reference/functions/settings_fields/)
- [do_settings_sections()](https://developer.wordpress.org/reference/functions/do_settings_sections/)
- [do_settings_fields()](https://developer.wordpress.org/reference/functions/do_settings_fields/)

**Errors:**

- [add_settings_error()](https://developer.wordpress.org/reference/functions/add_settings_error/)
- [get_settings_errors()](https://developer.wordpress.org/reference/functions/get_settings_errors/)
- [settings_errors()](https://developer.wordpress.org/reference/functions/settings_errors/)

## Adding Setting Fields

You can add new settings fields (basically, an option in the `wp_options` database table but totally managed for you) to the existing WordPress pages using this function. Your callback function just needs to output the appropriate HTML input and fill it with the old value, the saving will be done behind the scenes. You can create your own sections on existing pages using `add_settings_section()` as described below.

**NOTE:** You MUST register any options you use with `add_settings_field()` or they won’t be saved and updated automatically. See below for details and an example.


```php
add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() )
```

- `$id` – String for use in the ‘id’ attribute of tags.
- `$title` – Title of the field.
- `$callback` – Function that fills the field with the desired inputs as part of the larger form. Name and id of the input should match the $id given to this function. The function should echo its output.
- `$page` – The type of settings page on which to show the field (general, reading, writing, …).
- `$section` – The section of the settings page in which to show the box (default or a section you added with add\_settings\_section, look at the page in the source to see what the existing ones are.)
- `$args` – Extra arguments passed into the callback function

## Adding Settings Sections

Settings Sections are the groups of settings you see on WordPress settings pages with a shared heading. In your plugin you can add new sections to existing settings pages rather than creating a whole new page. This makes your plugin simpler to maintain and creates fewer new pages for users to learn. You just tell them to change your setting on the relevant existing page.


```php
add_settings_section( $id, $title, $callback, $page );
```

- `$id` – String for use in the ‘id’ attribute of tags.
- `$title` – Title of the section.
- `$callback` – Function that fills the section with the desired content. The function should echo its output.
- `$page` – The type of settings page on which to show the section (general, reading, writing, media etc.)

## Registering Settings


```php
register_setting( $option_group, $option_name, $args );


unregister_setting( $option_group, $option_name );
```

**NOTE:** `register_setting()` as well as the above mentioned `add_settings_*()` functions should all be called from a `admin_init` action hook callback function. Refer to the “Examples” section below.

## Options Form Rendering

When using the API to add settings to existing options pages, you do not need to be concerned about the form itself, as it has already been defined for the page. When you define a new page from scratch, you need to output a minimal form structure that contains a few tags that in turn output the actual sections and settings for the page.

To display the hidden fields and handle security of your options form, the Settings API provides the [settings_fields()](https://developer.wordpress.org/reference/functions/settings_fields/) function. `settings_fields( $option_group );`

`$option_group`** (*****string*****) (*****required*****):**

A settings group name. This must match the group name used in [register_setting()](https://developer.wordpress.org/reference/functions/register_setting/), which is the page slug name on which the form is to appear. Default: *None*

To display the sections assigned to the page and the settings contained within, the Settings API provides the [do_settings_sections()](https://developer.wordpress.org/reference/functions/do_settings_sections/) function. `do_settings_sections( $page );`

`$page`** (*****string*****) (*****required*****):**

The slug name of the page whose settings sections you want to output. This should match the page name used in [add_settings_section()](https://developer.wordpress.org/reference/functions/add_settings_section/). Default: *None*

The [do_settings_fields()](https://developer.wordpress.org/reference/functions/do_settings_fields/) function is provided to output the fields assigned to a particular page and section. You should not call this function directly, rather use `do_settings_sections()` to output the Section content as well as the associated fields.

Your options form also needs a submit button. You can use the [submit_button()](https://developer.wordpress.org/reference/functions/submit_button/) function to do this.

Finally, you need to output the HTML &lt;form&gt; tag defining the action destination of options.php and method of POST. Here is an example options form code to generate all the sections and fields added to a page who’s slug name is `my-page`:


```php
<form method="POST" action="options.php"><?php settings_fields( 'my-page' ); // pass slug name of page, also referred to in Settings API as option group namedo_settings_sections( 'my-page' ); // pass slug name of pagesubmit_button(); // submit button?></form>
```

## Example

### Adding a settings section with a new field in it


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
<?php /** * Add all your sections, fields and settings during admin_init */ function wporg_settings_api_init() { // Add the section to reading settings so we can add our // fields to it add_settings_section( 'wporg_setting_section', 'Example settings section in reading', 'wporg_setting_section_callback_function', 'reading' ); // Add the field with the names and function to use for our new // settings, put it in our new section add_settings_field( 'wporg_setting_name', 'Example setting Name', 'wporg_setting_callback_function', 'reading', 'wporg_setting_section' ); // Register our setting so that $_POST handling is done for us and // our callback function just has to echo the <input> register_setting( 'reading', 'wporg_setting_name' ); } // wporg_settings_api_init() add_action( 'admin_init', 'wporg_settings_api_init' ); /** * Settings section callback function * * This function is needed if we added a new section. This function * will be run at the start of our section */ function wporg_setting_section_callback_function() { echo '<p>Intro text for our settings section</p>'; } /* * Callback function for our example setting * * creates a checkbox true/false option. Other types are surely possible */ function wporg_setting_callback_function() { echo '<input name="wporg_setting_name" id="wporg_setting_name" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'wporg_setting_name' ), false ) . ' /> <label for="wporg_setting_name">Explanation text</label>'; }
```

#### Graphical Representation of where all those code should go

## External References

- [The WordPress Settings API](http://kovshenin.com/2012/the-wordpress-settings-api/) by Konstantin Kovshenin, Oct 23 2012
- [Incorporating the Settings API in WordPress Themes](http://www.chipbennett.net/2011/02/17/incorporating-the-settings-api-in-wordpress-themes/) by Chip Bennett, Feb 2011
- [Settings API Explained](http://www.presscoders.com/wordpress-settings-api-explained/) by David Gwyer
- [WordPress Settings API Tutorial](http://ottopress.com/2009/wordpress-settings-api-tutorial/) by Otto
- [Handling Plugin Options with register_setting()](http://planetozh.com/blog/2009/05/handling-plugins-options-in-wordpress-28-with-register_setting/) by Ozh
- [Intro to the WordPress Settings API](http://blog.gneu.org/2010/09/intro-to-the-wordpress-settings-api/) by BobGneu
- Using The Settings API: [Part 1](http://wp.tutsplus.com/tutorials/using-the-settings-api-part-1-create-a-theme-options-page/), [Part 2](http://wp.tutsplus.com/tutorials/using-the-settings-api-part-2-create-a-top-level-admin-menu/) by [Sarah Neuber](https://twitter.com/srhnbr/)
- [Class Based Settings with WordPress](https://www.yaconiello.com/blog/how-to-handle-wordpress-settings) by Francis Yaconiello
- [Adding multiple sections on a single settings screen](http://www.mendoweb.be/blog/wordpress-settings-api-multiple-sections-on-same-page/) by Mathieu Decaffmeyer
- [Adding multiple forms on a single settings screen](http://www.mendoweb.be/blog/wordpress-settings-api-multiple-forms-on-same-page/) by Mathieu Decaffmeyer
- [The Complete Guide To The WordPress Settings API](http://wp.tutsplus.com/tutorials/the-complete-guide-to-the-wordpress-settings-api-part-1/) by Tom McFarlin, Jan 31st 2012
- [WordPress Settings API Cheat Sheet](http://techblog.kjodle.net/2015/07/16/wordpress-settings-api-cheat-sheet/) by Kenneth Odle, July 16th 2015

## Generators

- [WordPress Settings API (options page) Generator](http://wpsettingsapi.jeroensormani.com/)

## PHP Class

- [WordPress settings API Class](https://github.com/tareq1988/wordpress-settings-api-class/)
