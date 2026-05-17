---
source_url: https://developer.wordpress.org/plugins/users/
synced: 2026-05-12
handbook: plugin
chapter: users
slug: index
parent_order: 12
page_order: 0
title: "Users"
---

# Users

A *User* is an access account with corresponding capabilities within the WordPress installation. Each WordPress user has, at the bare minimum, a username, password and email address.

Once a user account is created, that user may log in using the WordPress Admin (or programmatically) to access WordPress functions and data. WordPress stores the Users in the `users` table.

## Roles and Capabilities

Users are assigned [roles](roles-and-capabilities.md#roles), and each role has a set of [capabilities](roles-and-capabilities.md#capabilities).

You can create new roles with their own set of capabilities. Custom capabilities can also be created and assigned to existing roles or new roles.

In WordPress, developers can take advantage of user roles to limit the set of actions an account can perform.

## The Principle of Least Privileges

WordPress adheres to the principal of least privileges, the practice of giving a user *only* the privileges that are essential for performing the desired work. You should follow this lead when possible by creating roles where appropriate and checking capabilities before performing sensitive tasks.
