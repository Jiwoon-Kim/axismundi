# Community Moderators

현재 구현은 로컬 `Group + community`의 curated Actor 멤버십과 원격 Group의 귀속 컬렉션 관측이다.
Forum 테이블, principal 부여, Follow 관계를 읽거나 쓰지 않는다. Channel은 상속하지 않는다.

## Local Authority

최초 초기화는 Group과 지정한 Person 양쪽으로 발화할 수 있는 사용자가 명시적으로 수행한다.
생성자·소유자·현재 쿠키에서 Person을 추론하지 않는다. 운영자가 임의의 타인을 첫 moderator로
지정하지 못하게 제한한 것은 이번 로컬 정책이며, AS2 요구사항이 아니다.

POST `/wp-json/axismundi/v1/actors/{group-uuid}/moderators/initialize`
본문은 `{ "person": "Person의 canonical URI" }`. 기존 C2S 인증(App Password 또는 REST nonce)을
사용한다. 빈 멤버십과 초기화 이력이 없는 Group에서만 가능하다. 일반 메타 편집이나 principal
변경을 통해 moderator가 자동 생성되지 않는다.

이후 Person outbox의 Add/Remove는 기존 moderator만 저작할 수 있다. 높은 순위의 moderator를
낮은 순위가 해임할 수 없고 마지막 moderator도 해임할 수 없다. 이는 이번 Community 정책이다.
재초기화·소유권 이전·긴급 복구 UI는 제공하지 않는다.

로컬 멤버십과 mod log는 Person Add/Remove와 같은 SQL 트랜잭션에 기록한다. 로그 실패는
멤버십까지 롤백한다. Group Announce는 커밋된 로그를 확인한 뒤 안정적인 source key로 별도
기록한다. Announce 생성 실패 시 이미 커밋된 결정을 취소하지 않으며 같은 요청을 재시도한다.
반복 Add는 기존 임명 시각을 바꾸지 않는다. 로컬 순서는 실제 임명 시각 오름차순이며 동률은
멤버십 행 ID로 정한다. 과거 Add 재시도는 후속 Remove를 되돌리지 않는다.

수신된 직접 Person Add/Remove도 ②를 거쳐 같은 writer로 들어간다. 서명된 Person이 기존
moderator인지 확인하며 같은 도메인이라는 이유로 통과시키지 않는다. moderator가 되어도
Group으로 발화하는 principal 권한이 생기지는 않는다.

## Remote Observation Policy

관리자 채택 게이트는 없다. 원격 Group 자신의 `attributedTo`가 가리키는 단일 URI(또는
명시적 Collection 참조)를 관측한다. 다른 origin의 참조는 거부하고, 실제로 조회한 응답이
동일 ID의 완전한 OrderedCollection인지 확인한다. 플랫폼 이름·NodeInfo로 분기하지 않는다.
타입이 붙은 Person/Group 배열은 원본 귀속 데이터로 보존하며 moderators로 자동 변환하지 않는다.

이것은 원격 귀속 컬렉션을 moderator 목록으로 사용하는 **우리의 상호운용 정책**이다.
AS2 attributedTo 자체가 moderator 권한을 정의한다는 뜻도, FEP-fe34 채택 선언도 아니다.
원격 목록은 그 원격 Group의 역할 관측이며 로컬 Group 권한이나 principal로 승격하지 않는다.

발견·갱신 알림에서 동기화를 요청한다. 미확인 컬렉션의 Add/Remove는 증거로 수신되고,
역할 목록을 검증하기 전에는 unclaimed로 유지된다. 완전 스냅샷이 확인된 뒤 Group Announce는
재조회 신호로만 사용한다. 늦은 Add를 낙관 적용하지 않으며 generation fence가 진행 중인
오래된 스냅샷 커밋도 막는다. 원격 직접 Person 신호는 관측된 기존 moderator만 보낼 수 있다.

구조 실패·불완전한 페이지·중복 URI는 전체 교체를 보류한다. 이 슬라이스의 원격 reader는
페이지 없는 inline OrderedCollection, 최대 1000개를 지원한다. 정상적인 빈 컬렉션은 비운다.
각 항목은 ready/excluded/unresolved로 보존한다. 인증 필요·일시적 조회 실패는 unresolved이며,
캐시된 Person 사본이 있어도 표시와 역할 판단에서 제외한다. 확정된 비-Person은 excluded다.

원격 임명 시각은 와이어에 없으므로 만들지 않는다. `appointed_at=NULL`로 두고 원격
`orderedItems` 순서를 position으로 보존한다. 로컬 임명 시각과 원격 관측 순서를 혼동하지 않는다.

## 서열 규칙은 Lemmy 관행이다

임명 시각 오름차순 서열, "상위 moderator 를 건드릴 수 없다", "마지막 moderator 는 스스로 빠질 수
없다" 는 **어느 스펙에도 없는 Lemmy 관행**이다. 지금은 이 구현 전체가 `is_community` 게이트
뒤에 있어 Community 프로필로 격리돼 있으나, 나중에 범용 curated collection 이 생기더라도
**그것을 컬렉션 일반의 성질로 상속시키지 말 것.** 컬렉션에 순서가 있다는 것과 그 순서가 권한
서열이라는 것은 다른 주장이다.

