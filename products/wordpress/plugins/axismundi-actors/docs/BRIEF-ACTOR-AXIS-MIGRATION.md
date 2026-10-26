# 브리프: `get_for_user()` 단수 가정 폐기 — 호출부 축 이전

2026-10-26. **codex 설계·구현용.** 배경 계약은 `BRIEF-ACCOUNT-INBOX.md`의 S10 절과
"Users 인접 화면의 축" 절이 가지고, 선택·전환 계약은 `BRIEF-S10-SELECTION.md`가
가진다. 이 문서는 그 둘을 다시 쓰지 않는다. 여기서 새로 더하는 것은 **호출부 39곳의
인벤토리와 축 판정 규칙**뿐이다.

착수 계기: Account 전환기가 동작한 뒤에도 `users.php?page=axismundi-actor-profile`이
전환을 따라가지 않는다는 오너 지적(2026-10-26). 그 화면 하나의 수선이 아니라는 것이
아래 측정의 결론이다.

## 측정 — 모집단

```txt
axismundi_actors_get_for_user() production 호출   39곳 / 27파일 / 10플러그인
                                                  (tests·docs 제외, 정의부 제외)
플러그인별   forum 14 · actors 10 · calendar 4 · note 3 · activities 2
             activitypub-bridge 2 · contacts 1 · media-library 1
             notifications 1 · object-projections 1
```

`BRIEF-ACCOUNT-INBOX.md`가 적은 "production 27 파일"과 같은 모집단이다. 파일이 27,
호출이 39다.

## 축 판정 규칙

`get_for_user()`는 "이 User의 Actor" 하나를 돌려준다. User당 Account가 여럿이 되면
그 질문 자체가 성립하지 않으므로, **호출부마다 원래 묻고 싶었던 것이 무엇인지**를
먼저 정해야 한다. 네 가지뿐이다.

```txt
A  지금 행동 중인 주체        선택 쿠키가 권위. axismundi_actors_current_workspace_result()
                              단수. 선택이 없거나 철회면 오류이고 폴백하지 않는다
B  이미 저장된 기록의 주체    기록이 들고 있는 Actor를 읽는다. post_author에서 파생 금지
                              OP `wp_ax_objects.attributed_identity_id`가 그 자리다
C  이 User가 가진 Account 전부 복수 목록. 단수 반환으로는 표현할 수 없으므로
                              시그니처가 바뀐다. 스왑이 아니다
D  진짜 User 축               그대로 둔다. 아바타·Users 목록 열·User 삭제 정리 등
```

B를 A로 바꾸면 **과거 글의 저자가 지금 선택에 따라 달라진다.** 가장 비싼 오역이므로
B 판정을 먼저 확정한다. `axismundi-object-projections/docs/LOCAL-OBJECTS.md`의
"발행주체를 projection에서 파생하지 않는다"가 같은 규칙이고, 이 마이그레이션은 그
규칙을 호출부에 적용하는 일이다.

## 인벤토리

**이 표의 근거는 대부분 함수명이다.** 원문을 읽고 확인한 것은 ✔ 네 곳뿐이고, 나머지는
추정이다. codex는 바꾸기 전에 각 호출부의 원문으로 판정을 확정하고, 이 표와 다르면
표가 틀린 것으로 보고 고친다.

### B — 저장된 기록의 주체 (파생 중단)

**정정 (2026-10-26, 초판 직후).** 초판은 `local_author_actor_uri`를 귀속 경로로 적고
그 docblock의 경고를 근거로 인용했다. **틀렸다.** 추적하니 귀속은 이미 원장이 답한다.

```txt
op/post-article.php:88   apply_filters( 'axismundi_op_local_object_attribution', '', ... )
                         기본값 '' -- OP는 파생하지 않는다
activities/publish-command.php:139  그 필터에 붙어 axismundi_act_get_object_attribution()
                         주석: "projection no longer derives an identity and hands it over
                         to be recorded; it asks what was recorded"
```

즉 **B의 권위 저장소는 이미 존재하고 Activities 원장이다.** 남은 B는 "원장에 묻지 않고
아직 파생하는 호출부"이지, 저장소가 없는 상태가 아니다. 그리고
`local_author_actor_uri`는 어떤 귀속 필터에도 걸려 있지 않다 -- 유일한 production
소비자는 forum이고, 용도는 귀속이 아니라 **투영 가능성 게이트**다.

