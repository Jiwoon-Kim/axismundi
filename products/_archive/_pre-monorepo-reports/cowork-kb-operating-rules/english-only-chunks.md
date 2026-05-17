---
name: knowledge chunks must be English-only
description: For Axismundi knowledge base project — write new chunks in English (no Korean prose), to save tokens
type: feedback
originSessionId: 906565b5-2dd0-41e7-96c6-ace8de371cb8
---
In the Axismundi Knowledge Base project (./knowledge/ output folder), write
chunk files in **English only**. No Korean prose in chunk bodies.

**Why:** Token economy. Korean characters typically encode to 2–3 tokens each,
inflating chunk size and retrieval cost. User explicitly requested this on
2026-05-09 after the first 2 chunks (text-fields-spec.md, text-fields-impl.md)
came in over the 1000–3000자 budget partly due to Korean prose weight.

**How to apply:**
- All new chunks under `./knowledge/` → English prose throughout.
- Code / tokens / spec terminology stay as-is (always English anyway).
- Headings, body, references, citations → English.
- The two existing Korean chunks (text-fields-{spec,impl}.md) stay as-is —
  rule applies forward only ("다음부터는").
- This **overrides** the original project_instructions rule "5. 한국어 작성.
  본문 설명은 한국어" for the chunks themselves.
- **Does NOT apply to chat conversation** with the user — discussion / outline
  proposals / status reports stay in Korean.
