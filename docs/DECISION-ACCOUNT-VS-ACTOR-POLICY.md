# 결정: Account mailbox policy와 Actor activity policy를 분리한다

2026-10-26. 오너 결정. 적용 범위는 Actors · Activities · JMAP · Contacts · Forum.

## 규칙

> AddressBook은 Account의 것이고, Follow relationship은 Actor의 것이다.

```txt
Account mailbox policy (JMAP)     이 User가 이 Account의 데이터를 읽고 쓸 수 있는가
                                  읽는 사실: principal role · personal_owner ·
                                             accountCapabilities · isReadOnly
                                  읽지 않는 것: actor_type
Actor activity policy (AS2/AP)    이 Actor가 이 Activity를 보내고 받을 수 있는가
                                  읽는 사실: actor_type · status · handle lock ·
                                             로컬/원격 · audience
                                  읽지 않는 것: User · principal · role · isPersonal
```

두 판정을 한 술어로 합치지 않는다. 둘 다 통과해야 하는 요청은 있지만, 한쪽이 다른
쪽을 대신하지 않는다. C2S가 그 다리다 -- Account 층이 **누가 말할 수 있는가**를,
Activity 층이 **무엇을 말할 수 있는가**를 정한다.

## 측정 — 저장 모양이 이미 두 층이다

```txt
wp_ax_contact_address_books   owner_actor_id  bigint        로컬 identity 행 참조
wp_ax_activity_relations      subject_actor_uri / object_actor_uri  text + hash   URI
```

둘 다 "actor"라고 적혀 있지만 **참조 방식이 다르고, 그 차이가 곧 층이다.**

```txt
로컬 identity 행을 참조   이 서버가 데이터를 보관한다는 뜻 -> Account 층
URI 양쪽을 참조           어느 쪽이든 다른 서버일 수 있다는 뜻 -> Actor 층
```

AddressBook은 원격 Actor의 것일 수 없다. Follow는 양쪽 다 원격일 수 있다. 새 테이블을
설계할 때 **identity 행을 가리키는가 URI를 가리키는가**가 어느 층의 것인지를 결정한다.

`owner_actor_id`가 Account를 뜻하면서 이름이 actor인 것은 모순이 아니다. "JMAP Account와
AS2 Actor는 같은 엔터티"라는 결정이 이미 있고, 같은 행을 두 층에서 달리 묻는 것뿐이다.
층을 가르는 것은 이름이 아니라 **무엇을 묻는가와 어떻게 참조하는가**다.

## 판정이 제 층을 벗어났는지 보는 시험

```txt
Activity 판정이  isPersonal · role · principal 을 읽는다   -> 넘어갔다
Account 판정이   actor_type 을 읽는다                       -> 넘어갔다
```

코드가 이 경계를 이미 선언해 두었다.

```txt
account-access.php:78   "User-relative personal attribution, never inferred from
                         Actor type or manager role alone."
local-social.php:60,74  Follow 자격은 actor_type · status · handle lock 로만 판정한다.
                         User도 principal도 등장하지 않는다
workspaces.php:20       can_access_workspace = account_role() !== ''.
                         actor_type이 등장하지 않는다
```

## 섞였던 사례 두 가지

**하나.** 2026-10-26, Group 가입 자격 게이트를 `actor_type === 'Person'`에서
`isPersonal`로 일반화하자고 적었다. **Activity 층 판정에 Account 층 사실을 넣은 것**이고
위 시험에 걸린다. 오늘 기준 두 집합이 정확히 일치해(로컬 234행, 양방향 반례 0) 무동작
으로 보였는데, 그것은 축이 옳아서가 아니라 Person ≡ 개인귀속이 우연히 성립해서였다.
두 가지가 그 우연을 깬다 -- 개인 귀속 Account를 다른 User가 principal로 운영할 수 있고
(`workspaces.php:20`은 owner뿐 아니라 manager·editor도 통과시킨다), 원격 S2S Person은
User 관계를 정의할 방법이 없어 전부 비개인 Person이다.

