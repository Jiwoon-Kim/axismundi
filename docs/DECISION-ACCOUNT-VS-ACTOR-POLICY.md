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
