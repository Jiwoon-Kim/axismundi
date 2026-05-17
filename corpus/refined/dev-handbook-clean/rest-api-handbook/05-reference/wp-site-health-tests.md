---
source_url: https://developer.wordpress.org/rest-api/reference/wp-site-health-tests/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: wp-site-health-tests
parent_order: 5
page_order: 40
title: "Wp Site Health Tests"
---

# Wp Site Health Tests

## Schema

The schema defines all the fields that exist within a wp site health test record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `test` | The name of the test being run.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| --- | --- |
| `label` | A label describing the test.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `status` | The status of the test.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: ``<br><br>One of: `good`, `recommended`, `critical` |
| `badge` | The category this test is grouped in.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `description` | A more descriptive explanation of what the test looks for, and why it is important for the user.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `actions` | HTML containing an action to direct the user to where they can resolve the issue.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: |

## Retrieve a Wp Site Health Test

### Definition & Example Request

`GET /wp-site-health/v1/tests/background-updates`

Query this endpoint to retrieve a specific wp site health test record.

`$ curl https://example.com/wp-json/wp-site-health/v1/tests/background-updates`

There are no arguments for this endpoint.

## Retrieve a Wp Site Health Test

### Definition & Example Request

`GET /wp-site-health/v1/tests/loopback-requests`

Query this endpoint to retrieve a specific wp site health test record.

`$ curl https://example.com/wp-json/wp-site-health/v1/tests/loopback-requests`

There are no arguments for this endpoint.

## Retrieve a Wp Site Health Test

### Definition & Example Request

`GET /wp-site-health/v1/tests/https-status`

Query this endpoint to retrieve a specific wp site health test record.

`$ curl https://example.com/wp-json/wp-site-health/v1/tests/https-status`

There are no arguments for this endpoint.

## Retrieve a Wp Site Health Test

### Definition & Example Request

`GET /wp-site-health/v1/tests/dotorg-communication`

Query this endpoint to retrieve a specific wp site health test record.

`$ curl https://example.com/wp-json/wp-site-health/v1/tests/dotorg-communication`

There are no arguments for this endpoint.

## Retrieve a Wp Site Health Test

### Definition & Example Request

`GET /wp-site-health/v1/tests/authorization-header`

Query this endpoint to retrieve a specific wp site health test record.

`$ curl https://example.com/wp-json/wp-site-health/v1/tests/authorization-header`

There are no arguments for this endpoint.
