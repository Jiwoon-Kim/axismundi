# DECISION — Group 연합 프로필과 지원 범위 계약

2026-11-02. 결정만 적는다. 논의 경위는 커밋 이력에 있다.

## 1. Group 의 연합 운영 프로필을 생성 시 고정한다

```txt
activitypub-group   표준 Group. 커뮤니티 게시 모델도 채널 소유 모델도 강제하지 않는다
community           Lemmy 계열 — 멤버 제출 + Group 의 Announce
channel             PeerTube 계열 — 채널 소유 관계 + Object 대상 Announce
```

레이아웃이 아니라 **계약**이다 — 귀속·제출·Announce 대상이 달라진다. 그러므로
`Add Local Actor` 에서 Type=Group 을 고르면 프로필을 함께 고르고, **생성 후
변경하지 않는다.** URI 를 유지한 채 프로필을 바꾸면 원격이 같은 Actor 를 다른
의미로 해석한다.

프로필 도입 전 Group만 예외적으로 [명시적 일회성 마이그레이션](COMMUNITY-MIGRATION.md)으로
미지정 상태에서 Community 계약을 부여할 수 있다. 자동 변환이나 일반 편집 허용이 아니다.
이미 지정된 프로필은 바꾸지 않으며 전환 후에도 같은 불변 가드를 적용한다.

표현은 별개다. `presentation_layout` 은 언제든 바꿀 수 있고, community 프로필을
영상 중심으로 보여주거나 channel 프로필을 게시판으로 보여줘도 된다.

```txt
federation_profile    생성 후 불변.  원격과의 계약
publication_policy    자동 · 수동 전환 가능
presentation_layout   자유
```

**"Lemmy 완전 호환"·"PeerTube 완전 호환" 이라고 쓰지 않는다** — 실제 왕복 검증
뒤에만 그렇게 쓴다.

## 2. 지원 범위 조회는 Axismundi API 계약이다

JMAP Session 의 capability 에 넣지 않는다. 이것은 Account 가 어떤 JMAP 기능을
쓸 수 있는가가 아니라, **어떤 Actor 타입·프로필에서 어떤 Activity 와 속성이
지원되는가**이기 때문이다.

```txt
어휘 정의        OP 가 아는 타입과 속성
구현된 동작      우리가 실제로 받는 것
프로필 제약      그 조합에 부과되는 규칙
사용자 권한      현재 주체가 이 Actor 로 할 수 있는 것
→ 넷을 구분하고 **실효 결과**를 반환한다
```

응답은 서버가 집행하는 정책의 **설명**이지 집행 자체가 아니다. 같은 규칙을
제출 시점에 다시 검사한다.

## 3. Group 제출 심사와 원본 Object 상태를 분리한다

`pending` 은 Note 의 전역 상태가 아니라 **그 Group 의 배포 결정 상태**다.
워드프레스 `post_status` 에 섞지 않는다.

```txt
1 단계   단일 Group 귀속으로 좁힌다
불변     그 귀속을 일반 Update 로 바꿀 수 없다.
         바꾸려면 별도의 명시적 이전 절차이거나 새 Object 로 재게시다
검증     UI 드롭다운 제한이 아니라 C2S 생성 · S2S 수신 · Update 검증 ·
         Announce 발행 모든 경로에서 같은 불변 조건을 적용한다
```

`audience` 가 Group 을 가리킨다는 것은 **제출 의도**이지 배송 보장이 아니다.
Group inbox 수신 증거가 따로 필요하다.

## 4. Announce 이력과 현재 게시 목록은 다른 것이다

수정을 배포하려고 옛 `Announce(Create)` 를 Undo 하지 않는다 — Undo 는 최초 배포
승인의 철회를 뜻하게 된다. 새 승인 Activity 를 더한다.

```txt
Outbox        무엇을 했는가.  Announce(Create) · Announce(Update) · Announce(Delete)
게시 컬렉션    무엇이 현재 소속되어 있는가
승인 결정      approved 와 announced 는 다르다 — 승인했으나 아직 못 보냈을 수 있다
전달 결과      수신처별로 다르다
```

Group 이 승인하지 않은 것은 **자기 팔로워에게 재배포하는 행위**이지 작성자에게
Note 를 만들 권리가 없다는 판정이 아니다.

## 5. 멤버십 관리 권한은 항목 자체의 권한이 아니다

```txt
Remove   컬렉션 관계의 생명주기
Delete   원본 리소스의 생명주기 — 소유 서버가 권위다
Undo     과거 행위의 효과 철회
```

셋을 하나의 내부 명령으로 정규화하지 않는다. 원격 Actor 를 moderators 에서
빼는 것과 그 Actor 를 삭제하는 것은 다르고, **원격 Remove·Delete 가 로컬
워드프레스 첨부를 삭제할 권한이 되지 않는다.**

moderators 에 포함된다는 것이 Account 운영 권한을 주지도 않는다. 공개 역할 ·
컬렉션 편집 권한 · Account 실행 권한은 별개다 — managers 에서 분리한 것을
여기서 다시 합치지 않는다.

## 6. 지금 하지 않는 것

```txt
범용 ax_collections 테이블   featured 와 moderators 를 각각 구현하고
                             패턴이 드러난 뒤 통합을 검토한다.
                             일급 리소스로 다루는 것과 공용 테이블은 별개다
Group 실행 코드를 Actors 에   Actors = 정체성 · 프로필 · 정책
                             Activities = 수신 처리 · 제출 심사 · Announce 발행
                             OP = 콘텐츠
C2S Page 저작                 C2S 저작은 Note. Article·CPT 는 전용 경로
FEP-400e · FEP-7aa9          후속 검토. featured 의 선행조건이 아니다
```

## 7. 실측해 둔 업스트림 사실

```txt
PeerTube     Video 를 직접 대상으로 한 Announce 를 문서화한다. 다만 공식 예시의
             actor 가 Person 이므로 "채널 Group 이 Announce 한다" 까지는 문서만으로
             확정되지 않는다 — 발신 코드 확인이 필요하다
FEP-7aa9     Draft 2026-06-12.  구현 목록 Loops · Mastodon · Pixelfed.
             featuredCollections · FeaturedCollection · FeaturedItem ·
             FeatureRequest · FeatureAuthorization · interactionPolicy.canFeature
             **기존 고정글 featured 를 언급도 변경도 하지 않는다**
             동의가 필수이고 **정책만으로는 동의가 아니다**
공식 WP 플러그인  저장이 사이트 전역 sticky_posts 이고 조회 때 작성자로 거른다.
             정렬은 글 작성일 내림차순 (Mastodon 의 고정 시각순과 다르다).
             Outbox 저장 단계에서 Activity id 를 부여한다 — 우리 발신 경로에
             가까운 참고 사례다.  로컬 featured 의 공개·편집·발신 지원이고
             원격 featured 캐시·동기화를 뜻하지 않는다
```