```txt
  object-projections/includes/post-article.php:57   local_author_actor_uri
     귀속이 아니라 "저자에게 공개 Actor가 있는가" 게이트. 거는 곳 없음.
     유일한 호출: forum/topics.php:967 topic_article_supports
     옮길 질문은 "post_author의 Actor"가 아니라 "이 Object를 발행한 Actor가 공개인가"
✔ calendar/includes/event-actor.php:77              author_person_actor_uri
     post_author에서 파생한다. 바로 다음 함수의 docblock이 "The switcher's answer,
     and deliberately not a fourth resolver" -- 새 Event 경로는 이미 전환을 안다.
     즉 캘린더는 쓰기만 옮겼고 읽기가 남았다
  calendar/includes/event-actor.php:210              backfill_event_acting_actors
  forum/includes/topics.php:486                      admit_local_topic
  forum/includes/topics.php:987                      topic_to_article
  forum/includes/distribution.php:278                record_topic_delete
  media-library/includes/folder-identity.php:183     folder_actor_uri
```

### C — 복수 (시그니처가 바뀐다)

```txt
✔ notifications/includes/deliveries.php:221         readable_identities
     이미 배열을 돌려준다. get_for_user()는 "내 Person" 한 줄이고 거기에
     list_manageable_actors()를 더한다. principal 기준 목록 하나로 합치면 끝난다
  activitypub-bridge/includes/admin.php:181          public_actors
```

### A — 행동 주체 (선택된 Account)

```txt
  activities/includes/local-social.php:18            current_local_actor
  calendar/includes/event-participation.php:974      signed_in_actor_uri
  calendar/includes/admin-calendars.php:142          admin_creatable_authorities
```

### D — User 축 유지

```txt
  actors/includes/avatar.php:69                      filter_avatar_data
  actors/includes/admin.php:256                      users_column_content
  actors/includes/repository.php:2283                ensure_for_user
  contacts/includes/lifecycle.php:304                purge_for_deleted_user
  activities/includes/local-social-ui.php:54         users_follow_column_content
  forum/includes/admin.php:193                       render_group_admin_section   ($manager_user)
  forum/includes/moderators.php:196                  effective_moderators         ($manager['user_id'])
```

뒤 두 개는 **다른** User의 Actor를 표시용으로 푸는 자리다. 축이 현재 세션이 아니므로
선택 쿠키와 무관하고, 다만 그 User에게 Account가 여럿일 때 무엇을 보일지는 남는다.

### E — 레거시·폐기 (이 작업에서 건드리지 않는다)

```txt
  actors/includes/account-operations.php:146         legacy_current_workspace
  actors/includes/acting-actor.php:68                default_acting_actor
  actors/includes/routing.php:519                    legacy_author_profile_redirect_url
  activitypub-bridge/includes/migration-scan.php:78  legacy_local_actor
     앞의 셋은 S10이 적은 폐기 대상이고 C4(레거시 전환 경로 제거)가 가져간다.
     지금 옮기면 곧 지울 코드를 옮기는 것이다

  note/includes/cpt.php:102 · envelope.php:289 · federation.php:304
     axismundi-note는 하드 컷오프된 폐기 대상 플러그인이다. 마이그레이션하지 않는다
```

### ? — 축 판정이 설계 결정인 곳 (forum)

forum 7곳은 "이 User가 포럼에서 무엇을 할 수 있는가"를 묻는데, 답이 A인지 C인지가
포럼 의미론에 달려 있고 그것은 이 브리프가 정할 것이 아니다.

```txt
  forum/includes/topics.php:312       user_can_post_to_community
  forum/includes/topics.php:605       can_view_community_topics
  forum/includes/memberships.php:30   auto_join_published_community
✔ forum/includes/memberships.php:188  joined_local_communities_for_user
  forum/includes/moderators.php:36    user_can_moderate
  forum/includes/moderators.php:55    record_moderator_change
  forum/includes/outbound-topics.php:25  user_can_submit_to_remote_group
  forum/includes/outbound-topics.php:48  followed_remote_groups_for_user
  forum/includes/repository.php:401      moderated_communities
```

갈림길은 이렇다 -- 권한 질문이 **"지금 행동 중인 Account가 할 수 있는가"(A)** 인지
**"내 Account 중 하나라도 할 수 있는가"(C)** 인지. 가입·게시는 A가 자연스럽고
목록·조회는 C가 자연스러워 보이지만, 섞으면 "보이는데 쓸 수 없는" 화면이 생긴다.

