---
source_url: https://developer.wordpress.org/rest-api/reference/settings/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: site-settings
parent_order: 5
page_order: 27
title: "Site Settings"
---

# Site Settings

## Schema

The schema defines all the fields that exist within a Site Setting record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `title` | Site title.<br><br><br>JSON data type: string<br><br>Context: `` |
| --- | --- |
| `description` | Site tagline.<br><br><br>JSON data type: string<br><br>Context: `` |
| `url` | Site URL.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Context: `` |
| `email` | This address is used for admin purposes, like new user notification.<br><br><br>JSON data type: string,  <br>Format: email<br><br>Context: `` |
| `timezone` | A city in the same timezone as you.<br><br><br>JSON data type: string<br><br>Context: `` |
| `date_format` | A date format for all date strings.<br><br><br>JSON data type: string<br><br>Context: `` |
| `time_format` | A time format for all time strings.<br><br><br>JSON data type: string<br><br>Context: `` |
| `start_of_week` | A day number of the week that the week should start on.<br><br><br>JSON data type: integer<br><br>Context: `` |
| `language` | WordPress locale code.<br><br><br>JSON data type: string<br><br>Context: `` |
| `use_smilies` | Convert emoticons like :-) and :-P to graphics on display.<br><br><br>JSON data type: boolean<br><br>Context: `` |
| `default_category` | Default post category.<br><br><br>JSON data type: integer<br><br>Context: `` |
| `default_post_format` | Default post format.<br><br><br>JSON data type: string<br><br>Context: `` |
| `posts_per_page` | Blog pages show at most.<br><br><br>JSON data type: integer<br><br>Context: `` |
| `show_on_front` | What to show on the front page<br><br><br>JSON data type: string<br><br>Context: `` |
| `page_on_front` | The ID of the page that should be displayed on the front page<br><br><br>JSON data type: integer<br><br>Context: `` |
| `page_for_posts` | The ID of the page that should display the latest posts<br><br><br>JSON data type: integer<br><br>Context: `` |
| `default_ping_status` | Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.<br><br><br>JSON data type: string<br><br>Context: ``<br><br>One of: `open`, `closed` |
| `default_comment_status` | Allow people to submit comments on new posts.<br><br><br>JSON data type: string<br><br>Context: ``<br><br>One of: `open`, `closed` |
| `site_logo` | Site logo.<br><br><br>JSON data type: integer<br><br>Context: `` |
| `site_icon` | Site icon.<br><br><br>JSON data type: integer<br><br>Context: |

## Retrieve a Site Setting

### Definition & Example Request

`GET /wp/v2/settings`

Query this endpoint to retrieve a specific Site Setting record.

`$ curl https://example.com/wp-json/wp/v2/settings`

There are no arguments for this endpoint.

## Update a Site Setting

### Arguments

| `title` | Site title. |
| --- | --- |
| `description` | Site tagline. |
| `url` | Site URL. |
| `email` | This address is used for admin purposes, like new user notification. |
| `timezone` | A city in the same timezone as you. |
| `date_format` | A date format for all date strings. |
| `time_format` | A time format for all time strings. |
| `start_of_week` | A day number of the week that the week should start on. |
| `language` | WordPress locale code. |
| `use_smilies` | Convert emoticons like :-) and :-P to graphics on display. |
| `default_category` | Default post category. |
| `default_post_format` | Default post format. |
| `posts_per_page` | Blog pages show at most. |
| `show_on_front` | What to show on the front page |
| `page_on_front` | The ID of the page that should be displayed on the front page |
| `page_for_posts` | The ID of the page that should display the latest posts |
| `default_ping_status` | Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.  <br>One of: `open`, `closed` |
| `default_comment_status` | Allow people to submit comments on new posts.  <br>One of: `open`, `closed` |
| `site_logo` | Site logo. |
| `site_icon` | Site icon. |

### Definition

`POST /wp/v2/settings`
