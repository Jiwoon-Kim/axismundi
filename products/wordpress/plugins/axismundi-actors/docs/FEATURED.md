# Person featured

## 범위와 소유

Person 전용 첫 curated collection이다. Group, moderators, featuredTags,
FEP-400e/7aa9, 관리자 컴포저 화면은 이 구현에 포함하지 않는다.

- Actors: `ax_actor_featured` 멤버십, `ax_actor_featured_state` 원격 동기화 상태.
- Activities: 로컬 Add/Remove의 인증·인가와 불변 Activity 기록.
- OP: `ap-featured` 계약, Actor의 `featured`, OrderedCollection 렌더와 GET.
- Bridge: 서명 검증된 수신의 `ax_featured_receipts`, 기존 발신 큐 사용.

범용 Collection 테이블이나 Forum 테이블을 사용하지 않는다. 로컬 원본과 원격
관찰은 Actor의 origin으로 구분하며 원격 목록에 대한 로컬 편집 경로는 없다.
자기 저자만 허용하는 규칙과 로컬 상한은 이 featured 모듈의 정책이며 일반
Collection의 불변식이 아니다. 공통 레지스트리에 저자 정책 필드는 추가하지 않는다.

## 로컬 저작과 공개

현재 Actor의 outbox POST에 `Add` 또는 `Remove`, `actor`, URI `object`,
해당 Actor의 featured URI인 `target`을 제출한다. 클라이언트 id는 채택하지 않고
서버 Activity id를 부여한다. `Idempotency-Key` 재사용은 원래 결과를 반환하며
후속 Remove를 취소하지 않는다. 원장과 멤버십은 같은 트랜잭션에 참여한다.

Person 자신의 공개적으로 읽을 수 있는 Object만 고정할 수 있다. 최대 5개이며
최근 고정부터 반환한다. 중복 Add는 멤버십·순서를 바꾸지 않는다. WP sticky나
글 작성 시각을 사용하지 않는다. 삭제가 확정된 Object는 공개 목록에서 빠지되
과거 Add 기록은 보존한다.

`GET /axismundi/v1/actors/{uuid}/featured`는 공개 Person에 대해서만 응답한다.
전량 URI를 담은 OrderedCollection과 실제 공개 가능한 totalItems를 반환한다.
비공개 Object를 URI로 대체해 노출하는 Mastodon 정책은 채택하지 않는다.
응답은 no-store이다. 공개 컬렉션이므로 인증 주체에 따라 다른 목록을 제공하거나
Public fetch에서 차단 대상의 열람까지 방지한다고 주장하지 않는다.

## 원격 수신과 재조정

서명 검증과 수신 Account의 moderation을 거친 Add/Remove에 대해 발신자가
Person이며 target이 그 Actor가 광고한 featured인지 확인한다. id가 없으면
원격 Activity를 만들지 않고 내부 수신 ID, 원문, 발신 Actor, 수신 시각, 결과만
보존한다. id가 있으면 기존 불변 원장 검증도 거친다. 다른 컬렉션의 정상적인
id 있는 Add/Remove는 기존 원장 경로를 유지한다.
Mastodon의 id 없는 Add/Remove에 관한 논의는
[upstream #30664](https://github.com/mastodon/mastodon/issues/30664)를 참고한다.
역참조 가능성과 식별자의 존재는 별개이며 우리 발신은 안정적인 id를 부여한다.

수신 신호는 멤버십을 낙관적으로 갱신하지 않는다. 5초 뒤 재조정을 예약하므로
오래된 Add가 Remove 뒤에 도착해도 기존 목록을 즉시 부활시키지 않는다.
수신 Activity만으로 원격 고정 순서를 추정하지 않는다.

전체 OrderedCollection을 최대 5개 페이지·100개 항목까지 읽고 orderedItems 순서를
보존한다. HTTPS 공개 URL, 무리디렉션, 요청당 10초·Actors 응답 크기 한계를
사용한다. 페이지의 id·partOf·동일 host/port, 중복·순환·totalItems 모순을 검사한다.
한도를 넘거나 페이지 조회·구조 검증이 실패하면 기존 멤버십을 보존한다.
완전한 목록을 읽었다면 URI와 순서는 항목 조회 성공 여부와 별개로 갱신한다.
각 행의 `resolution`은 ready / unresolved / excluded이고 오류 코드를 함께 남긴다.
타임아웃·인증 필요 등은 unresolved이며 삭제로 간주하지 않는다. 확정된 비공개·
삭제·저자 불일치는 excluded이다. 두 경우 모두 공개 표시에서는 제외하지만
원격이 광고한 URI를 관찰 기록에서 임의로 지우지 않는다. 다른 정상 항목은
계속 갱신하며 이후 재조회로 복구될 수 있다. 개별 Object는 OP의 기존 안전한
원격 조회로 검증하며 인라인 원문만 믿지 않는다.

동기화 중 새 신호가 오면 generation fence로 오래된 결과의 커밋을 막는다.
완료 후 1시간마다 재확인하고, 실패 또는 미해소 항목이 있으면 15분 뒤 재시도한다. WP-Cron 실행에
의존하며 인증된 fetch가 필요한 원격 컬렉션은 현재 가져오지 못한다.
기존 원격 Actor 전체를 자동 백필하지 않는다. Actor 발견·갱신 또는 수신 신호가
최초 동기화를 요청한다. 명시 실행은 다음과 같다.

```sh
wp axismundi featured sync https://remote.example/users/alice
```

## 검증

Forum 비활성 상태에서 실행한다. 실제 외부 서버와의 왕복 호환성을 주장하지 않는다.
수신 검사는 실제 RSA 서명을 사용하되 원격 HTTP는 테스트 응답으로 대체한다.

```sh
python tools/wp-env/run_audits.py run --audit audit-person-featured.php
python tools/wp-env/run_audits.py run --audit audit-person-featured-mutations.php
```

변이는 디스크를 수정하지 않고 함수를 독립 namespace에서 재정의한다. 각 변이의
동일한 무변이 대조군이 먼저 통과해야 하고, 실제 호출과 정확한 실패 단언을 확인한다.
대상은 큐레이션 권한, 순서 보존, 늦은 Add의 낙관적 재삽입 금지,
항목 하나의 실패가 완전한 멤버십 갱신을 막지 않는다는 규칙이다.
