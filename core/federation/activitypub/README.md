# ActivityPub Federation Ontology — RESERVED

Placeholder for the federation ontology layer. Will be populated in v4.x with:

- Actor / Object / Activity / Collection entities
- Public/Followers/Bcc/Audience addressing model
- Inbox/Outbox protocol semantics
- Side: client-to-server (C2S) vs. server-to-server (S2S)

Binding to WordPress will live at `bindings/wordpress-activitypub/` and connect:
- `wp:Post` ↔ `ap:Note` / `ap:Article`
- `wp:User` ↔ `ap:Person`
- `wp:Comment` ↔ `ap:Note` (inReplyTo)
- WordPress core REST API ↔ ActivityPub inbox/outbox endpoints

This layer is **orthogonal to the design system axis**. ActivityPub binding is independent of Material/Fluent/other design choices.
