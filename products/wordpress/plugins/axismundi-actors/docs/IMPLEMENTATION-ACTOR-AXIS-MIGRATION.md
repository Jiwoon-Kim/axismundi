# Actor-axis consumer migration

Implementation of `BRIEF-ACTOR-AXIS-MIGRATION.md` after C3 and principal generation. Base: `4a9cab3`. Review the working-tree diff; staging deployment is excluded.

## Boundaries and Results

- Acting identity uses the shared workspace selection/publisher kernel. The rollout gate is unchanged; OFF still uses the central legacy adapter, not consumer-specific fallbacks.
- Historical attribution reads the Activities ledger. Forum first submission freezes the selected public Person in its existing entry/meta before Create. Later reads prefer the ledger; neither `post_author` nor current selection rewrites authorship. Failed intent writes return errors.
- Forum Person/local managed-Group restrictions are centralized in `includes/actor-policy.php`, not widened or replaced with `isPersonal`.
- Forum moderation inventories union direct User management and explicit moderator/local-account grants for accessible Person Accounts. This does not confer inherited Account principal roles. Sessionless community creation no longer fabricates a Follow from the recovery-owner User.
- Calendar authoring/participation uses the selected Account; authority choices enumerate directly manageable Accounts. Existing ledger-based event attribution is retained.
- Bridge inventory enumerates public local Actors, including secondary Persons and Organizations without User bindings. Notifications were already plural after principal generation.
- Actor Profile defaults to the selected Account. Explicit links and forms pin identity and recheck live principal rights; GET never provisions Actors. Menu accessibility uses `read`, with editing authorized by principal rights. Page title and author-profile media scripts are corrected.
- WordPress User summary/avatar/list surfaces remain User-scoped. OP's old User-author helper remains a deprecated empty-return compatibility symbol, with no production callers.

## Public Folders

Anonymous readability remains governed by folder ACL/tier/password gates, independently of attribution. Without recorded Create, a readable folder omits `attributedTo` entirely. Reading does not mint Create, outbox membership or historical attribution.

Existing public folders without a recorded publisher in this local database:

| Term | UUID |
| --- | --- |
| 1081 | `75835314-d060-48ab-85af-8bd76b88bcc8` |
| 59 | `507cd379-ace4-421a-9825-aef84f7c4258` |
| 991 | `b1f7c537-27db-433d-a7de-86a04e0f4924` |

Canonical URLs remain `http://localhost:8884/media/folder/{uuid}`. These are local review identifiers, not portable production IDs. Removing guessed attribution must not hide these URLs.

HTTP verification with `Accept: application/activity+json`: all three return 200, `type: OrderedCollection`, and no `attributedTo` key.

## Remaining Single-Actor Lookups

`tests/audit-actor-axis-callers.php` token-scans production includes, excluding deprecated Note. Exactly 11 D/E consumers remain:

| File / function | Axis |
| --- | --- |
| Actors admin / `authorize_user_actor` | E: legacy activation API |
| Actors admin / `profile_panel` | D: WordPress User summary |
| Actors admin / `users_column_content` | D: row's User |
| Actors avatar / `filter_avatar_data` | D: User avatar |
| Actors repository / `ensure_for_user` | D: explicit User provisioning |
| Actors acting-actor / `default_acting_actor` | E: old adapter |
| Actors account-operations / `legacy_current_workspace` | E: central gate-OFF adapter |
| Actors routing / `legacy_author_profile_redirect_url` | E: old User URL |
| Activities local-social-ui / `users_follow_column_content` | D: target User, not acting subject |
| Bridge migration-scan / `legacy_local_actor` | E: old data inspection |
| Contacts lifecycle / `purge_for_deleted_user` | D: existing User-deletion lifecycle; redesign deferred |

## Focused Verification

Use `docker exec wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/<plugin>/tests/<audit>.php`.

- Actors `audit-actor-axis.php`: 47 checks; selection, stored attribution, Organization profile, live revocation, plural inventories and exact principal-row preservation.
- Actors `audit-actor-axis-callers.php`: 11 classified D/E calls, no unclassified production call.
- Actors `audit-actor-axis-mutations.php`: `AX_ACTOR_AXIS_MUTATION=topic|folder|selection|profile|plural`; all five caught at the intended assertions. Functions are cloned in memory; production files are not rewritten.
- Media `audit-folder-identity.php`: 17 checks, including public unattributed projection/property omission and private/password denial.
- Forum `audit-forum-moderation.php`: 32 checks.
- Calendar `audit-dependencies.php`: 21 checks; `audit-event-acting-actor.php`: 11 checks. The writing audit owns its gates and cleans its principal fixtures.
- Actors `audit-principal-fixture-cleanup.php`: 18/18 passed (nine audits, two rounds); full row-content comparison preserved all 1,038 existing principal rows. Run serially against mutation/fixture writers.
- All 30 changed/new PHP files passed syntax checks; `git diff --check` passed.

Browser: disposable author User + Person + Organization; selection set through the central legacy API with rollout gate OFF. Default Actor Profile displayed Organization. Actual nonce form submission changed that Organization from Public to Internal, with successful redirect/notice and stable page title. Fixtures were removed. This is not a new-switcher click test; gate-ON selection is covered by the domain audit.

Measurement corrections: an ad hoc CLI probe used a nonexistent function and is not evidence. An overlapping mutation/cleanup run failed a whole-table snapshot comparison; rerun serially, not as evidence of a production leak.

## Excluded

Contacts/JMAP capability/AddressBook redesign, profile cards, switcher relocation/rollout, managed-Actors UI redesign, generalized ActivitySubmission, Question authoring, direction/locality migration and staging packaging. Existing historical orphan rows are not purged; no retrospective publication is inferred.

## Follow-ups (not in this change)

Opus 리뷰 2026-10-27. 본작업은 통과. 아래는 **감사 위생** 문제이고 이 변경이 만든 결함이
아니므로 별건으로 둔다.

```txt
잔여 principal 3행   1035 -> 1038
  identity 54059 Person        tombstone
           54060 Person        tombstone
           54061 Organization  public   addr axeai1jdleuq@localhost:8884
  2026-10-26 14:49 생성. 54061 은 tombstone 이 아니라 **살아 있는 공개 Actor** 다.
  principal 행만이 아니라 Actor 자체가 남았다

회귀 감사의 사각지대
  audit-principal-fixture-cleanup.php 는 목록에 적힌 감사 각각의 **전후만** 비교한다.
  목록 밖 감사가 흘리거나 감사 밖에서 생긴 행은 보지 못한다.
  그리고 wp_ax_actor_managers 만 보고 wp_ax_identities · wp_ax_actors 는 보지 않는다
```

같은 뿌리의 다른 증상이 어휘 축소 쪽에 있다 -- `internal` 이 브리프 작성 시점 78행에서
1,279행으로 늘었고 픽스처 잔여로 보인다. **추정이므로 일괄 삭제·전환하지 않는다**
(`BRIEF-VOCABULARY-REDUCTION.md` 의 "순서" 절). 실데이터와 잔여를 먼저 가른다.
