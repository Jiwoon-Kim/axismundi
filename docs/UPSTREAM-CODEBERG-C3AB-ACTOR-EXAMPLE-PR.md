# PR 본문 초안 — c3ab 비규범 예시(기존 Actor 소개)

대상: `fediverse/fep`, 브랜치 `Jiwoon-Kim:fep-c3ab-actor-example` (로컬 커밋 `310a623`,
`upstream/main` `6a9c19d` 기준). 1차 개정 PR #986 은 이미 머지됐으므로 이것은 별건 PR 이다.

Claude Code 표기 줄은 넣지 않는다 — 저자 표시 정확성 때문이며, 2026-11-13 에 내린 같은
결정을 따른다. `fediverse/fep` 에 LLM 정책은 없다.

게시 전 검증:

```powershell
$env:PYTHONIOENCODING="utf-8"; python tools/validators/verify_upstream_comment.py `
  --source docs/UPSTREAM-CODEBERG-C3AB-ACTOR-EXAMPLE-PR.md `
  --source-after "<본문 시작 주석 줄>" `
  --source-before "<본문 끝 주석 줄>" `
  --candidate <본문 파일>
```

<!-- BEGIN PR BODY -->
One non-normative section, after `What this does not specify`. No normative text changes.

The requirements leave actor types out of the base case and allow a server to apply the same rules to other types without saying what they mean there. This example takes that opening at its narrowest: a `Create` whose `actor` and `object` name the same actor URI can introduce an actor that already exists to selected recipients. It states what the activity is not — not provisioning, not a follow relationship — and leaves the actor document's retrieval policy alone, since the recipients are addressed on the activity.

It cites two server-to-server precedents for an actor acting on itself, as precedents rather than as definitions of client-to-server actor management: Mastodon sends an `Update` carrying the actor document when a profile changes, and a `Delete` naming the same actor URI in both `actor` and `object` when announcing an account deletion. Only the second is a URI reference.

Actor lifecycle stays out of scope, and so does what a receiver does with such an activity. I have not implemented or measured the receiving side, which is why this is an example and not a requirement.
<!-- END PR BODY -->
