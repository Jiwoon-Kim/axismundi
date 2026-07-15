# Runtime ownership matrix

> Status: **0.0.4 verified Inbox handoff contract.**

| Surface | Current owner | Official module state | Reason |
|---|---|---|---|
| Actor profile, WebFinger, NodeInfo | Axismundi Actors | Router dormant | Avoid competing identity and discovery URLs. |
| Object content negotiation | Object Projections | Router dormant | One canonical URL must have one JSON-LD producer. |
| Post publish lifecycle | Axismundi Activities | Scheduler dormant | Prevent duplicate Create records and split Actor identity. |
| Follow/Like/domain state | Axismundi Activities | Handler dormant | Prevent CPT/postmeta state beside the URI-keyed ledger. |
| Inbox HTTP and signature validation | Official ActivityPub | Inbox routes enabled | The official permission callback verifies the network request. |
| Inbox Activity and relationship state | Axismundi Activities | Default handlers dormant | The verified handoff records one URI-keyed Activity and materializes local relations. |
| Outbound delivery | Nobody while dormant | Dispatcher dormant | No supported external signing-identity API exists yet. |
| Signature and REST validation code | Official ActivityPub | Active for Inbox routes | This is the retained S2S boundary. |
| Official stored rows/options/cron | Official ActivityPub | Preserved | Compatibility mode is reversible and non-destructive. |

## Re-enable order

1. Prefer the supported upstream module gate; retain callback removal only as a stock-version fallback.
2. Keep verified Inbox handoff immediately after the official permission callback and before default persistence.
3. Add external Actor delivery, then re-enable dispatcher/queue callbacks without official domain handlers.
4. Keep official Router, post lifecycle scheduler, and default relationship handlers disabled permanently
   while Axismundi repositories are authoritative.

The bridge must never acknowledge an Inbox write and then discard it. Stock upstream versions
without the verified handoff retain the temporary-failure guard.
