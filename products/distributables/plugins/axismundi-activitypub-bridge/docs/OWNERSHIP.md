# Runtime ownership matrix

> Status: **0.0.2 dormant transport compatibility contract.**

| Surface | Current owner | Official module state | Reason |
|---|---|---|---|
| Actor profile, WebFinger, NodeInfo | Axismundi Actors | Router dormant | Avoid competing identity and discovery URLs. |
| Object content negotiation | Object Projections | Router dormant | One canonical URL must have one JSON-LD producer. |
| Post publish lifecycle | Axismundi Activities | Scheduler dormant | Prevent duplicate Create records and split Actor identity. |
| Follow/Like/domain state | Axismundi Activities | Handler dormant | Prevent CPT/postmeta state beside the URI-keyed ledger. |
| Inbox HTTP | Nobody while dormant | POST returns 503 | Silent discard is worse than explicit temporary failure. |
| Outbound delivery | Nobody while dormant | Dispatcher dormant | No supported external signing-identity API exists yet. |
| Signature and REST validation code | Official ActivityPub | Loaded, idle | Retained for the future verified Inbox handoff. |
| Official stored rows/options/cron | Official ActivityPub | Preserved | Compatibility mode is reversible and non-destructive. |

## Re-enable order

1. Replace internal callback removal with supported upstream module gates when available.
2. Add verified Inbox handoff, then re-enable only REST verification and the required transport callbacks.
3. Add external Actor delivery, then re-enable dispatcher/queue callbacks without official domain handlers.
4. Keep official Router, post lifecycle scheduler, and default relationship handlers disabled permanently
   while Axismundi repositories are authoritative.

The bridge must never acknowledge an Inbox write and then discard it. Until step 2 is complete,
temporary failure is the only safe response.
