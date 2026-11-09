# WP 소스의 현재 발행 귀속

## 경계

post와 attachment의 현재 attributedTo는 공통 postmeta
`_ax_op_attributed_to`에 저장한다. post_author는 편집·업로드 사용자이고,
미디어 권리의 creator·라이선스도 별개다. 서로의 폴백으로 쓰지 않는다.
OP는 메타를 직접 읽고 기존 attribution 필터는 확장점으로 유지한다.
Activities가 꺼져도 현재 귀속은 사라지지 않는다.

공동귀속은 반복 postmeta `_ax_op_co_attributed_to`로 별도 저장한다. 공개 attributedTo는
단일 값이면 문자열, 복수이면 전체 배열이다. `_ax_op_attributed_to`는 명시적 로컬
발행 계정 1개이며 공동귀속 목록의 첫 항목에서 권위를 추론하지 않는다. 출력에서 주 계정을
앞에 두는 것은 표시 관례일 뿐이다. 입력 집합은 정규화·중복 제거하며 주 계정 포함 최대 32개다.
공동 URI는 원격도 허용하지만 발행 권한이나 상대방의 승인을 부여하지 않는다.

`axismundi_op_set_post_attribution(postId, actorUri, userId, collaborators)`의 네 번째
인자는 공동귀속 목록이다. 생략하면 기존 목록을 유지하고 빈 배열이면 목록을 지운다.
공동 목록만 바뀌어도 발행된 객체는 Update를 만들고 옛 Create를 보존한다. 같은 집합의
순서만 바뀌면 새 lifecycle 사건을 만들지 않는다. 원격 동의 프로토콜은 구현하지 않았다.

Object 레지스트리 입장은 발행 사건의 증거가 아니다. Core 소스의 실제 URI·타입·
source_ref·현재 귀속이 일치해야 하지만 Create가 없어도 등록할 수 있다.
attachment는 Core가 일반 save_post 전에 반환하므로 add_attachment와
edit_attachment도 색인 갱신과 저장 경로에 연결한다.

## 변경

편집 화면의 Publishing Account 상자와
`axismundi_op_set_post_attribution(postId, actorUri, userId)`가 같은 서비스를 쓴다.
WP edit_post 권한과 대상 로컬 활성 Actor의 can_act_as 권한을 각각 검사한다.
일반 REST meta 쓰기는 열지 않는다. 임의의 Account URI를 meta 권한만으로
주입할 수 없도록 show_in_rest=false이며, 서버 통합은 명시 서비스로 변경한다.

Create 없이 먼저 지정할 수 있다. 귀속 필드만 바꾸는 저장은 첫 Create를 만들지 않는다.
이미 발행한 객체의 A→B 변경은 현재 문서를 Update로 기록한다.
옛 Create payload는 수정하지 않는다. Update가 실패하면 이전 메타로 되돌린다.
원격 처리·수렴은 보장하지 않는다. 전달이나 수신 처리가 실패한 경우 재조회가
현재 상태를 읽는 복구 경로가 될 수 있으나 자동 재조회는 구현하지 않는다.
이미 발행한 객체의 귀속을 빈 값으로 지우는 동작은 현재 409로 거부한다.

Activities의 새 Create/Update 후에는 실제 Object.attributedTo를 메타에 반영한다.
Object가 URI뿐인 Create는 검증된 Create.actor를 사용한다.
URI뿐인 Update.actor는 Object 귀속의 근거로 사용하지 않는다.
이 sink는 커밋 후 실행되며, 메타 쓰기 실패는
axismundi_act_post_attribution_write_failed로 알린다. 커밋된 원장을 되돌리지는 않는다.

C2S 로컬 JSON 객체의 acting actor 귀속 강제와 Note Update 허용 목록은 이번에
일반화하지 않았다. 이는 현재 구현 범위이며 영구적인 프로토콜 정책이 아니다.
JMAP Account/blob 접근 권한을 이 메타 하나로 결정하지 않는다.

## 권위와 수신자 소비 경로

Activities의 `axismundi_act_object_publisher_uri()`는 OP의 로컬 레지스트리 권위를
사용한다. 알려진 주 계정과 모순된 단일 귀속도 권위로 승인하지 않는다. OP가 없거나
로컬 근거가 없는 소스는 단일 귀속일 때만 해소한다. 원격 다저자는 첫 원소나 활동의
actor 포함 여부로 권위를 선택하지 않고 미해소로 처리한다. 이 제한은 승인 FEP 미구현과
구분한다. AS2 복수 표현은 허용되지만 원격 발행 권위를 확정할 증거를 이 경로에서
아직 사용하지 않는다는 제품 한계다.

| 질문 | 사용 값 | 소비 경로 |
| --- | --- | --- |
| 누가 발행했는가 | 독립적으로 해소한 publisher URI | Forum 제출·직접 송출, Community 수락, Question 저자·투표 판정, QuoteRequest instrument |
| 누구에게 알릴 것인가 | 전체 귀속 URI 집합 | Like·Dislike·EmojiReact·Announce resolver와 REST 호출부·기록 함수 |
| 원래 귀속을 어떻게 표현하는가 | 원본 scalar/list 그대로 | likes·shares·emojiReactions·replies 루트/페이지 |

