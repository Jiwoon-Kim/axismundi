# C2S Create(Note), 2026-10-29

## Storage and submission

`ax_local_objects` owns current local Note documents, independently of `wp_posts` and
`ax_remote_objects`. Its primary key is its own ID. `ax_objects` remains the common
URI/source index; `ax_object_index` remains the listing projection, not a second body.
The source binding is `axismundi-object-projections / local-object / body-row-id`.

Canonical Object `id` and `url` are both `/objects/{uuid}`. Activity and Object UUIDs
are separately server allocated. Existing WordPress objects keep their existing URLs.
Creating a Note does not allocate a WordPress post or guess a CPT from its AS2 type.

The existing Actor outbox POST accepts Create with an embedded Note, or a bare Note
which is wrapped in Create. It also accepts Delete of an Object owned by that Actor.
Cookie/REST nonce and Application Password authentication use the existing adapter;
principal authorization is rechecked under the submission lock. No JMAP capability
is introduced. Like/Undo continue through their existing service.

The Activity writer owns one SQL transaction. Body, common registry, reply/quote,
mention, tag, thread, listing and optional media usage writes participate without
nested COMMIT. SQL failure prevents the post-commit event and delivery scheduling.
New taxonomy term caches are invalidated after rollback; arbitrary external hooks
are not claimed to be transactional. Idempotency-Key replays return the same Activity;
reusing a key for different input, including another supported verb, returns 409.

New local Create payloads reference the Object URI, following the existing local WP
lifecycle convention. The current body has one authoritative store, with no body
revision/history collection. This changes no existing ledger payload and establishes
no inbound retention, redaction or evidence preservation policy.

## Projection and deletion

Text is sanitized; language maps, addressing and structured document properties are
preserved. Attachments are URI descriptors, not an upload API. Local attachment IDs
require the submitting User's edit permission and use the Media Library usage index.
Delete removes usage links, not shared media binaries.

Delete replaces the body with exactly `id`, `type=Tombstone`, `formerType`, `deleted`.
It uses version CAS and reconciles projections; thread position can remain. Public
Objects return 200 before deletion and 410 afterward for both AP JSON and HTML.
HEAD has the same status and no body. Unknown and nonpublic Objects return 404,
independently of login cookies. A private/operator read API is not implemented here.

WordPress registry registration requires recorded Create attribution matching the
source. `save_post` alone and direct registry calls cannot invent that attribution.
The committed Activity observer admits the matching WordPress source afterward.

## Hard Cutoff and Deferred Work

Checkpoint for the next session: local Note JSON does not yet advertise replies,
likes, shares or emoji reaction collection URLs. Existing property decorators are
attached to WP-specific filters. Finish that wiring and test WP-parent replies and
local Object collection round trips before calling this backend slice review-ready.
The existing storage and HTTP checks below do not establish those collection paths.

No dedicated wp-admin Object list/detail/editor or Media Library attachment picker
has been added. Those belong to the following authoring UI slice, after backend review.

The Note plugin bootstrap is inert. It no longer registers its CPT, REST APIs,
routes, publishers or source adapters. Existing Note tables/data are untouched;
there is no migration, dual-write or old-URI compatibility layer.

Update, Create(Question), Question/vote table retirement, native uploads and frontend
composer work are not included. The existing 15 remote Question observations are
not deleted or given invented historical Creates. Ledger retention is deferred.

## Verification

Run storage/failure-injection checks:

```sh
docker exec wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-create-note.php
```

Run real HTTP checks using the internal wp-env origin:

```sh
docker exec -e AX_C2S_HTTP_ORIGIN=http://wordpress wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-outbox-http.php
```

Storage audit: 26 checks passed, including injected body/registry/relation/tag/listing/
media failures, WP admission and minimal Tombstone/CAS. Real HTTP audit: 53 checks
passed, including existing Like/Undo, authentication, Create/bare Note, cross-verb
retry conflicts, private 404 and AP/HTML 410/HEAD. Shared relation regression: 16/16;
tag-reference regression: 34/34. No mutation-suite claim is made by these fault tests.
After cleanup: Activity rows 30,770, principals 1,038, local body rows 0. Existing
ledger contents were compared, not just counted, by the submission audits.
