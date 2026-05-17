---
source_url: https://developer.wordpress.org/apis/making-http-requests/performance/
synced: 2026-05-12
handbook: common-apis
chapter: making-http-requests
slug: performance
parent_order: 12
page_order: 3
title: "Performance"
---

# Performance

When you make an HTTP request, your application has to wait for the external server to respond to the request and for all the data to be transferred over the network. This can be very time consuming, and your application performance might be heavily impacted.

## Caching

That’s why you should always consider caching your API requests, so you don’t have to do them all the time.

Caching the response means storing the response on your server so you can easily use it multiple times without the need of an HTTP request every time.

For example, let’s say your site makes an HTTP request to Github to fetch your user’s stats and display it on your sidebar. If you don’t cache, every visitor in your site will trigger that API request and wait for github to response. And if you stop and think, they are all seeing the same information, because your stats don’t change so fast.

In the other hand, if you use cache, only the first visitor will have to wait for Github to respond. All the next users will see the same information that was quickly grabbed from the local database.

You can then define how often this information has to be updated. In other words, how often the cache has to be cleaned.

There are multiple apporaches to caching. An easy one provided by WordPress is the [Transient API](https://developer.wordpress.org/apis/handbook/transients/). Check it out!

## Check Headers

Many APIs allow you to make a HEAD request to check the status of things before actually retrieving the data you want. For example, you can make a HEAD request to check if there’s an update, before doing a GET request to actually fetch the data. This is a much faster request because it only responds a short piece of information.

Check the Advanced &gt; Headers section for more information.