**정정.** Follow 자격은 Activity 층이 이미 `actor_type`으로 쥐고 있다. 옮기지 않는다.

**둘.** Forum이 `'Person' !== get_type()`를 8곳에서 각자 재선언한다 -- 가입
(`memberships.php:31`), 가입 목록(`:189`), 모더레이터 자격(`moderators.php:23`),
모더레이터 목록(`:191`), 원격 Group 제출·목록(`outbound-topics.php:16·30·49`),
게시(`topics.php:313`), 토픽 투영(`:988`), 삭제 기록(`distribution.php:279`).
읽는 사실 자체는 Activity 층이라 틀리지 않았으나, **같은 규칙이 8벌이라 한 곳만 고치면
조용히 어긋난다.** 축 이전 전에 단일 술어로 모으는 것이 선행이다.

## 왜 자꾸 섞이는가 — 한쪽 층에 아직 자리가 없다

```txt
register_account_provider 실 호출부   0 건
accountCapabilities                   모든 Account가 {}
isReadOnly                            모든 Account가 true (AccountCapability::writable
                                      로만 켜지는데 grant가 없다)
primaryAccounts                       항상 {}
```

Account mailbox policy의 **표면이 비어 있다.** 접근 판정을 놓을 자리가 없으니 질문이
Actor 층과 제품 층으로 흘러간다. 플랫폼을 유기적으로 만들려다 섞인 것이라기보다, 한쪽
층이 아직 지어지지 않아 질문이 갈 곳이 없는 쪽에 가깝다.

## 함의 — 순서

```txt
첫 provider 등록(Contacts 유력)이 곧 mailbox policy가 처음 존재하게 되는 지점이다.
그 전까지 "이건 mailbox 쪽"이라고 판정해도 옮겨 놓을 대상이 없다.
기능별 메인 Account(primaryAccounts) 설정 UI를 그 슬라이스로 미룬 것과 같은 자리다.
```

## 남은 결정

```txt
비-Person Account가 Group을 Follow할 수 있어야 하는가   Activity 층. 현재는 금지
Forum이 Person만 받을 것인가                            제품 층. 8곳을 하나로 모은 뒤
```

둘은 다른 답을 가질 수 있다 -- Organization이 Group을 Follow해 읽되 포럼에 글은 쓰지
못하는 것도 정합하다.

## 저장은 JMAP 처럼 나누고, AS2 컬렉션은 그 투영이다 (2026-10-26)

오너 결정. AS2 와 JMAP 이 같은 것을 다르게 나눈다는 관찰에서 나왔다.

```txt
AS2    inbox/outbox 가 Activity 를 담는다. 하나에 무엇을·누가·누구에게가 다 들어간다
       AP §5.1 "The outbox stream contains activities the user has published"
       AP §5.2 "The inbox stream contains all activities received by the actor."
       AP §6   Activity 가 아닌 객체를 POST 하면 Create 로 감싸진다 (§6.2.1 MUST)
JMAP   객체와 행위를 나눈다. Mailbox 는 Email 을 담고, 보내는 행위는 EmailSubmission 이다
       RFC 8621 §7  identityId · envelope · sendAt 은 immutable, undoStatus 는 pending|final|canceled
       Mailbox.role 은 IANA "IMAP Mailbox Name Attributes" 레지스트리를 따르고
       등록된 21개에 **outbox 는 없다** (Inbox · Sent · Drafts · Scheduled · Trash …)
```

그러므로 "JMAP 은 object, AS2 는 activity" 가 아니라 **"JMAP 은 둘을 나누고 AS2 는 합친다"**
가 정확하다. 우리 저장 구조는 JMAP 쪽이고, AS2 컬렉션은 그 위의 투영이다.

```txt
mailbox             활동 원장 wp_ax_activities
inbox               direction = 'inbound'
sent                direction = 'outbound'
EmailSubmission     ActivitySubmission -- 행위 이전의 의도. C3 의 pending intent
deliveryStatus      wp_ax_ap_deliveries (Bridge 소유)
AS2 outbox          저장소가 아니라 투영. repository.php:920 이
                    direction='outbound' AND effective_status='active' 로 뜬다
```

