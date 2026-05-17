---
source_url: https://developer.wordpress.org/plugins/plugin-basics/including-a-software-license/
synced: 2026-05-12
handbook: plugin
chapter: plugin-basics
slug: software-license
parent_order: 2
page_order: 5
title: "Including a Software License"
---

# Including a Software License

Most WordPress plugins are released under the [GPL](http://www.gnu.org/licenses/gpl.html), which is the same license that [WordPress itself uses](https://wordpress.org/about/license/). However, there are other compatible options available. It is always best to clearly indicate the license your plugin uses.

In the [Header Requirements](https://developer.wordpress.org/plugins/the-basics/header-requirements/) section, we briefly mentioned how you can indicate your plugin’s license within the plugin header comment. Another common, and encouraged, practice is to place a license block comment near the top of your main plugin file (the same one that has the plugin header comment).

This license block comment usually looks something like this:


```text
/*{Plugin Name} is free software: you can redistribute it and/or modifyit under the terms of the GNU General Public License as published bythe Free Software Foundation, either version 2 of the License, orany later version. {Plugin Name} is distributed in the hope that it will be useful,but WITHOUT ANY WARRANTY; without even the implied warranty ofMERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See theGNU General Public License for more details. You should have received a copy of the GNU General Public Licensealong with {Plugin Name}. If not, see {URI to Plugin License}.*/
```
