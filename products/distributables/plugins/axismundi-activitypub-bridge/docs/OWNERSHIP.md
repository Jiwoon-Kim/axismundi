# Runtime ownership matrix

> Status: **0.0.16 behavior-level official-plugin composition and Bridge-owned delivery spool.**

| Surface | Current owner | Official module state | Reason |
|---|---|---|---|
| Actor profile, WebFinger, NodeInfo | Axismundi Actors | Router dormant | Avoid competing identity and discovery URLs. |
| Actor URL JSON-LD | Object Projections | Router dormant | One representation owner; Bridge injects transport fields only. |
| Object content negotiation | Object Projections | Router dormant | One canonical URL must have one JSON-LD producer. |
| Public Actor Outbox representation and GET route | Object Projections | Default domain routes dormant | Activities supplies a bounded public-safe query; representation stays transport-independent. |
| Post publish lifecycle | Axismundi Activities | Official post callbacks unhooked | Prevent duplicate Create records and split Actor identity. |
| Follow/Like/domain state | Axismundi Activities | Official type callbacks unhooked | Prevent CPT/postmeta state beside the URI-keyed ledger. |
| Inbox HTTP and signature validation | Official ActivityPub | Inbox routes enabled | The official permission callback verifies the network request. |
| Inbox Activity and relationship state | Axismundi Activities | Default handlers dormant | Existing controller actions feed one URI-keyed Activity into the authoritative ledger. |
| Verified Inbox action composition and transport mapping | ActivityPub Bridge | Default handlers dormant; CPT skipped only when claimed | The bridge consumes validated actions without losing unclaimed snapshots. |
| Remote Actor snapshot maintenance | Actors through Bridge | Default Actor handlers dormant | Follow only queues a missing host cache; complete cached-only Update(Actor) documents may refresh snapshots under the official same-host signature trust boundary. |
| Outbound Activity JSON-LD representation | Object Projections | N/A | Adds the canonical context without mutating the Activities ledger. |
| Outbound spool, retry, and HTTP | ActivityPub Bridge | Private `ax_ap_delivery` jobs | Official `ap_outbox` invariants remain private to its Dispatcher and Scheduler. |
| Outbound HTTP signature | Official ActivityPub | Existing request-signing filter active | Bridge supplies a transient key reference and resolves private key material only while sending. |
| Signature and REST validation code | Official ActivityPub | Active for Inbox routes | This is the retained S2S boundary. |
| Official stored rows/options/cron | Official ActivityPub | Preserved | Compatibility mode is reversible and non-destructive. |
| Official-to-Axismundi migration analysis | ActivityPub Bridge | Read-only | Only this package understands both storage models; import and purge remain disabled. |

## Re-enable order

1. Consume the existing controller-owned Inbox actions after the official permission callback.
2. Unhook official type callbacks through `activitypub_register_handlers` and local publication
   callbacks through `activitypub_register_schedulers`.
3. Keep the official request signer, REST controllers, and Dispatcher active for their own rows.
4. Keep Bridge delivery jobs outside `ap_outbox`; never share a transport state machine.

An Activity without a public local target is intentionally unclaimed, matching the official
shared-Inbox recipient rules. When a local target exists but Axismundi cannot record the
Activity, official Inbox snapshot storage remains enabled as a recovery path while official type
handlers stay dormant.
