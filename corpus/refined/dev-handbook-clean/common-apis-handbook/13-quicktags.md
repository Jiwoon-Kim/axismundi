---
source_url: https://developer.wordpress.org/apis/quicktags/
synced: 2026-05-12
handbook: common-apis
chapter: quicktags
slug: quicktags
parent_order: 13
page_order: 0
title: "Quicktags"
code_quality: degraded
code_issue: pre_newline_loss
---

# Quicktags

## Description

The Quicktags API allows you to include additional buttons in the Text (HTML) mode of the WordPress Classic editor.

## History

This API was introduced in [WordPress 3.3](https://developer.wordpress.org/support/wordpress-version/version-3-3).

## Usage


```text
QTags.addButton( id, display, arg1, arg2, access_key, title, priority, instance, object );
```

## Parameters

- `id`** (*****string*****) (*****required*****):** The html id for the button. Default: *None*
- `display`** (*****string*****) (*****required*****):** The html value for the button. Default: *None*
- `arg1`** (*****string*****) (*****required*****): **Either a starting tag to be inserted like “&lt;span&gt;” or a callback that is executed when the button is clicked. Default: *None*
- `arg2`** (*****string*****) (*****optional*****):** Ending tag like “&lt;/span&gt;”. Leave empty if tag doesn’t need to be closed (i.e. “&lt;hr /&gt;”). Default: *None*
- `access_key`** (*****string*****) (*****optional*****):** **Deprecated and Not used.** Shortcut access key for the button. Default: *None*
- `title`** (*****string*****) (*****optional*****):** The html title value for the button. Default: *None*
- `priority`** (*****int*****) (*****optional*****):** A number representing the desired position of the button in the toolbar. 1 – 9 = first, 11 – 19 = second, 21 – 29 = third, etc. Default: *None*
- `instance`** (*****string*****) (*****optional*****):** Limit the button to a specific instance of Quicktags, add to all instances if not present. Default: *None*
- `object`** (*****attr*****) (*****optional*****):** Used to pass additional attributes. Currently supports `ariaLabel` and `ariaLabelClose` (for “close tag” state)

## Return Values

(*mixed*) Null or the button object that is needed for back-compat.

## Examples

Below examples would add HTML buttons to the default Quicktags in the Text editor.

### Modern example

This example uses the inline JS API to add the JavaScript when quicktags are enqueued.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
/** * Add a paragraph tag button to the quicktags toolbar * * @return void */function wporg_add_quicktag_paragraph() { wp_add_inline_script( 'quicktags', "QTags.addButton( 'eg_paragraph_v2', 'p_v2', '<p>', '</p>', '', 'Paragraph tag v2', 2, '', { ariaLabel: 'Paragraph', ariaLabelClose: 'Close Paragraph tag' });" );}add_action( 'admin_enqueue_scripts', 'wporg_add_quicktag_paragraph' );
```

### Another modern example

In this example,

1. Enqueue a script using the proper WordPress function [`wp_enqueue_script`](https://developer.wordpress.org/reference/functions/wp_enqueue_script).
2. Call any JavaScript that you want to fire when or after the QuickTag was clicked inside the QuickTag call-back.

#### Enqueue the script

Put below codes into active theme’s `functions.php`.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
function enqueue_quicktag_script(){ wp_enqueue_script( 'your-handle', get_template_directory_uri() . '/editor-script.js', array( 'jquery', 'quicktags' ), '1.0.0', true );}add_action( 'admin_enqueue_scripts', 'enqueue_quicktag_script' );
```

#### The JavaScript itself

Create new file `editor-script` and save under the active theme directory.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
QTags.addButton( 'eg_paragraph_v3', 'p_v3', my_callback, '', '', 'Prompted Paragraph tag', 3, '', { ariaLabel: 'Prompted Paragraph' } ); function my_callback(){ var my_stuff = prompt( 'Enter Some Stuff:', '' ); if ( my_stuff ) { QTags.insertContent( '<p>' + my_stuff + '</p>' ); }}
```

### Traditional example

This example manually add hardcoded JavaScript with `wp_script_is` on the admin footer hook. You should consider to use modern example. See above.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
/** * Add more buttons to the quicktags HTML editor * * @return void */function wporg_traditional_add_quicktags() { if ( ! wp_script_is( 'quicktags' ) ) { return; } ?> <script type="text/javascript"> QTags.addButton( 'eg_paragraph', 'p', '<p>', '</p>', '', 'Paragraph tag', 1, '', { ariaLabel: 'Paragraph', ariaLabelClose: 'Close Paragraph tag' } ); QTags.addButton( 'eg_hr', 'hr', '<hr />', '', '', 'Horizontal rule line', 201, '', { ariaLabel: 'Horizontal' } ); QTags.addButton( 'eg_pre', 'pre', '<pre lang="php">', '</pre>', '', 'Preformatted text tag', 111, '', { ariaLabel: 'Pre', ariaLabelClose: 'Close Pre tag' } ); </script> <?php} add_action( 'admin_print_footer_scripts', 'wporg_traditional_add_quicktags', 11 );
```

Note:

- To avoid a Reference Error we check to see whether or not the ‘quicktags’ script is in use.
- Since WordPress 6.0, the script loading order was changed and the error “QTags is not defined” occurs without 3rd parameter of `add_action()`. Also, you have to specfy the larger number than 10 (ex.11).

The “p” button HTML would be:


```html
<input type="button" id="qt_content_eg_paragraph" class="ed_button button button-small" title="Paragraph tag" aria-label="Paragraph" value="p">
```

The ID value for each button is automatically prepended with the string qt\_content\_.

Here is a dump of the docblock from `quicktags.js`, it’s pretty useful on it’s own.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
/** * Main API function for adding a button to Quicktags * * Adds qt.Button or qt.TagButton depending on the args. The first three args are always required. * To be able to add button(s) to Quicktags, your script should be enqueued as dependent * on "quicktags" and outputted in the footer. If you are echoing JS directly from PHP, * use add_action( 'admin_print_footer_scripts', 'output_my_js', 100 ) or add_action( 'wp_footer', 'output_my_js', 100 ) * * Minimum required to add a button that calls an external function: * QTags.addButton( 'my_id', 'my button', my_callback ); * function my_callback() { alert('yeah!'); } * * Minimum required to add a button that inserts a tag: * QTags.addButton( 'my_id', 'my button', '<span>', '</span>' ); * QTags.addButton( 'my_id2', 'my button', '<br />' ); */
```

## Default Quicktags

Here are the values of the default Quicktags added by WordPress to the Text editor. ID must be unique. When adding your own buttons, do not use these values:

| **ID** | **Value** | **Tag Start** | **Tag End** |
| --- | --- | --- | --- |
| link | link | &lt;a href=”‘ + URL + ‘”&gt; | &lt;/a&gt; |
| strong | b | &lt;strong&gt; | &lt;/strong&gt; |
| code | code | &lt;code&gt; | &lt;/code&gt; |
| del | del | &lt;del datetime=”‘ + \_datetime + ‘”&gt; | &lt;/del&gt; |
| fullscreen | fullscreen |  |  |
| em | i | &lt;em&gt; | &lt;/em&gt; |
| li | li | t&lt;li&gt; | &lt;/li&gt;n |
| img | img | &lt;img src=”‘ + src + ‘” alt=”‘ + alt + ‘” /&gt; |  |
| ol | ol | &lt;ol&gt;n | &lt;/ol&gt;nn |
| block | b-quote | nn&lt;blockquote&gt; | &lt;/blockquote&gt;nn |
| ins | ins | &lt;ins datetime=”‘ + \_datetime + ‘”&gt; | &lt;/ins&gt; |
| more | more | &lt;!–more–&gt; |  |
| ul | ul | &lt;ul&gt;n | &lt;/ul&gt;nn |
| spell | lookup |  |  |
| close | close |  |

Some tag values above use variables, such as URL and `_datetime`, passed from functions.

## Source File

qt.addButton() source is located in `js/_enqueues/lib/quicktags.js`, during build it’s output in `wp-incudes/js/quicktags.js` and `wp-includes/js/quicktags.min.js`.
