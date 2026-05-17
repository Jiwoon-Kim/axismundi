---
source_url: https://developer.wordpress.org/rest-api/reference/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: reference
parent_order: 5
page_order: 0
title: "Reference"
---

# Reference

The WordPress REST API is organized around [REST](http://en.wikipedia.org/wiki/Representational_state_transfer), and is designed to have predictable, resource-oriented URLs and to use HTTP response codes to indicate API errors. The API uses built-in HTTP features, like HTTP authentication and HTTP verbs, which can be understood by off-the-shelf HTTP clients, and supports cross-origin resource sharing to allow you to interact securely with the API from a client-side web application.

The REST API uses JSON exclusively as the request and response format, including error responses. While the REST API does not completely conform to the [HAL standard](http://stateless.co/hal_specification.html), it does implement HAL’s `._links` and `._embedded` properties for linking API resources, and is fully discoverable via hyperlinks in the responses.

The REST API provides public data accessible to any client anonymously, as well as private data only available after [authentication](03-using-the-rest-api/authentication.md). Once authenticated the REST API supports most content management actions, allowing you to build alternative dashboards for a site, enhance your plugins with more responsive management tools, or build complex single-page applications.

This API reference provides information on the specific endpoints available through the API, their parameters, and their response data format.

## REST API Developer Endpoint Reference

| Resource | Base Route |
| --- | --- |
| [Posts](05-reference/posts.md) | `/wp/v2/posts` |
| [Post Revisions](05-reference/post-revisions.md) | `/wp/v2/posts/<id>/revisions` |
| [Categories](05-reference/categories.md) | `/wp/v2/categories` |
| [Tags](05-reference/tags.md) | `/wp/v2/tags` |
| [Pages](05-reference/pages.md) | `/wp/v2/pages` |
| [Page Revisions](05-reference/page-revisions.md) | `/wp/v2/pages/<id>/revisions` |
| [Comments](05-reference/comments.md) | `/wp/v2/comments` |
| [Taxonomies](05-reference/taxonomies.md) | `/wp/v2/taxonomies` |
| [Media](05-reference/media.md) | `/wp/v2/media` |
| [Users](05-reference/users.md) | `/wp/v2/users` |
| [Post Types](05-reference/types.md) | `/wp/v2/types` |
| [Post Statuses](05-reference/statuses.md) | `/wp/v2/statuses` |
| [Settings](05-reference/site-settings.md) | `/wp/v2/settings` |
| [Themes](05-reference/themes.md) | `/wp/v2/themes` |
| [Search](05-reference/search-results.md) | `/wp/v2/search` |
| [Block Types](05-reference/block-types.md) | `/wp/v2/block-types` |
| [Blocks](05-reference/editor-blocks.md) | `/wp/v2/blocks` |
| [Block Revisions](05-reference/block-revisions.md) | `/wp/v2/blocks/<id>/autosaves/` |
| [Block Renderer](05-reference/rendered-blocks.md) | `/wp/v2/block-renderer` |
| [Block Directory Items](05-reference/block-directory-items.md) | `/wp/v2/block-directory/search` |
| [Plugins](05-reference/plugins.md) | `/wp/v2/plugins` |

## A Distributed API

Unlike many other REST APIs, the WordPress REST API is distributed and available individually on each site that supports it. This means there is no singular API root or base to contact; instead, we have [a discovery process](https://developer.wordpress.org/rest-api/discovery/) that allows interacting with sites without prior contact. The API also exposes self-documentation at the index endpoint, or via an `OPTIONS` request to any endpoint, allowing human- or machine-discovery of endpoint capabilities.
