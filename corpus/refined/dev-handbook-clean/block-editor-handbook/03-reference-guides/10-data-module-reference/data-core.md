---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core
parent_order: 3
sub_order: 10
page_order: 1
title: "WordPress Core Data"
code_quality: degraded
code_issue: pre_newline_loss
---

# WordPress Core Data

Namespace: `core`.

## Dynamically generated selectors

There are a number of user-friendly selectors that are wrappers of the more generic `getEntityRecord` and `getEntityRecords` that can be used to retrieve information for the various entities.

### getPostType

Returns the information for a given post type.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { useSelect } from '@wordpress/data';import { store as coreDataStore } from '@wordpress/core-data'; const postType = useSelect( ( select ) => select( coreDataStore ).getPostType( 'post' ) // Equivalent to: select( coreDataStore ).getEntityRecord( 'root', 'postType', 'post' ));
```

*Parameters*

- postType `string`

*Returns*

- `EntityRecord | undefined`: Record.

### getPostTypes

Returns the information for post types.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { useSelect } from '@wordpress/data';import { store as coreDataStore } from '@wordpress/core-data'; const postTypes = useSelect( ( select ) => { return select( coreDataStore ).getPostTypes( { per_page: 4 } ); // Equivalent to: // select( coreDataStore ).getEntityRecords( 'root', 'postType', { per_page: 4 } );} );
```

*Parameters*

- *query* `GetRecordsHttpQuery`: Optional terms query. If requesting specific fields, fields must always include the ID. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available for “List [Entity kind]s”.

*Returns*

- `EntityRecord[] | null`: Records.

### getTaxonomy

Returns information for a given taxonomy.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { useSelect } from '@wordpress/data';import { store as coreDataStore } from '@wordpress/core-data'; const taxonomy = useSelect( ( select ) => { return select( coreDataStore ).getTaxonomy( 'category' ); // Equivalent to: // select( coreDataStore ).getEntityRecord( 'root', 'taxonomy', 'category' );} );
```

*Parameters*

- taxonomy `string`

*Returns*

- `EntityRecord | undefined`: Record.

### getTaxonomies

Returns information for taxonomies.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { useSelect } from '@wordpress/data';import { store as coreDataStore } from '@wordpress/core-data'; const taxonomies = useSelect( ( select ) => { return select( coreDataStore ).getTaxonomies( { type: 'post' } ); // Equivalent to: // select( coreDataStore ).getEntityRecords( 'root', 'taxonomy', { type: 'post' } );} );
```

*Parameters*

- *query* `GetRecordsHttpQuery`: Optional terms query. If requesting specific fields, fields must always include the ID. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available for “List [Entity kind]s”.

*Returns*

- `EntityRecord[] | null`: Records.

## Other Selectors

### canUser

Returns whether the current user can perform the given action on the given REST resource.

Calling this may trigger an OPTIONS request to the REST API via the `canUser()` resolver.

[https://developer.wordpress.org/rest-api/reference/](../../../rest-api-handbook/05-reference.md)

*Parameters*

- *state* `State`: Data state.
- *action* `string`: Action to check. One of: ‘create’, ‘read’, ‘update’, ‘delete’.
- *resource* `string | EntityResource`: Entity resource to check. Accepts entity object `{ kind: 'postType', name: 'attachment', id: 1 }` or REST base as a string – `media`.
- *id* `EntityRecordKey`: Optional ID of the rest resource to check.

*Returns*

- `boolean | undefined`: Whether or not the user can perform the action, or `undefined` if the OPTIONS request is still being made.

### canUserEditEntityRecord

Returns whether the current user can edit the given entity.

Calling this may trigger an OPTIONS request to the REST API via the `canUser()` resolver.

[https://developer.wordpress.org/rest-api/reference/](../../../rest-api-handbook/05-reference.md)

*Parameters*

- *state* `State`: Data state.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record’s id.

*Returns*

- `boolean | undefined`: Whether or not the user can edit, or `undefined` if the OPTIONS request is still being made.

### getAuthors

> 
> **Deprecated** since 11.3. Callers should use `select( 'core' ).getUsers({ who: 'authors' })` instead.

Returns all available authors.

*Parameters*

- *state* `State`: Data state.
- *query* `GetRecordsHttpQuery`: Optional object of query parameters to include with request. For valid query parameters see the [Users page](../../../rest-api-handbook/05-reference/users.md) in the REST API Handbook and see the arguments for [List Users](../../../rest-api-handbook/05-reference/users.md#list-users) and [Retrieve a User](../../../rest-api-handbook/05-reference/users.md#retrieve-a-user).

*Returns*

- `ET.User[]`: Authors list.

### getAutosave

Returns the autosave for the post and author.

*Parameters*

- *state* `State`: State tree.
- *postType* `string`: The type of the parent post.
- *postId* `EntityRecordKey`: The id of the parent post.
- *authorId* `EntityRecordKey`: The id of the author.

*Returns*

- `EntityRecord | undefined`: The autosave for the post and author.

### getAutosaves

Returns the latest autosaves for the post.

May return multiple autosaves since the backend stores one autosave per author for each post.

*Parameters*

- *state* `State`: State tree.
- *postType* `string`: The type of the parent post.
- *postId* `EntityRecordKey`: The id of the parent post.

*Returns*

- `Array< any > | undefined`: An array of autosaves for the post, or undefined if there is none.

### getBlockPatternCategories

Retrieve the list of registered block pattern categories.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `Array< any >`: Block pattern category list.

### getBlockPatterns

Retrieve the list of registered block patterns.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `Array< any >`: Block pattern list.

### getCurrentTheme

Return the current theme.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `any`: The current theme.

### getCurrentThemeGlobalStylesRevisions

> 
> **Deprecated** since WordPress 6.5.0. Callers should use `select( 'core' ).getRevisions( 'root', 'globalStyles', ${ recordKey } )` instead, where `recordKey` is the id of the global styles parent post.

Returns the revisions of the current global styles theme.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `Array< object > | null`: The current global styles.

### getCurrentUser

Returns the current user.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `ET.User< 'view' >`: Current user object.

### getDefaultTemplateId

Returns the default template use to render a given query.

*Parameters*

- *state* `State`: Data state.
- *query* `TemplateQuery`: Query.

*Returns*

- `string`: The default template id for the given query.

### getEditedEntityRecord

Returns the specified entity record, merged with its edits.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `ET.Updatable< EntityRecord > | false`: The entity record, merged with its edits.

### getEmbedPreview

Returns the embed preview for the given URL.

*Parameters*

- *state* `State`: Data state.
- *url* `string`: Embedded URL.

*Returns*

- `any`: Undefined if the preview has not been fetched, otherwise, the preview fetched from the embed preview API.

### getEntitiesByKind

> 
> **Deprecated** since WordPress 6.0. Use getEntitiesConfig instead

Returns the loaded entities for the given kind.

*Parameters*

- *state* `State`: Data state.
- *kind* `string`: Entity kind.

*Returns*

- `Array< any >`: Array of entities with config matching kind.

### getEntitiesConfig

Returns the loaded entities for the given kind.

*Parameters*

- *state* `State`: Data state.
- *kind* `string`: Entity kind.

*Returns*

- `Array< any >`: Array of entities with config matching kind.

### getEntity

> 
> **Deprecated** since WordPress 6.0. Use getEntityConfig instead

Returns the entity config given its kind and name.

*Parameters*

- *state* `State`: Data state.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.

*Returns*

- `any`: Entity config

### getEntityConfig

Returns the entity config given its kind and name.

*Parameters*

- *state* `State`: Data state.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.

*Returns*

- `any`: Entity config

### getEntityRecord

Returns the Entity’s record object by key. Returns `null` if the value is not yet received, undefined if the value entity is known to not exist, or the entity object if it exists and is received.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *key* `EntityRecordKey`: Optional record’s key. If requesting a global record (e.g. site settings), the key can be omitted. If requesting a specific item, the key must always be included.
- *query* `GetRecordsHttpQuery`: Optional query. If requesting specific fields, fields must always include the ID. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available “Retrieve a [Entity kind]”.

*Returns*

- `EntityRecord | undefined`: Record.

### getEntityRecordEdits

Returns the specified entity record’s edits.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `Optional< any >`: The entity record’s edits.

### getEntityRecordNonTransientEdits

Returns the specified entity record’s non transient edits.

Transient edits don’t create an undo level, and are not considered for change detection. They are defined in the entity’s config.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `Optional< any >`: The entity record’s non transient edits.

### getEntityRecords

Returns the Entity’s records.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *query* `GetRecordsHttpQuery`: Optional terms query. If requesting specific fields, fields must always include the ID. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available for “List [Entity kind]s”.

*Returns*

- `EntityRecord[] | null`: Records.

### getEntityRecordsTotalItems

Returns the Entity’s total available records for a given query (ignoring pagination).

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *query* `GetRecordsHttpQuery`: Optional terms query. If requesting specific fields, fields must always include the ID. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available for “List [Entity kind]s”.

*Returns*

- `number | null`: number | null.

### getEntityRecordsTotalPages

Returns the number of available pages for the given query.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *query* `GetRecordsHttpQuery`: Optional terms query. If requesting specific fields, fields must always include the ID. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available for “List [Entity kind]s”.

*Returns*

- `number | null`: number | null.

### getLastEntityDeleteError

Returns the specified entity record’s last delete error.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `any`: The entity record’s save error.

### getLastEntitySaveError

Returns the specified entity record’s last save error.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `any`: The entity record’s save error.

### getRawEntityRecord

Returns the entity’s record object by key, with its attributes mapped to their raw values.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *key* `EntityRecordKey`: Record’s key.

*Returns*

- `EntityRecord | undefined`: Object with the entity’s raw attributes.

### getRedoEdit

> 
> **Deprecated** since 6.3

Returns the next edit from the current undo offset for the entity records edits history, if any.

*Parameters*

- *state* `State`: State tree.

*Returns*

- `Optional< any >`: The edit.

### getReferenceByDistinctEdits

Returns a new reference when edited values have changed. This is useful in inferring where an edit has been made between states by comparison of the return values using strict equality.

*Usage*

```js
const hasEditOccurred = ( getReferenceByDistinctEdits( beforeState ) !== getReferenceByDistinctEdits( afterState ));
```

*Parameters*

- *state* Editor state.

*Returns*

- A value whose reference will change only when an edit occurs.

### getRevision

Returns a single, specific revision of a parent entity.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordKey* `EntityRecordKey`: The key of the entity record whose revisions you want to fetch.
- *revisionKey* `EntityRecordKey`: The revision’s key.
- *query* `GetRecordsHttpQuery`: Optional query. If requesting specific fields, fields must always include the ID. For valid query parameters see revisions schema in [the REST API Handbook](../../../rest-api-handbook/05-reference.md). Then see the arguments available “Retrieve a [entity kind]”.

*Returns*

- `RevisionRecord | Record< PropertyKey, never > | undefined`: Record.

### getRevisions

Returns an entity’s revisions.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordKey* `EntityRecordKey`: The key of the entity record whose revisions you want to fetch.
- *query* `GetRecordsHttpQuery`: Optional query. If requesting specific fields, fields must always include the ID. For valid query parameters see revisions schema in [the REST API Handbook](../../../rest-api-handbook/05-reference.md). Then see the arguments available “Retrieve a [Entity kind]”.

*Returns*

- `RevisionRecord[] | null`: Record.

### getSyncConnectionStatus

Returns the current sync connection status across all entities. Prioritizes disconnected states, then connecting, then connected.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `ConnectionStatus | undefined`: The current sync connection state, prioritized by importance.

### getThemeSupports

Return theme supports data in the index.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `any`: Index data.

### getUndoEdit

> 
> **Deprecated** since 6.3

Returns the previous edit from the current undo offset for the entity records edits history, if any.

*Parameters*

- *state* `State`: State tree.

*Returns*

- `Optional< any >`: The edit.

### getUserPatternCategories

Retrieve the registered user pattern categories.

*Parameters*

- *state* `State`: Data state.

*Returns*

- `Array< UserPatternCategory >`: User patterns category array.

### getUserQueryResults

Returns all the users returned by a query ID.

*Parameters*

- *state* `State`: Data state.
- *queryID* `string`: Query ID.

*Returns*

- `ET.User< 'edit' >[]`: Users list.

### hasEditsForEntityRecord

Returns true if the specified entity record has edits, and false otherwise.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `boolean`: Whether the entity record has edits or not.

### hasEntityRecord

Returns true if a record has been received for the given set of parameters, or false otherwise.

Note: This action does not trigger a request for the entity record from the API if it’s not available in the local state.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *key* `EntityRecordKey`: Record’s key.
- *query* `GetRecordsHttpQuery`: Optional query.

*Returns*

- `boolean`: Whether an entity record has been received.

### hasEntityRecords

Returns true if records have been received for the given set of parameters, or false otherwise.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *query* `GetRecordsHttpQuery`: Optional terms query. For valid query parameters see the [Reference](../../../rest-api-handbook/05-reference.md) in the REST API Handbook and select the entity kind. Then see the arguments available for “List [Entity kind]s”.

*Returns*

- `boolean`: Whether entity records have been received.

### hasFetchedAutosaves

Returns true if the REST request for autosaves has completed.

*Parameters*

- *state* `State`: State tree.
- *postType* `string`: The type of the parent post.
- *postId* `EntityRecordKey`: The id of the parent post.

*Returns*

- `boolean`: True if the REST request was completed. False otherwise.

### hasRedo

Returns true if there is a next edit from the current undo offset for the entity records edits history, and false otherwise.

*Parameters*

- *state* `State`: State tree.

*Returns*

- `boolean`: Whether there is a next edit or not.

### hasRevision

Returns true if a revision has been received for the given set of parameters, or false otherwise.

Note: This does not trigger a request for the revision from the API if it’s not available in the local state.

*Parameters*

- *state* `State`: State tree
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordKey* `EntityRecordKey`: The key of the entity record whose revision you want to check.
- *revisionKey* `EntityRecordKey`: The revision’s key.
- *query* `GetRecordsHttpQuery`: Optional query.

*Returns*

- `boolean`: Whether a revision has been received.

### hasUndo

Returns true if there is a previous edit from the current undo offset for the entity records edits history, and false otherwise.

*Parameters*

- *state* `State`: State tree.

*Returns*

- `boolean`: Whether there is a previous edit or not.

### isAutosavingEntityRecord

Returns true if the specified entity record is autosaving, and false otherwise.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `boolean`: Whether the entity record is autosaving or not.

### isDeletingEntityRecord

Returns true if the specified entity record is deleting, and false otherwise.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `boolean`: Whether the entity record is deleting or not.

### isPreviewEmbedFallback

Determines if the returned preview is an oEmbed link fallback.

WordPress can be configured to return a simple link to a URL if it is not embeddable. We need to be able to determine if a URL is embeddable or not, based on what we get back from the oEmbed preview API.

*Parameters*

- *state* `State`: Data state.
- *url* `string`: Embedded URL.

*Returns*

- `boolean`: Is the preview for the URL an oEmbed link fallback.

### isRequestingEmbedPreview

Returns true if a request is in progress for embed preview data, or false otherwise.

*Parameters*

- *state* `State`: Data state.
- *url* `string`: URL the preview would be for.

*Returns*

- `boolean`: Whether a request is in progress for an embed preview.

### isSavingEntityRecord

Returns true if the specified entity record is saving, and false otherwise.

*Parameters*

- *state* `State`: State tree.
- *kind* `string`: Entity kind.
- *name* `string`: Entity name.
- *recordId* `EntityRecordKey`: Record ID.

*Returns*

- `boolean`: Whether the entity record is saving or not.

## Actions

### addEntities

Returns an action object used in adding new entities.

*Parameters*

- *entities* `Array`: Entities received.

*Returns*

- `Object`: Action object.

### clearEntityRecordEdits

Action triggered to clear all edits from an entity record.

*Parameters*

- *kind* `string`: Kind of the entity.
- *name* `string`: Name of the entity.
- *recordId* `number|string`: Record ID of the entity record.

*Returns*

- `Object`: Action object.

### deleteEntityRecord

Action triggered to delete an entity record.

*Parameters*

- *kind* `string`: Kind of the deleted entity.
- *name* `string`: Name of the deleted entity.
- *recordId* `number|string`: Record ID of the deleted entity.
- *query* `?Object`: Special query parameters for the DELETE API call.
- *options* `[Object]`: Delete options.
- *options.\_\_unstableFetch* `[Function]`: Internal use only. Function to call instead of `apiFetch()`. Must return a promise.
- *options.throwOnError* `[boolean]`: If false, this action suppresses all the exceptions. Defaults to false.

### editEntityRecord

Returns an action object that triggers an edit to an entity record.

*Parameters*

- *kind* `string`: Kind of the edited entity record.
- *name* `string`: Name of the edited entity record.
- *recordId* `number|string`: Record ID of the edited entity record.
- *edits* `Object`: The edits.
- *options* `Object`: Options for the edit.
- *options.undoIgnore* `[boolean]`: Whether to ignore the edit in undo history or not.

*Returns*

- `Object`: Action object.

### receiveDefaultTemplateId

Returns an action object used to set the template for a given query.

*Parameters*

- *query* `Object`: The lookup query.
- *templateId* `string`: The resolved template id.

*Returns*

- `Object`: Action object.

### receiveEntityRecords

Returns an action object used in signalling that entity records have been received.

*Parameters*

- *kind* `string`: Kind of the received entity record.
- *name* `string`: Name of the received entity record.
- *records* `Array|Object`: Records received.
- *query* `?Object`: Query Object.
- *invalidateCache* `?boolean`: Should invalidate query caches.
- *edits* `?Object`: Edits to reset.
- *meta* `?Object`: Meta information about pagination.

*Returns*

- `Object`: Action object.

### receiveNavigationFallbackId

Returns an action object signalling that the fallback Navigation Menu id has been received.

*Parameters*

- *fallbackId* `integer`: the id of the fallback Navigation Menu

*Returns*

- `Object`: Action object.

### receiveRevisions

Action triggered to receive revision items.

*Parameters*

- *kind* `string`: Kind of the received entity record revisions.
- *name* `string`: Name of the received entity record revisions.
- *recordKey* `number|string`: The key of the entity record whose revisions you want to fetch.
- *records* `Array|Object`: Revisions received.
- *query* `?Object`: Query Object.
- *invalidateCache* `?boolean`: Should invalidate query caches.
- *meta* `?Object`: Meta information about pagination.

### receiveThemeSupports

> 
> **Deprecated** since WP 5.9, this is not useful anymore, use the selector directly.

Returns an action object used in signalling that the index has been received.

*Returns*

- `Object`: Action object.

### receiveUploadPermissions

> 
> **Deprecated** since WP 5.9, use receiveUserPermission instead.

Returns an action object used in signalling that Upload permissions have been received.

*Parameters*

- *hasUploadPermissions* `boolean`: Does the user have permission to upload files?

*Returns*

- `Object`: Action object.

### redo

Action triggered to redo the last undone edit to an entity record, if any.

### saveEditedEntityRecord

Action triggered to save an entity record’s edits.

*Parameters*

- *kind* `string`: Kind of the entity.
- *name* `string`: Name of the entity.
- *recordId* `Object`: ID of the record.
- *options* `Object=`: Saving options.

### saveEntityRecord

Action triggered to save an entity record.

*Parameters*

- *kind* `string`: Kind of the received entity.
- *name* `string`: Name of the received entity.
- *record* `Object`: Record to be saved.
- *options* `Object`: Saving options.
- *options.isAutosave* `[boolean]`: Whether this is an autosave.
- *options.\_\_unstableFetch* `[Function]`: Internal use only. Function to call instead of `apiFetch()`. Must return a promise.
- *options.throwOnError* `[boolean]`: If false, this action suppresses all the exceptions. Defaults to false.

### setSyncConnectionStatus

Returns an action object used to set the sync connection status for an entity or collection.

*Parameters*

- *kind* `string`: Kind of the entity.
- *name* `string`: Name of the entity.
- *key* `number|string|null`: The entity key, or null for collections.
- *status* `Object|null`: The connection state object or null on unload.

*Returns*

- `Object`: Action object.

### undo

Action triggered to undo the last edit to an entity record, if any.
