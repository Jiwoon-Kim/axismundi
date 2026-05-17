---
source_url: https://developer.wordpress.org/apis/internationalization/internationalization-functions/
synced: 2026-05-12
handbook: common-apis
chapter: internationalization
slug: internationalization-functions
parent_order: 6
page_order: 1
title: "Internationalization Functions"
---

# Internationalization Functions

Check the [Internationalization Guidelines](https://developer.wordpress.org/apis/handbook/internationalization/internationalization-guidelines/) and learn what each i18n function is for, how to use them, and the best practices when writing your strings.

## Basic functions

- [__()](https://developer.wordpress.org/reference/functions/__/)
- [_e()](https://developer.wordpress.org/reference/functions/_e/)
- [_x()](https://developer.wordpress.org/reference/functions/_x/)
- [_ex()](https://developer.wordpress.org/reference/functions/_ex/)
- [_n()](https://developer.wordpress.org/reference/functions/_n/)
- [_nx()](https://developer.wordpress.org/reference/functions/_nx/)
- [_n_noop()](https://developer.wordpress.org/reference/functions/_n_noop/)
- [_nx_noop()](https://developer.wordpress.org/reference/functions/_nx_noop/)
- [translate_nooped_plural()](https://developer.wordpress.org/reference/functions/translate_nooped_plural%28%29/)

## Translate & Escape functions

Strings that require translation and is used in attributes of html tags must be escaped.

- [esc_html__()](https://developer.wordpress.org/reference/functions/esc_html__/)
- [esc_html_e()](https://developer.wordpress.org/reference/functions/esc_html_e/)
- [esc_html_x()](https://developer.wordpress.org/reference/functions/esc_html_x/)
- [esc_attr__()](https://developer.wordpress.org/reference/functions/esc_attr__/)
- [esc_attr_e()](https://developer.wordpress.org/reference/functions/esc_attr_e/)
- [esc_attr_x()](https://developer.wordpress.org/reference/functions/esc_attr_x/)

## Date and number functions

- [number_format_i18n()](https://developer.wordpress.org/reference/functions/number_format_i18n)
- [date_i18n()](https://developer.wordpress.org/reference/functions/date_i18n)

## Functions also available in javascript

- [__()](https://developer.wordpress.org/reference/functions/__/)
- [_x()](https://developer.wordpress.org/reference/functions/_x/)
- [_n()](https://developer.wordpress.org/reference/functions/_n/)
- [_nx()](https://developer.wordpress.org/reference/functions/_nx/)
- sprintf()

Note: To be able to use these functions available in your javascript, you have to [set up your plugin/theme javascript localization](https://developer.wordpress.org/apis/handbook/internationalization/#internationalizing-javascript).