알림 target의 새 `recipient_uris`는 전체 집합이다. 기존 `recipient_uri`는 명시적 권위를
알 때만 주는 스칼라 호환 힌트이며, 새 호출부는 복수 목록을 우선한다. 각 수신 Actor가
현재 레지스트리에 있어야 하며, 해소되지 않은 수신자를 조용히 누락하거나 이 서비스에서
원격 fetch하지 않는다. 같은 URI는 중복 제거한다. 자기 수신자 제외 및 자기 반응의
followers 주소 정책은 기존 동작을 유지한다. Forum 투표는 기존처럼 Community Group
주소가 우선이며 Group이 없을 때 전체 저자 목록으로 폴백한다.

구 Note CPT의 부모 Reply 어댑터도 목록 해석을 제공하지만 Note 플러그인의 hard cutoff로
현재 운영 런타임에서는 도달하지 않는다. 감사를 위해 그 런타임을 되살리지 않았다.
현재 C2S Community reply는 이미 부모 귀속 전체를 cc로 포함한다.

## 기존 데이터

자동 활성화 백필은 없다. 아래 명령은 기본 읽기 전용, 배치 최대 500건이다.

```sh
wp axismundi attribution --after=0 --limit=500
wp axismundi attribution --after=0 --limit=500 --apply
```

다음 배치는 응답 next를 after로 사용한다. 여러 귀속 주장이 충돌하거나 Actor가
해소되지 않으면 보류한다. 기존 메타와 다르면 덮어쓰지 않는다. 아무 근거가 없으면
post_author 등으로 추정하지 않는다. 명시 apply에서 안전 후보의 메타와 색인을 쓰고,
충돌하지 않은 attachment 소스는 Create 없이 등록한다.

2026-11-08 로컬 wp-env 감사: post 718 + attachment 25 = 743건.
안전 후보 17, 충돌·Actor 해소 불가 355, 근거 없음 371이었다.
wp_postmeta·wp_ax_objects·wp_ax_object_index를 백업한 뒤 안전 17건만 적용했다.
메타 쓰기 오류와 등록 오류는 0이었다. 이는 로컬 픽스처 상태이며 운영 재고가 아니다.
스테이징은 변경하지 않았다. 보류 355건의 자동 이관을 완료했다고 보지 않는다.

## 검증

- Activities audit-post-attribution-meta.php: 27개.
- OP audit-attribution-without-activities.php: Activities와 Bridge를 실제 skip한 6개.
- OP audit-local-attribution.php: 현재 postmeta 계약 5개.
- 기존 C2S Note 35개, 발행 선택 24개, 즉시 발행 27개, 예약 발행 41개도 통과했다.
- 브라우저 attachment 편집 저장: attributedTo 저장, Activity 0, 업로드 User 유지.

JMAP HTTP·Session·런타임 137개와 합쳐 이번 회귀 묶음은 302개다.
실행 출력과 UI 증거는 _dist/attribution-jmap-20261108/에 보관한다.

실행 코드는 미커밋 개발 변경이다. 공개 SVN 버전과 심사 ZIP은 다시 배포하지 않았다.

2026-11-09 증분 검증:

- `audit-joint-attribution.php` 34개: 두 번째 구성원이 등록 publisher인 정상 사례,
  첫 공동귀속자의 권한 오인 거부, 미해소 원격 배열, 실제 네 상호작용의 복수 수신자,
  컬렉션 5개 배열 보존, 공동 목록 변경 Update와 Create 불변, 실제 Forum 제출 판정.
- Forum은 설치된 소스 서비스만 명시적으로 로드해 검사하며 플러그인을 활성화하지 않는다.
  위 감사는 Bridge 외부 배송 큐 훅을 일시 해제하고 종료 시 복구하며 외부 HTTP 배송을 하지 않는다.
- 어휘 레지스트리 23개, 컨텍스트 43개, C2S 로컬 Note 45개: 어휘 선언과 기존 제품 정책을
  분리해 기록했다. 세부 내용은 [어휘 의미와 C2S 정책](VOCABULARY-SEMANTICS.md) 참조.
- 기존 postmeta 27개·Question 16개·QuoteRequest 11개·outbound quote 12개,
  Like/Dislike 14개·votes 7개·Announce 8개·EmojiReact 39개·반응 mutation 26개도 재실행했다.
  테스트의 post_author 귀속 가정과 중복 블록 등록 경고는 명시적 픽스처로 수정했다.
- 브라우저의 새 공동귀속 입력 UI와 외부 피어 호환성은 이번 명령 서비스 감사의 증명 범위가 아니다.
- Forum 전체 소스를 CLI 요청 안에서 로드한 송출 감사는 12/12 통과했다.
  커뮤니티 core 감사는 6/8로, 소유자 멤버십 투영과 커뮤니티 관리 화면 단정이 실패했다.
  부분 로드 때의 미정의 함수는 전체 로드로 해소됐지만 두 단정은 남았다.
  기존 실패인지 이번 변경의 영향인지는 분리하지 못했으므로 전체 Forum 회귀 통과로 보지 않는다.