`joined_local_communities_for_user`는 원문을 읽었고, 판정에 쓸 사실이 하나 더 있다 --
**`'Person' !== $member->get_type()`이면 빈 배열을 돌려준다.** Organization Account로
Group에 가입하는 것을 지금 코드가 막고 있다는 뜻이다. 의도인지 단수 전제의 부산물인지
확인하고, 의도가 아니면 이 게이트도 같이 결정한다.

## 화면

`BRIEF-ACCOUNT-INBOX.md`의 "Users 인접 화면의 축"이 결정을 들고 있다. 여기서는
위 인벤토리와의 연결만 적는다.

```txt
axismundi-actor-profile   actors/admin.php:90 · 199 · 746 · 1290
                          admin_target_user() -> user_id -> get_for_user()
                          결정: 선택된 Account를 편집한다. Contacts가 참조 구현
axismundi-managed-actors  admin.php:316 get_current_user_id() · :326 is_managed() 게이트
                          결정: managed 게이트를 principal로 치환하지 않고 재설계.
                          재설계 전까지 손대지 않는다
axismundi-remote-actors   사이트 전역 캐시. 전환과 무관. 바꾸지 않는다
```

Actor Profile 네 곳은 위 분류에서 일부러 뺐다. 화면의 축을 바꾸는 일이라 호출부
치환이 아니고, 아래 순서의 마지막에 온다.

## 순서

```txt
0  판정 확정    codex가 39곳 원문으로 A/B/C/D/E를 확정하고, 이 표와 다른 곳을 보고한다
                위 ✔ 4곳 외에는 전부 재확인 대상이다
1  B 먼저       저장된 귀속을 읽도록 바꾼다. 귀속이 비어 있는 과거 기록을 무엇으로
                읽을지가 같이 결정되어야 한다 -- 비우는 것이 기본값이고,
                post_author로 되돌리는 폴백을 다시 만들지 않는다
2  C            복수 시그니처. principal 기준 목록 하나로 모은다
3  forum 판정   A/C를 정하고 적용한다. Person 게이트도 여기서
4  A            남은 행동 주체 호출부
5  화면         Actor Profile 축 이전. managed-actors 재설계는 별도 슬라이스
```

B가 먼저인 이유는 두 가지다. 과거 기록의 저자가 선택에 따라 흔들리는 것이 가장 비싼
오역이고, A를 먼저 하면 B가 그 사이 더 그럴듯해 보인다 -- 선택이 늘 채워져 있으니까.

## 완료선과 검증

```txt
완료선   production에서 get_for_user() 호출이 D와 E에만 남는다.
         남은 각 호출이 왜 User 축인지 한 줄 주석을 가진다
검증     전환 후 같은 화면·같은 기록이 무엇을 보이는지 wp-env에서 측정한다.
         "감사 통과"는 완료선이 아니다 -- 단수 전제를 공유하는 감사가 있다
변이     B 호출부마다 "저장된 귀속이 없으면 post_author로 되돌린다"를 심어
         감사가 잡는지 본다. 안 잡히면 감사가 그 불변을 안 보는 것이다
금지     폴백을 새로 만들지 않는다. 선택이 없으면 오류다.
         `axismundi_actors_account_selection_enabled()`가 꺼진 경로를 위해
         두 번째 해소기를 만들지 않는다 -- 레거시는 E가 가져간다
```

## 선행·동시 작업과의 충돌

```txt
principal 세대교체  이 작업보다 앞선다. `BRIEF-PRINCIPAL-GENERATION.md`
                  forum/moderators.php:196 의 get_for_user($manager['user_id']) 는
                  local_account principal 이 쓰이면 사라진다. 먼저 하지 않으면 같은
                  자리를 두 번 옮긴다. forum 의 Person 8곳·managed 9곳도 같은 영역이다
C3 예약 발행      같은 B 영역을 만진다. 예약 발행이 Account를 얼려 저장하는 자리가
                  곧 B가 읽을 자리다. 두 작업이 같은 컬럼을 서로 모르게 정하지 않도록
                  C3를 먼저 하거나, 저장 위치를 이 브리프 1단계에서 먼저 못박는다
C4 레거시 제거    E 묶음을 가져간다. 이 브리프보다 먼저 와도 되고 뒤여도 된다
스테이징 패키징   S2/S5/S10 묶음은 이 작업과 독립이다
```

