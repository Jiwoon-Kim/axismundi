# Plugin boundaries

| Package | Owns |
|---|---|
| Axismundi Actors | Actor identities, profile data, keys, Actor URI resolution |
| Axismundi Object Projections | Object/collection JSON-LD, remote observations, cache leases |
| Axismundi Activities | Activity ledger, Follow/Block state, Like/Announce, logical inbox/outbox membership |
| Axismundi Notifications | Recipient-facing rules, read state, notification queue |
| Axismundi Federation | HTTP inbox/outbox, signatures, signed fetch, federation delivery queue |
| PWA / Web Push | Installation, service worker, subscriptions, push transport |

Federation delivery and notification delivery are distinct queues with independent retry and
failure state. Activities never performs a network request.

## Object Projections lease API

Activities will call a public API such as:

```php
axismundi_op_add_lease( $object_uri, 'interaction', $activity_uri );
axismundi_op_release_lease( $object_uri, 'interaction', $activity_uri );
```

The exact API ships in Object Projections before Activities Phase 3. Calls are optional and
feature-detected. The cache owner decides expiry and garbage collection; Activities does not
write its tables or duplicate a lease table.

## Media policy

Activities must not subscribe to `add_attachment` to create feed Activities. Uploading is a
Media Library operation. A later explicit "share to feed" command may ask Activities to mint
Create, making user intent auditable and preventing follower timeline flooding.