## Wire And Boundaries

### Community moderation removal (decision, not implemented)

다른 Actor가 저작한 커뮤니티 게시물도 해당 커뮤니티 moderator가 내릴 수 있다는 정책을
채택한다. 일반 Object 삭제 권한으로 올리지 않으며 Community 상호운용 계약에 격리한다.
Channel이나 audience 없는 개인 게시물로 이 권한이 확장되지 않는다.

후속 구현의 조건은 다음과 같다. 현재 이 조건들을 집행하는 removal 경로는 없다.

- 대상 Object의 확인된 표현에 `audience`가 있어야 하고, 그 값들에 해당 Group URI가
  포함되어야 한다. Delete/Announce 자체에 새로 적힌 audience로 대상의 소속을 만들지 않는다.
  audience가 없거나 다른 Group만 가리키면 거부한다. `cc`나 같은 origin으로 대체하지 않는다.
- 내부 행위자는 그 Group의 확인된 현재 moderator여야 한다. Group 포장과 내부 Person의
  저작 증거를 구별하며, 검증되지 않은 Person Activity를 만들어내지 않는다.
- 대상이나 역할이 미해소 상태라면 삭제 권한이 확인된 것이 아니다. 조회 실패로 삭제하거나
  멤버십을 지우지 않으며, audience 부재가 확인된 경우와 미확정을 구별한다.
- 기존 저자 자신의 Delete 경로와 이 추가 moderation 권한을 구별한다.

**소스 독해 정정:** Lemmy moderator removal은 와이어에서 `Announce(Delete)`를 사용하지만
저자 Delete와 같은 저장 효과가 아니다. `Delete.summary`가 `Some`이면
`receive_remove_action()`으로 가서 `removed=true`를 기록한다. summary가 없으면
`receive_delete_action()`에서 `deleted=true`를 기록한다. 사유 없는 moderator removal도
빈 문자열 summary를 사용해 구분할 수 있다. 따라서 "moderator Delete가 항상 deleted를
쓴다"는 이전 대화의 결론은 채택하지 않는다. Undo 역시 summary 유무로 removal 복구와
저자 삭제 복구를 나눈다. 로컬 원본 파괴나 저자 Tombstone 생성을 이 결정에서 도출하지 않는다.

확인한 Lemmy revision: `f400a88017203b8f942c4e3d3e0680a9deb5b806`.
[Delete 처리](https://github.com/LemmyNet/lemmy/blob/f400a88017203b8f942c4e3d3e0680a9deb5b806/crates/apub/activities/src/deletion/delete.rs),
[저자 삭제 처리](https://github.com/LemmyNet/lemmy/blob/f400a88017203b8f942c4e3d3e0680a9deb5b806/crates/apub/activities/src/deletion/mod.rs),
[Undo 처리](https://github.com/LemmyNet/lemmy/blob/f400a88017203b8f942c4e3d3e0680a9deb5b806/crates/apub/activities/src/deletion/undo_delete.rs).
실제 상대 Lemmy의 배포 버전 및 왕복 효과는 여전히 미검증이다.

GET `/wp-json/axismundi/v1/actors/{group-uuid}/moderators`는 Community만 공개한다.
`OrderedCollection`의 orderedItems에 Person URI를 싣고 페이지·totalItems는 내보내지 않는다.
공개 Community Actor 문서는 Actors 소유 moderators 컬렉션 URI를 attributedTo로 송출한다.
빈 컬렉션도 유효하다. Channel과 프로필 미지정 Group에는 적용하지 않는다.
기존 Group의 명시적 전환과 Forum URL 하드 전환은 [COMMUNITY-MIGRATION.md](COMMUNITY-MIGRATION.md)를 따른다.
OP 계약은 Actors 소유, `member_models=['Actor']`다.

Ban 해석·집행·알림 UI는 이 슬라이스가 아니다. moderators 목록이 없어도 Group의
Announce(Block) 자체는 수신·기록된다. 내부 Person Block을 만들어 내지 않는다.
관계 엔진의 target-scoped Block 가드는 그대로 유지한다. target의 긍정적 의미는 미래의
Community/Lemmy 어댑터가 담당하며, 개인 차단이나 PeerTube 정책으로 일반화하지 않는다.

원격 Group으로 Add/Remove를 저작하는 UI, moderator 관리 페이지, Group featured,
Create(Page)/답글 중계는 후속 범위다. 실제 Lemmy 서버와의 왕복은 아직 검증하지 않았다.

## Verification

```sh
python tools/wp-env/run_audits.py run --audit audit-community-moderators.php --audit audit-community-moderators-mutations.php --audit audit-collection-controls.php --audit audit-collection-controls-mutations.php --audit audit-collection-contracts.php
```

Actors tests/는 멤버십·초기화·순서·원격 재조정을, Bridge tests/의 ② 감사는 서명·전달 증거를
검사한다. 기존 principal/관계 불변 단언은 유지한다. Forum 비활성에서 실행한다.
