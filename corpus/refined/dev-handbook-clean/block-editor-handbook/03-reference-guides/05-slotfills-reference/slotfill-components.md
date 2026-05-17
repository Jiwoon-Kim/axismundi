---
source_url: https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-block-settings-menu-item/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: slotfills-reference
slug: slotfill-components
parent_order: 3
sub_order: 5
page_order: 2
title: "PluginBlockSettingsMenuItem"
code_quality: degraded
code_issue: pre_newline_loss
---

# PluginBlockSettingsMenuItem

This slot allows for adding a new item into the More Options area.  
This will either appear in the controls for each block or at the Top Toolbar depending on the users setting.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { registerPlugin } from '@wordpress/plugins';import { PluginBlockSettingsMenuItem } from '@wordpress/editor'; const PluginBlockSettingsMenuGroupTest = () => ( <PluginBlockSettingsMenuItem allowedBlocks={ [ 'core/paragraph' ] } icon="smiley" label="Menu item text" onClick={ () => { alert( 'clicked' ); } } />); registerPlugin( 'block-settings-menu-group-test', { render: PluginBlockSettingsMenuGroupTest,} );
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-document-setting-panel/
# PluginDocumentSettingPanel

This SlotFill allows registering a UI to edit Document settings.

## Available Props

- **name** `string`: A string identifying the panel.
- **className** `string`: An optional class name added to the sidebar body.
- **title** `string`: Title displayed at the top of the sidebar.
- **icon** `(string|Element)`: The [Dashicon](https://developer.wordpress.org/resource/dashicons/) icon slug string, or an SVG WP element.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { registerPlugin } from '@wordpress/plugins';import { PluginDocumentSettingPanel } from '@wordpress/editor'; const PluginDocumentSettingPanelDemo = () => ( <PluginDocumentSettingPanel name="custom-panel" title="Custom Panel" className="custom-panel" > Custom Panel Contents </PluginDocumentSettingPanel>); registerPlugin( 'plugin-document-setting-panel-demo', { render: PluginDocumentSettingPanelDemo, icon: 'palmtree',} );
```

## Accessing a panel programmatically

Core and custom panels can be accessed programmatically using their panel name. The core panel names are:

- Summary Panel: `post-status`
- Categories Panel: `taxonomy-panel-category`
- Tags Panel: `taxonomy-panel-post_tag`
- Featured Image Panel: `featured-image`
- Excerpt Panel: `post-excerpt`
- DiscussionPanel: `discussion-panel`

Custom panels are namespaced with the plugin name that was passed to `registerPlugin`.  
In order to access the panels using function such as `toggleEditorPanelOpened` or `toggleEditorPanelEnabled` be sure to prepend the namespace.

To programmatically toggle panels, use the following:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useDispatch } from '@wordpress/data';import { store as editorStore } from '@wordpress/editor'; const Example = () => { const { toggleEditorPanelOpened } = useDispatch( editorStore ); return ( <Button variant="primary" onClick={ () => { // Toggle the Summary panel toggleEditorPanelOpened( 'post-status' ); // Toggle the Custom Panel introduced in the example above. toggleEditorPanelOpened( 'plugin-document-setting-panel-demo/custom-panel' ); } } > Toggle Panels </Button> );};
```

It is also possible to remove panels from the admin using the `removeEditorPanel` function by passing the name of the registered panel.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useDispatch } from '@wordpress/data';import { store as editorStore } from '@wordpress/editor'; const Example = () => { const { removeEditorPanel } = useDispatch( editorStore ); return ( <Button variant="primary" onClick={ () => { // Remove the Featured Image panel. removeEditorPanel( 'featured-image' ); // Remove the Custom Panel introduced in the example above. removeEditorPanel( 'plugin-document-setting-panel-demo/custom-panel' ); } } > Toggle Panels </Button> );};
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-more-menu-item/
# PluginMoreMenuItem

This slot will add a new item to the More Tools & Options section.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { registerPlugin } from '@wordpress/plugins';import { PluginMoreMenuItem } from '@wordpress/editor';import { image } from '@wordpress/icons'; const MyButtonMoreMenuItemTest = () => ( <PluginMoreMenuItem icon={ image } onClick={ () => { alert( 'Button Clicked' ); } } > More Menu Item </PluginMoreMenuItem>); registerPlugin( 'more-menu-item-test', { render: MyButtonMoreMenuItemTest } );
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-post-publish-panel/
# PluginPostPublishPanel

This slot allows for injecting items into the bottom of the post-publish panel that appears after a post is published.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { registerPlugin } from '@wordpress/plugins';import { PluginPostPublishPanel } from '@wordpress/editor'; const PluginPostPublishPanelTest = () => ( <PluginPostPublishPanel> <p>Post Publish Panel</p> </PluginPostPublishPanel>); registerPlugin( 'post-publish-panel-test', { render: PluginPostPublishPanelTest,} );
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-post-status-info/
# PluginPostStatusInfo

This slots allows for the insertion of items in the Summary panel of the document sidebar.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { registerPlugin } from '@wordpress/plugins';import { PluginPostStatusInfo } from '@wordpress/editor'; const PluginPostStatusInfoTest = () => ( <PluginPostStatusInfo> <p>Post Status Info SlotFill</p> </PluginPostStatusInfo>); registerPlugin( 'post-status-info-test', { render: PluginPostStatusInfoTest } );
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-pre-publish-panel/
# PluginPrePublishPanel

This slot allows for injecting items into the bottom of the pre-publish panel that appears to confirm publishing after the user clicks “Publish”.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { registerPlugin } from '@wordpress/plugins';import { PluginPrePublishPanel } from '@wordpress/editor'; const PluginPrePublishPanelTest = () => ( <PluginPrePublishPanel> <p>Pre Publish Panel</p> </PluginPrePublishPanel>); registerPlugin( 'pre-publish-panel-test', { render: PluginPrePublishPanelTest,} );
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-sidebar/
# PluginSidebar

This slot allows adding items to the tool bar of either the Post or Site editor screens.  
Using this slot will add an icon to the toolbar that, when clicked, opens a panel with containing the items wrapped in the `<PluginSidebar />` component.  
Additionally, it will also create a `<PluginSidebarMoreMenuItem />` that will allow opening the panel from Options panel when clicked.

## Example

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { PluginSidebar } from '@wordpress/editor';import { PanelBody, Button, TextControl, SelectControl,} from '@wordpress/components';import { registerPlugin } from '@wordpress/plugins';import { useState } from '@wordpress/element'; const PluginSidebarExample = () => { const [ text, setText ] = useState( '' ); const [ select, setSelect ] = useState( 'a' ); return ( <PluginSidebar name="plugin-sidebar-example" title={ __( 'My PluginSidebar' ) } icon={ 'smiley' } > <PanelBody> <h2> { __( 'This is a heading for the PluginSidebar example.' ) } </h2> <p> { __( 'This is some example text for the PluginSidebar example.' ) } </p> <TextControl __next40pxDefaultSize label={ __( 'Text Control' ) } value={ text } onChange={ ( newText ) => setText( newText ) } /> <SelectControl label={ __( 'Select Control' ) } value={ select } options={ [ { value: 'a', label: 'Option A' }, { value: 'b', label: 'Option B' }, { value: 'c', label: 'Option C' }, ] } onChange={ ( newSelect ) => setSelect( newSelect ) } /> <Button variant="primary">{ __( 'Primary Button' ) } </Button> </PanelBody> </PluginSidebar> );}; // Register the plugin.registerPlugin( 'plugin-sidebar-example', { render: PluginSidebarExample } );
```

---

https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-sidebar-more-menu-item/
# PluginSidebarMoreMenuItem

This slot is used to allow the opening of a `<PluginSidebar />` panel from the Options dropdown.  
When a `<PluginSidebar />` is registered, a `<PluginSidebarMoreMenuItem />` is automatically registered using the title prop from the `<PluginSidebar />` and so it’s not required to use this slot to create the menu item.

## Example

This example shows how customize the text for the menu item instead of using the default text provided by the `<PluginSidebar />` title.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';import { PanelBody, Button, TextControl, SelectControl,} from '@wordpress/components';import { registerPlugin } from '@wordpress/plugins';import { useState } from '@wordpress/element';import { image } from '@wordpress/icons'; const PluginSidebarMoreMenuItemTest = () => { const [ text, setText ] = useState( '' ); const [ select, setSelect ] = useState( 'a' ); return ( <> <PluginSidebarMoreMenuItem target="sidebar-name" icon={ image }> { __( 'Custom Menu Item Text' ) } </PluginSidebarMoreMenuItem> <PluginSidebar name="sidebar-name" icon={ image } title="My Sidebar" > <PanelBody> <h2> { __( 'This is a heading for the PluginSidebar example.' ) } </h2> <p> { __( 'This is some example text for the PluginSidebar example.' ) } </p> <TextControl __next40pxDefaultSize label={ __( 'Text Control' ) } value={ text } onChange={ ( newText ) => setText( newText ) } /> <SelectControl label={ __( 'Select Control' ) } value={ select } options={ [ { value: 'a', label: __( 'Option A' ) }, { value: 'b', label: __( 'Option B' ) }, { value: 'c', label: __( 'Option C' ) }, ] } onChange={ ( newSelect ) => setSelect( newSelect ) } /> <Button variant="primary"> { __( 'Primary Button' ) }{ ' ' } </Button> </PanelBody> </PluginSidebar> </> );}; registerPlugin( 'plugin-sidebar-more-menu-item-example', { render: PluginSidebarMoreMenuItemTest,} );
```
