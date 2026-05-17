---
source_url: https://developer.wordpress.org/apis/making-http-requests/
synced: 2026-05-12
handbook: common-apis
chapter: making-http-requests
slug: making-http-requests
parent_order: 12
page_order: 0
title: "Making HTTP requests"
code_quality: degraded
code_issue: pre_newline_loss
---

# Making HTTP requests

Very often we need to make HTTP requests from our theme or plugin, for example when we need to fetch data from an external API. Luckily WordPress has many helper functions to help you do that.

In this section, you will learn how to properly make HTTP requests and handle their responses.

Here’s an example of what you’re going to see


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$response = wp_remote_get( 'https://api.github.com/users/wordpress' );$body = wp_remote_retrieve_body( $response );
```

In the next articles you’ll see a detailed explanation on how to make the requests:

- [GETting data from an external service](12-making-http-requests/getting-data-from-external-service.md)
- [POSTing data to an external service](12-making-http-requests/posting-data-to-external-service.md)
- [Performance](12-making-http-requests/performance.md)
- [Advanced](12-making-http-requests/advanced.md)
- [Authentication](12-making-http-requests/authentication.md)

If you’re just looking for the available helper functions, here they are:

The functions below are the ones you will use to retrieve a URL.

- [`wp_remote_get()`](https://developer.wordpress.org/reference/functions/wp_remote_get/): Retrieves a URL using the GET HTTP method.
- [`wp_remote_post()`](https://developer.wordpress.org/reference/functions/wp_remote_post/): Retrieves a URL using the POST HTTP method.
- [`wp_remote_head()`](https://developer.wordpress.org/reference/functions/wp_remote_head/): Retrieves a URL using the HEAD HTTP method.
- [`wp_remote_request()`](https://developer.wordpress.org/reference/functions/wp_remote_request/): Retrieves a URL using either the default GET or a custom HTTP method that you specify.

The other helper functions deal with retrieving different parts of the response. These make usage of the API very simple and are the preferred method for processing response objects.

- `wp_remote_retrieve_body()` – Retrieves just the body from the response.
- `wp_remote_retrieve_header()` – Retrieve a single header by name from the raw response.
- `wp_remote_retrieve_headers()` – Retrieve only the headers from the raw response.
- `wp_remote_retrieve_response_code()` – Retrieve the response code for the HTTP response. This should be 200, but could be 4xx or even 3xx on failure.
- `wp_remote_retrieve_response_message()` – Retrieve only the response message from the raw response.