## 오너 결정 — Group 가입 자격 (2026-10-26)

위 forum 판정보류에서 나온 `'Person' !== $member->get_type()` 게이트에 대한 결정이다.

> Group actor가 group에 가입하는 것 자체가 모순. Service나 Org처럼 Person이 아닌
> Actor는 당분간 Group follow를 막는다. **로컬이면 personal account가 아닌 경우를
> 막는 것으로 일반화**하고 나중에 수정한다.

즉 게이트를 없애지 않고 축만 옮긴다 -- actor type에서 **Account가 이 User에게 개인
귀속인가**로. 오늘 기준 두 집합은 정확히 같으므로 동작은 바뀌지 않는다.

```txt
로컬 비-tombstone 234행
  Person        user     personal_owner 있음   27
  Group         managed  없음                  69
  Organization  managed  없음                 137
  Application   site     없음                   1

Person 인데 비개인       0
비-Person 인데 개인      0      ← 양방향 반례 없음. 치환이 오늘은 무동작이다
```

적용 지점은 `axismundi_actors_account_is_personal( $actor, $user_id )`
(`account-access.php:79`)이고, 그 판정은 `is_local && scope != 'site' &&
personal_owner_user_id === $user_id && role === 'owner'` 이다.

여기서 한 가지는 짚어 둔다. 이 세션에서 **`isPersonal`은 개인 귀속의 표현이지 권한이
아니다**라고 결정했고, 이 게이트는 그것을 자격 판정에 쓴다. 모순은 아니다 -- 막는
근거가 "권한이 없다"가 아니라 "**개인 귀속이 아닌 주체가 Group에 가입한다는 것이
의미상 성립하지 않는다**"이기 때문이다. 다만 두 축이 갈라지는 날 -- 예컨대 Person
Actor를 다른 User가 manager로 운영하는 경우 -- 이 게이트는 "Person만"이 아니라
"**내가 개인적으로 소유한 Account만**"으로 읽힌다. 그때 다시 본다.

```txt
적용   로컬 Account 한정. 원격 Actor의 가입 판정은 이 게이트가 아니다
       (remote actor는 scope가 항상 NULL이고 연합 개념이 아니다)
범위   Group 가입·follow. 다른 권한 판정에 이 게이트를 복사하지 않는다
```

### 정정 — 위 게이트 결정은 뒤집혔다 (2026-10-26, 같은 날)

**위 절을 지우지 않고 남긴다.** 같은 일반화를 다시 시도하지 않기 위해서다.

뒤집은 근거는 둘이고, 둘 다 "오늘 두 집합이 일치한다"는 측정이 **축의 정당성이 아니라
우연**이었음을 보인다.

```txt
principal 운영   workspaces.php:20  can_access_workspace = account_role() !== ''
                 owner 뿐 아니라 manager·editor 도 통과한다. accounts_for_user()도
                 personal_owner 인 Account를 제외하지 않는다
                 -> 개인 귀속 Account를 다른 User가 운영할 수 있고, 그러면 같은
                    Account에 대해 is_personal 이 User마다 다른 답을 낸다.
                    Account 자체에 대해 참이어야 하는 것을 게이트로 쓸 수 없다
원격 Person      S2S Person 은 User 관계를 정의할 방법이 없어 전부 비개인 Person 이다
                 -> 로컬에서 그 조합을 금지하면 일상적으로 수신하는 형태를 로컬에서는
                    만들 수 없는 비대칭이 생긴다
```

그리고 애초에 **옮길 게이트가 Activity 층에 따로 있었다.**

```txt
activities/local-social.php:60   로컬: subject 는 Person, object 는 Person 이거나 managed Group
activities/local-social.php:74   원격: "An activated public local Person actor is required"
                                 둘 다 actor_type 으로 판정하고 is_personal 을 읽지 않는다
```

**결론: Follow 자격은 Activity 층이 이미 actor_type 으로 쥐고 있다. 옮기지 않는다.**
forum 의 `'Person' !== get_type()` 8곳은 제품 층 제약이고, 축 이전 전에 **단일 술어로
모으는 것이 선행**이다 -- 같은 규칙이 8벌이면 한 곳만 고쳐도 조용히 어긋난다.

층 구분의 전문은 `axismundi/docs/DECISION-ACCOUNT-VS-ACTOR-POLICY.md` 가 가진다.