### 정밀하게 할 두 가지

**하나 -- inbox 는 membership 이고 sent 는 파생이다.** `direction='inbound'` 는 "이 Activity 가
도착했다" 는 Activity 자신의 사실이고, 누구의 inbox 에 있는지는 별도 테이블이 든다.

```txt
wp_ax_activity_inbox   (actor_identity_id, activity_id)
                       주석: "Actor-scoped receipt membership, independent of
                              transport and publication state"
```

JMAP 은 양쪽을 같은 방식으로 다룬다 -- `Email.mailboxIds` 가 Inbox 든 Sent 든 membership 이다.
우리는 **수신만 membership 이고 발신은 파생**이다. 하나의 수신 Activity 는 여러 Actor 의
inbox 에 들어갈 수 있지만, 발신 Activity 의 작성자는 `actor_uri` 하나뿐이기 때문이다.
이 비대칭은 결함이 아니라 authorship 이 단일값이라는 사실의 귀결이다. sent membership
테이블을 만들지 않는다.

**둘 -- ActivitySubmission 이라는 이름을 정직하게 만들려면 `activity_type` 이 필요하다.**
현재 pending 스키마에는 그 컬럼이 없고 Create 를 암묵 전제한다. 이름이 Activity 일반을
말하면서 Create 만 담으면 주장이 구현을 넘어선다. 값 하나를 더하는 비용으로 이름이
정직해진다. 지금은 항상 'Create' 다.

### direction 은 authorship 전용이어야 한다

`direction` 이 지금 세 값(`inbound` · `outbound` · `local`)을 갖는데, 이것은 두 축을
한 컬럼에 접은 것이다.

```txt
authorship   내가 한 일인가 받은 일인가     inbound ↔ outbound
locality     선 위에 올라가는가             remote ↔ local-only
```

`local` 은 **outbound ∧ local-only** 다. 로컬 수신자도 AP 상으로는 받는다 -- 같은 서버의
inbox 에 넣을 뿐이고, Activity 는 똑같이 작성자의 outbox 에 속한다. "S2S 트래픽이 나지
않는다" 는 전달 층의 사실이지 원장의 사실이 아니다. 전달 최적화가 authorship 어휘에
올라가 있다.

그래서 authorship 을 묻는 질의마다 `('outbound','local')` 을 기억해야 하고, 실제로 갈린다.

```txt
기억함   repository.php:788 · 858   피드 = ('outbound','local')
안 함    repository.php:495 · 920 · 734   'outbound' 만
```

**측정 (2026-10-26): 지금은 터지지 않는다.**

```txt
active local 행 5,256   전부 has_public_audience = 0
  Accept 1631 · Join 1292 · Invite 767 · Follow 755 · Undo 339 · Update 210 ·
  Reject 160 · Remove 35 · TentativeAccept 44 · Block 22 · Like 1
local 인 Create          0 건          ← 비어 있는 칸이고, 우연이다
outbox 가 떨어뜨리는 공개 local 행   0 건
```

`local` 이 붙는 행위가 전부 비공개 관계 행위라 공개 outbox 가 아무것도 잃지 않는다.
**로컬 전용 공개 Create 가 생기는 날 터진다** -- 포럼 로컬 커뮤니티 글이 후보다.

```txt
결정   direction 은 authorship 전용(inbound|outbound)으로 간다. locality 는 전달 층이 판단
순서   지금 하지 않는다. C3 와 principal 세대교체가 repository.php 를 점유 중이고,
       현재 데이터에 실제 결함이 없다
가드   그날을 잡기 위해 감사에 단언을 넣는다 --
       direction='local' AND has_public_audience=1 인 행이 0 이다.
       위반이 생기면 실패하고, 그 실패가 이 리팩터링의 착수 신호다
검증   이행할 때는 동작이 바뀌지 않아야 한다. outbox·피드를 이행 전후 전수 대조한다
```
