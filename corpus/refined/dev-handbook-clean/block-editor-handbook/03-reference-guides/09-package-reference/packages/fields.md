---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-fields/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: fields
parent_order: 3
sub_order: 9
page_order: 55
title: "@wordpress/fields"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/fields

This package provides core elements for the DataView library, designed to simplify the creation and management of data display elements in WordPress.

## Installation

Install the module

```bash
npm install @wordpress/fields --save
```

## Usage

### authorField

Author field for BasePost.

### BasePost

Undocumented declaration.

### BasePostWithEmbeddedAuthor

Undocumented declaration.

### commentStatusField

Comment status field for BasePost.

### CreateTemplatePartModal

A React component that renders a modal for creating a template part. The modal displays a title and the contents for creating the template part. This component should not live in this package, it should be moved to a dedicated package responsible for managing template.

*Parameters*

- *props* `{ modalTitle?: string; } & CreateTemplatePartModalContentsProps`: The component props.
- *props.modalTitle* `{ modalTitle?: string; } & CreateTemplatePartModalContentsProps[ 'modalTitle' ]`:

### dateField

Date field for BasePost.

### deletePost

Delete action for Templates, Patterns and Template Parts.

### discussionField

Discussion field for BasePost with custom render logic.

### duplicatePattern

Duplicate action for Pattern.

### duplicatePost

Duplicate action for BasePost.

### duplicateTemplatePart

Duplicate action for TemplatePart.

### excerptField

Excerpt field for BasePost.

### exportPattern

Export action as JSON for Pattern.

### featuredImageField

Featured Image field for BasePostWithEmbeddedFeaturedMedia.

### formatField

Format field for BasePost.

### MediaEdit

A media edit control component that provides a media picker UI with upload functionality for selecting WordPress media attachments. Supports both the traditional WordPress media library and the experimental DataViews media modal.

This component is intended to be used as the `Edit` property of a field definition when registering fields with `registerEntityField` from `@wordpress/editor`.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { MediaEdit } from '@wordpress/fields';import type { DataFormControlProps } from '@wordpress/dataviews'; const featuredImageField = { id: 'featured_media', type: 'media', label: 'Featured Image', Edit: ( props: DataFormControlProps< MyPostType > ) => ( <MediaEdit { ...props } allowedTypes={ [ 'image' ] } /> ),};
```

*Parameters*

- *props* `MediaEditProps<Item>`: – The component props.
- *props.data* `Item`: – The item being edited.
- *props.field* `Object`: – The field configuration with getValue and setValue methods.
- *props.onChange* `Function`: – Callback function when the media selection changes.
- *props.allowedTypes* `[string[]]`: – Array of allowed media types. Use `['*']` to allow all file types. Default `['image']`.
- *props.multiple* `[boolean]`: – Whether to allow multiple media selections. Default `false`.
- *props.hideLabelFromVision* `[boolean]`: – Whether the label should be hidden from vision.
- *props.isExpanded* `[boolean]`: – Whether to render in an expanded form. Default `false`.

*Returns*

- `React.JSX.Element`: The media edit control component.

### MediaEditProps

Undocumented declaration.

### notesField

Notes count field for post types that support editor.notes.

### orderField

Order field for BasePost.

### pageTitleField

Title for the page entity.

### parentField

Parent field for BasePost.

### passwordField

Password field for BasePost.

### Pattern

Undocumented declaration.

### patternTitleField

Title for the pattern entity.

### permanentlyDeletePost

Delete action for PostWithPermissions.

### pingStatusField

Ping status field for BasePost.

### postContentInfoField

Post content information field for BasePost.

### PostType

Undocumented declaration.

### renamePost

Rename action for PostWithPermissions.

### reorderPage

Reorder action for BasePost.

### resetPost

Reset action for Template and TemplatePart.

### restorePost

Restore action for PostWithPermissions.

### scheduledDateField

ScheduledDate Field.

### slugField

Slug field for BasePost.

### statusField

Status field for BasePost.

### stickyField

Sticky field for BasePost.

### templateField

Template field for BasePost.

### templateTitleField

Title for the template entity.

### titleField

Title for the any entity with a `title` property. For patterns, pages or templates you should use the respective field because there are some differences in the rendering, labels, etc.

### trashPost

Trash action for PostWithPermissions.

### viewPost

View post action for BasePost.

### viewPostRevisions

View post revisions action for Post.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
