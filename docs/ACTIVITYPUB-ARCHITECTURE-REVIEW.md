# Axismundi 연합 모델 검토 (ActivityStreams 2.0 / ActivityPub)

> 목적: Actors · Activities · Object Projections · ActivityPub Bridge 네 플러그인이
> **표준의 기반 개념을 빠짐없이 담을 수 있는 모델인지** 판정한다. 기능 목록 점검이 아니라
> 데이터 모델·도메인 모델 검토다.
>
> 시작 2026-09-20. 축마다 대조가 끝나면 이 문서에 확정본을 적는다. 진행 중인 축은
> "미착수"로 남겨 두고, 추정으로 채우지 않는다.

## 1. 범위와 출처

**검토 대상 (버전은 검토 시점)**

| 플러그인 | 버전 | 이 검토에서의 역할 |
|---|---|---|
| Axismundi Actors | 0.1.2 | 정체성 레코드, handle, WebFinger, 원격 actor 관측 |
| Axismundi Activities | 0.1.0 | Activity 원장, 관계 상태 |
| Axismundi Object Projections (OP) | 0.1.0 | AS2 JSON-LD 표현, URI 소유, `@context` |
| Axismundi ActivityPub Bridge | 0.1.0 | 검증된 Inbox 소비, 전송 필드, 배달 |

**규범 출처 (원문을 읽고 인용한다)**

- ActivityStreams 2.0 Core, ActivityStreams 2.0 Vocabulary
- ActivityPub (특히 §5 컬렉션, §6 부작용, §7 전달과 forwarding)
- RFC 7033 WebFinger, RFC 7565 `acct:`
- RFC 9421 HTTP Message Signatures — **현재 표준값**
- draft-cavage HTTP Signatures — **실제 상호운용 프로파일**(표준값과 분리해 적는다)
- JSON-LD 1.1, `w3c/ns`의 ActivityStreams 네임스페이스
- NodeInfo 2.0 / 2.1 스키마 — 서버 capability 층(정체성 모델과 별도 박스)
- FEP: 번호·제목·상태를 **기억으로 쓰지 않고** Codeberg `fediverse/fep` 원문에서 확인한다

**상호운용 프로파일 (스펙이 아니라 "실제로 통하는 것")**

Mastodon, Misskey, Lemmy, GoToSocial. 우리와 이미 접점이 있다: Lemmy는 Forum,
Misskey는 이모지 리액션과 embedded url scalar, Mastodon은 sensitive·인용.

## 2. 판정 범례

| 판정 | 뜻 |
|---|---|
| **담고 있음** | 표준 개념이 모델에 자리를 갖고 있고 코드가 그 자리를 쓴다 |
| **부분적** | 자리는 있으나 계약이 일부만 구현됐다. 무엇이 빠졌는지 적는다 |
| **공백** | 모델에 자리가 없다. 나중에 넣을 수 있는지까지 적는다 |
| **의도적 비표준** | 표준과 다르며 그 이유가 있다. 되돌리지 말 것 |

"의도적 비표준"은 버그가 아니다. 상호운용 shim인지, 실험인지, 장기 확장점인지 구분해 적는다.

## 3. 이 검토에서 먼저 고정한 결정

세 가지는 검토를 시작하기 전에 정했다. 축별 판정이 이 결정을 뒤집을 수는 있지만,
뒤집으려면 근거를 적어야 한다.

1. **서명은 직접 구현하지 않는다.** canonicalization, digest, clock skew, key lookup,
   rotation, RFC 9421과 draft-cavage 동시 지원까지 전부 따라온다. 우리는 *무엇을 보낼지*를
   소유하고, 공식 ActivityPub 플러그인이 *어떻게 안전하게 보낼지*를 소유한다.
   대신 **의존 계약을 검증한다**: 공식 플러그인이 outbound에서 어느 방식을 쓰는지, inbound에서
   둘 다 검증하는지, 키 회전을 어떻게 다루는지, 서명 실패를 어떻게 노출하는지.
2. **JSON-LD 프로세서는 런타임에 넣지 않는다.** 현재 구조는 PHP 배열 → 알려진 compact key
   직접 생성 → renderer가 `@context` 단독 조립이다. 런타임 프로세서는 원격 context fetch,
   캐시, SSRF, 예기치 않은 compaction까지 끌고 온다. **검증 도구로는 쓴다**: 테스트에서
   출력 JSON을 표준 프로세서에 넣어 expand 오류, 기대 IRI 매핑, term 충돌, 왕복 보존을 본다.
3. **`@context` 조립은 두 층의 계약이다.** 의미론(이 property가 무슨 IRI인가)과
   wire format(상대 구현체가 실제로 이 키 이름을 읽는가). 두 층을 각각 판정한다.

## 4. A축 — 정체성 (대조 완료)

### 4.1 모델

```text
WP_User (로그인 계정)        ← Person actor만 연결, Site/Group/Service는 없음
   ↕ local_user_id
identity/actor DB row id     ← 구현 세부, 플러그인 경계를 넘지 않음
   ↕
uuid                         ← 불변 앵커
   ↓ 파생
actor_uri = /actors/{uuid}   ← canonical AS id (plain fallback /?ax_actor={uuid})
   ↓ 별개
handle / preferredUsername   ← 사람용 이름, 식별자가 아님
```

### 4.2 판정

| 항목 | 표준이 요구하는 것 | 우리 모델 | 판정 |
|---|---|---|---|
| Canonical identity | actor `id`는 영구 식별자 | uuid가 불변 앵커, `actor_uri`는 uuid 파생. DB id는 플러그인 경계를 넘지 않고 Activities·Bridge 모두 canonical URI만 주고받음 | 담고 있음 |
| Discovery | WebFinger `acct:` → actor `id` | Actors가 WebFinger를 소유하고 Bridge가 `rel=self`를 추가 | 담고 있음 |
| Local name | `preferredUsername`은 표시 이름이지 식별자가 아님 | handle과 `actor_uri`가 독립. `repository.php:903`이 로컬 handle 규칙과 AS `preferredUsername`(문자 규칙 없음)을 일부러 분리한다고 적고 있음 | 담고 있음 |
| Actor type | Person / Group / Organization / Service / Application | 로컬 Person·Site, managed Group·Organization. 원격은 허용 타입 검사 후 저장 | 담고 있음 |
| Keys | 공개키 노출, 소유자 확인, 회전 | Bridge가 `inbox` + `endpoints.sharedInbox` + `publicKey`를 **원자 묶음**으로만 주입(`transport.php:93`). 키를 못 만들면 셋 다 생략. WebFinger `rel=self`도 같은 게이트(`transport.php:120`) — 키 없는 actor를 남이 캐시하지 못하게 fail-closed. 키 자체는 공식 플러그인 저장소 소유 | 담고 있음 |
| Dereferencing | actor `id` GET이 AS 표현을 내야 함 | OP가 Actor JSON-LD를 소유하고 URL 하나에서 content negotiation. 전송 필드는 `axismundi_op_actor_transport_fields` 필터로 Bridge가 주입. Actors + OP만으로도 표현이 성립한다(§5.9) | 담고 있음 |
| Aliases | `url`, 프로필 URL, handle의 구분 | 사람용 handle URL과 canonical `actor_uri`가 분리돼 있음 | 담고 있음 |
| 원격 actor 수용 | payload `id`와 발견 경로의 일치 | WebFinger self URI = payload `id`, HTTPS endpoint, 허용 타입, key owner 확인 후 저장 | 담고 있음 |
| Migration | `Move`, `alsoKnownAs`, 이전 URI 처리 | `Move`는 Activities 타입 목록(22종)에 있음. `alsoKnownAs`/`movedTo`는 **관측값으로만** 저장(`remote-discovery.php:254`). 공식 `handle_move`는 composition에서 dormant. 로컬 도메인 이전, 상호 검증, outbound `Move` 배달은 구현 계약이 아님 | 부분적 |

### 4.3 A축 결론

기반은 잘 잡혀 있다. WP 계정 / DB id / uuid / canonical URI / 사람용 handle의 다섯 층이
실제로 분리돼 있고, 키 없는 actor를 외부에 캐시시키지 않으려는 fail-closed 경계가 있다.

**명시적 후속 계약은 하나다.** uuid가 살아 있어도 **도메인 이전 뒤 기존 actor URI를 이어 주는 일은
자동으로 풀리지 않는다.** `Move` 발신, 이전 URI의 redirect/보존 정책, 상호 검증은 연합 단계의
별도 설계 항목으로 남긴다. 이는 숨은 공백이 아니라 문서에도 future로 적힌 것이다.

### 4.4 NodeInfo와 인스턴스 정보의 소유 (결정)

NodeInfo는 Actors가 소유한다(`axismundi-actors/includes/nodeinfo.php`). 공식 플러그인은 이것을
어느 층에도 붙이지 않고 `rest/class-nodeinfo-controller.php`라는 독립 컨트롤러로 둔다. 우리도
같은 판단을 하되 문서 생산은 Actors에 둔다. 이유는 셋이다.

- 문서가 담는 `usage.users`는 `wp_ax_identities`를 직접 세는 값이다. 밖으로 빼면 다시 Actors에
  물어야 한다.
- NodeInfo는 **연합하지 않는 사이트도 내는 문서**다. Bridge에 두면 "우리는 ActivityPub을 하지
  않는다"고 말할 주체가 사라진다. Bridge는 공식 플러그인이 있어야 존재하고, REST 라우트를 0개
  갖는 것을 자기 계약으로 삼고 있다.
- 호스트 정체성은 결국 actor 하나로 수렴한다. Actors는 이미 site scope actor를 갖고 있다.

**층은 문서 안에서 나눈다.** Actors가 문서와 집계를 소유하고, **능력은 능력 소유자가 선언한다.**
`axismundi_actors_nodeinfo_protocols`는 그 자리이고, Bridge가 전송 claim(§5.9)이 설 때
`activitypub`을 채운다. 다른 transport가 생기면 같은 자리로 들어온다.

**분리 조건**(충족되면 `axismundi-instance` 같은 층으로 옮긴다):

1. 인스턴스 수준 표면이 둘 이상이 될 때 — 정책 목록, NodeInfo 선언, host-meta, FEP-67ff
   `FEDERATION.md`, 공개 모더레이션 정책 등.
2. 정체성과 무관한 소비자가 그 정보를 필요로 할 때.
3. Actors 없이 인스턴스 정보를 내야 할 때.

조건을 적어 두는 이유는 "언젠가 옮길까"를 "조건이 서면 옮긴다"로 바꾸기 위해서다. 표면 하나
때문에 플러그인을 늘리면 연결해야 할 경우의 수만 늘어난다.

## 5. C축 — Activity 부작용 (대조 완료)

직렬화 가능 여부가 아니라 **무슨 내부 상태를 바꾸는가**로 본다.

### 5.1 수신 구조

Bridge의 inbound는 **타입에 무관하다.** 서명이 검증된 Activity는 공식 플러그인의
`activitypub_inbox` / `activitypub_inbox_shared` 액션에서 그대로
`axismundi_act_record_activity( $activity, 'inbound' )`로 원장에 기록된다
(`axismundi-activitypub-bridge.php:216`). Bridge에서 타입을 보는 유일한 지점은 원격 Actor
`Update` 하나다(Person/Organization/Application/Service/Group). 공식 핸들러 25개는
composition에서 dormant 처리된다.

```text
검증된 Inbox Activity
  → Bridge: 대상 해석 + 원격 Actor 확인 → 원장 기록(타입 무관)
  → axismundi_act_activity_recorded 훅
      ├─ Activities: 관계 상태 파생(Follow/Accept/Reject/Undo/Block)
      ├─ OP: 원격 객체 관측·Tombstone·Group Announce 해제
      └─ 제품 플러그인(Forum 등): 자기 도메인 규칙
```

**부작용을 "수신 핸들러"가 아니라 "원장에서 파생"으로 뒤집은 구조**다. 같은 사실을 두 번
기록하지 않고, 재계산이 가능하다는 장점이 있다. 대가는 부작용이 여러 플러그인의 훅으로
흩어져 한곳에서 전체를 볼 수 없다는 점이다.

### 5.2 타입별 판정

| 타입 | AP가 요구하는 부작용 | 우리 구현 | 판정 |
|---|---|---|---|
| `Create` | 활동과 객체의 표현을 로컬에 저장 | 원장 기록 + OP `axismundi_op_observe_inbound_object()`가 원격 객체 투영 저장 | 담고 있음 |
| `Update` | 객체 사본 갱신(권한 확인 후) | 원격 Actor는 Bridge가 `axismundi_actors_apply_remote_actor_update()`로 반영. 원격 객체는 관측으로 갱신 | 담고 있음 |
| `Delete` | 표현 제거 또는 Tombstone 교체 | `axismundi_op_observe_inbound_object_delete()`가 **검증된 작성자**의 삭제일 때 캐시를 Tombstone으로 교체. 링크와 스레드가 "삭제됨"을 말할 수 있게 남김 | 담고 있음 |
| `Follow` | `Accept` 또는 `Reject`를 생성해 전달 | `axismundi_act_maybe_auto_accept_inbound_follow()`가 `axismundi_act_relation_changed`에서 자동 Accept. 승인 요구 설정이면 pending으로 남김. **Group이면 자동 Accept하지 않고** 제품(Forum)의 가입 규칙에 넘김 | 담고 있음 |
| `Accept` | Follow면 following 컬렉션에 추가 | `axismundi_act_reconcile_follow()`가 관계를 accepted로 전이 | 담고 있음 |
| `Reject` | following에 추가하면 안 됨 | 같은 경로에서 rejected로 전이 | 담고 있음 |
| `Add` | `target` 컬렉션에 객체 추가 | **일반 계약 없음.** `Add`/`Remove`는 타입 목록에 있고 `target` 필수 검증도 있으나(`repository.php:984`), 수신 시 컬렉션 멤버십을 바꾸는 경로가 없다. 현재 유일한 사용처는 Forum 모더레이터 지정(`moderators.php:65`) | 공백 |
| `Remove` | `target` 컬렉션에서 제거 | 위와 같음 | 공백 |
| `Like` | likes 컬렉션에 추가해 집계 | OP `integrations/reactions.php`가 `likes` 컬렉션 URL과 집계를 제공, Activities가 원장에서 센다 | 담고 있음 |
| `Announce` | shares 컬렉션에 추가해 집계 | `shares` 컬렉션 + 집계. 추가로 `axismundi_op_unwrap_inbound_group_announce()`가 Group Announce를 열어 내부 Create를 꺼낸다(threadiverse 경로) | 담고 있음 |
| `Undo` | 이전 활동의 부작용 되돌리기 | `axismundi_act_recompute_effectiveness()`가 `effective_status`만 `undone`으로 바꾸고 **원 payload는 불변**. 같은 actor의 Undo만 인정하고 재귀 깊이 16 제한 | 담고 있음 |
| `Block` | 수신 서버 부작용은 규정 없음. **보내는 쪽은 대상에게 배달하지 않는 것이 SHOULD** | `axismundi_act_reconcile_block()`이 관계 상태를 만든다. 그러나 `axismundi_activitypub_bridge_activity_inboxes()`가 **`Follow`와 `Block`을 같이 묶어 object의 inbox를 수신자로 추가**한다(`transport.php:190`) | **어긋남 — 5.4 참조** |
| `Flag` | — | 타입 목록에 있고 원장에 기록됨. 조정 파이프라인 없음 | 부분적 |

### 5.3 컬렉션

| 컬렉션 | AP | 우리 | 비고 |
|---|---|---|---|
| `inbox` | MUST | 공식 플러그인 라우트 | Bridge는 REST 라우트를 0개 갖는다 |
| `outbox` | MUST | OP `axismundi_op_actor_outbox_url()` | 공개 payload만. Activities가 없으면 `totalItems: 0`인 빈 컬렉션 — 주소는 언제나 응답한다(§6.7) |
| `followers` | SHOULD | OP 컬렉션 + 페이징 | 프라이버시 정책에 따라 노출 여부 결정. 관계 상태가 없으면 셀 수 없으므로 아무 주장도 하지 않는다(0을 보내지 않는다) |
| `following` | SHOULD | 같은 라우트의 `collection=following` | 같음 |
| `liked` | MAY | 없음 | 의도적 미구현으로 보임. 필요해지면 원장에서 파생 가능 |
| `likes` | MAY | 객체별 제공 | |
| `shares` | MAY | 객체별 제공 | |
| `replies` | AS2 | `axismundi_op_object_replies_url()` + 페이지 | |

멱등성은 스키마가 보장한다. `wp_ax_activities.activity_uri_hash`와
`wp_ax_ap_deliveries.activity_uri_hash`가 UNIQUE라, 같은 Activity가 두 경로로 도착해도 행이
하나다. 공유 Inbox와 per-recipient Inbox 중 전자만 기록하는 규칙도 같은 이유다.

### 5.4 발견 — 아웃바운드 `Block`이 대상에게 배달된다

`axismundi_activitypub_bridge_activity_inboxes()`는 `Follow`와 `Block`을 한 조건으로 묶어
`object`의 inbox를 수신자 목록에 넣는다.

```php
if ( in_array( $activity->get_type(), array( 'Follow', 'Block' ), true ) && $activity->get_object_uri() ) {
    $candidates[] = $activity->get_object_uri();
}
```

`Follow`는 맞다. 상대가 받아야 `Accept`를 돌려줄 수 있다. `Block`은 AP가 **보내지 않는 것을
SHOULD**로 적는다. 차단당한 사실이 상대에게 통지되기 때문이다.

**구현체 관행은 표준과 다르다.** Mastodon의 ActivityPub 문서는 "ActivityPub defines the
`Block` activity for client-to-server (C2S) use-cases, but not for server-to-server (S2S)"라고
적으면서도, 로컬 사용자가 원격 사용자를 차단하면 그 Activity를 **보낸다**고 명시한다. 받은
쪽은 해당 actor의 프로필과 글을 숨기는 신호로 쓴다. Misskey도 `Block`과 해제 시
`Undo(Block)`를 실제로 배달하고 양쪽 follow 관계를 정리한다(조사 출처: Misskey
`UserBlockingService`와 federation block 테스트 — 링크는 E축에 정리).

즉 우리 현재 동작은 **표준 권고와 어긋나지만 실무 관행과는 맞는다.** 그러므로 이것은 버그
판정이 아니라 **정책 결정**이다.

- 기본값을 표준 쪽(보내지 않음)으로 둘 것인가, 관행 쪽(보냄)으로 둘 것인가
- 어느 쪽이든 옵트인/옵트아웃을 둘 것인가
- Forum 모더레이션의 ban을 이 경로에 섞지 않는다는 것(§5.7)

owner 결정 항목으로 §9에 올린다. 결정되면 그 이유를 `transport.php`의 해당 조건 위에
`NON-STANDARD` 주석으로 적는다.

### 5.5 발견 — 컬렉션 멤버십(`Add`/`Remove`)은 아직 계약이 아니다

공유 폴더 로드맵이 정확히 이 지점 위에 있다. 잠근 모델에서 폴더는 `Add`/`Remove`의 **target**이고
`Follow`를 받지 않는다. 지금 원장은 `Add`/`Remove`를 기록하고 `target` 없는 payload를 거부하지만,
**"target 컬렉션의 멤버십을 바꾼다"는 수신 부작용이 어디에도 없다.**

즉 Lemmy Group join이 증명한 것은 Group 왕복 배관이지 폴더 멤버십이 아니라는 기존 판단이
코드로도 확인된다. 공유 폴더를 재개할 때 **가장 먼저 설계할 것은 `Add`/`Remove` 수신 계약**이다.

### 5.6 발견 — §7.1.2 inbox forwarding이 없다

AP는 세 조건(처음 보는 Activity, 주소에 우리 소유 컬렉션, 참조가 우리 소유 객체)을 모두 만족하면
원 서버가 닿지 못한 수신자에게 **전달해야 한다**고 적는다. 이른바 ghost replies 방지다.
코드에서 이에 해당하는 경로를 찾지 못했다.

다만 Forum은 다른 길을 간다. Group이 승인한 것을 **Group Actor의 `Announce`로 재배포**하는
threadiverse 방식이고, 수신 쪽도 `axismundi_op_unwrap_inbound_group_announce()`로 그 Announce를
연다. Lemmy와 붙는 데는 이쪽이 실제 경로다.

그래서 이것은 "공백"이자 "의도적 대체"일 수 있다. 판정하려면 Group 밖의 경우, 즉 **우리 로컬
객체에 달린 원격 답글이 원 작성자의 팔로워에게 어떻게 닿는가**를 확인해야 한다. 이 질문을 §9로
올린다.

### 5.7 moderation은 한 층이 아니라 세 층이다

`Block` 하나로 볼 수 없다. 구현체들은 셋을 분명히 나눠 다룬다.

| 층 | 무엇인가 | wire에 나가는가 | 우리 상태 |
|---|---|---|---|
| **`Block`** | actor ↔ actor의 연합 가능한 관계 변화. follow/request 해제와 상호작용 제한 | 표준은 S2S 권고 아님, 그러나 Mastodon·Misskey는 보냄 | `wp_ax_activity_relations`에 `block` 관계로 존재. 배달은 §5.4 |
| **Mute / ignore** | 내 인스턴스의 타임라인·알림 표시 정책. 상대 actor의 상태를 바꾸지 않음 | 나가지 않음 | **없음.** 코드에 mute/ignore 개념이 전혀 없다 |
| **Ban / instance block** | 범위를 가진 moderation(커뮤니티 ban, 사이트 ban) 또는 transport·discovery 정책 | Lemmy는 `Block`의 `target`으로 범위를 구분 | Forum 모더레이터는 있으나 ban의 scope/authority 모델은 없음 |

세 가지 판정이 따라 나온다.

1. **Mute/ignore는 공백이다.** 사용자별 preference 상태이지 Activity가 아니다. 원장에 넣으면
   안 되고, Notifications가 생길 때 그 층에 두는 것이 맞다. Misskey 테스트가 이 분리를
   분명히 보여 준다. 원격 차단 뒤에도 **mention은 전달되고 알림도 발생**하며, 알림을 막으려면
   mute하라고 테스트 주석이 적고 있다. 우리도 `Block`을 "상호작용 제한", ignore를 "내 표시·알림
   억제"로 갈라야 호환된다.
2. **Forum의 ban을 일반 `Block` 관계에 섞으면 안 된다.** Lemmy에서 moderation `Block`은
   `target`이 community면 커뮤니티 ban, site면 인스턴스 ban이라는 **별도 의미**다. 그래서
   ban은 `scope`(어느 커뮤니티/사이트)와 `authority`(누가 그럴 권한이 있는가)를 명시적으로
   가져야 한다. Lemmy의 보안 권고 사례도 연합으로 받은 site-level `Block`에서 admin 권한
   확인이 빠졌던 경우다. **수신 ban은 권한 확인 없이 적용하면 안 된다.**
3. **instance-level 정책은 actor 관계가 아니다.** 도메인 차단은 transport·discovery 층이다.
   Actors의 instance 캐시나 Bridge의 배달 후보 선정 쪽에 두어야 하고, 원장에 들어갈 사실이
   아니다.

### 5.8 instance 정책은 Actors가 소유하고 여러 층이 조회한다 (결정)

도메인 차단의 효과는 다섯 군데로 퍼진다.

| 효과 | 집행 지점 |
|---|---|
| 그 호스트의 Inbox 거부 | Bridge |
| 그 호스트로 배달하지 않음 | Bridge |
| fetch·discover 하지 않음 | Actors(원격 발견), OP(원격 객체 fetch) |
| 저장된 콘텐츠를 공개 표면에서 감춤 | OP(렌더), 제품 플러그인 |
| 그 authority의 커스텀 이모지를 더 관측하지 않음 | Emoji(반응 Activity·원격 Actor·원격 객체의 세 관측 지점) |
| 보관·삭제 | Actors, OP, Emoji(`ax_emoji_authorities`) |

그래서 **Bridge가 목록을 소유하면 안 된다.** Bridge 없이도 OP는 원격 객체를 가져오고 Actors는
원격 Actor를 발견한다. 전송만 막히고 나머지가 샌다.

목록은 **Actors의 `wp_ax_instances`**가 갖는다. 이미 host별 행에 software·NodeInfo·fetch 상태가
있다. 여기에 정책 한 단계를 더한다(`none` / `limit` = 받되 공개 표면에서 감춤 / `suspend` =
주고받지 않음). Actors가 조회 API 하나를 공개하고 각 층이 자기 지점에서 묻는다. 가시성 해석기를
Activities가 혼자 갖고 모두가 부르는 것과 같은 패턴이다.

**연합으로 받은 ban은 Activity다.** 기존 구조가 그대로 적용된다.

```text
수신 Block(target = site/community)
  → Activities 원장에 기록
  → 권한 확인(보낸 쪽이 그럴 자격이 있는가)
  → 통과하면 Actors의 instance 정책 상태가 바뀐다
```

Follow가 원장에 기록되고 관계 상태가 파생되는 것과 같은 모양이다. **원장은 사건, Actors는
상태.** §5.7의 "수신 ban은 권한 확인 없이 적용하지 않는다"가 여기에 들어간다.

**이모지 캐시가 조회 지점 하나를 더 만든다.** 커스텀 이모지 반응은 세 층으로 나뉜다.

```text
수신 EmojiReact / Like(content)
  → Activities  사실과 키: unicode:U+1F44D 또는 custom:<authority>:<key>
                authority 없는 커스텀 반응은 받지 않는다 — 선언이 증거다
  → Emoji       그림·선언·출처를 캐시(ax_emoji_authorities / ax_emojis / ax_emoji_references)
  → OP          반응 컬렉션 표현
  → Actors      authority가 되는 인스턴스
```

커스텀 반응의 선언은 반응 대상 객체가 아니라 **Activity에 실려 온다.** 그런데 참조는 대상 객체에
붙여 저장한다. 레지스트리의 참조 모델이 객체·Actor 범위여서, Activity 봉투만을 위한 세 번째
수명주기를 만들지 않으려는 선택이고, 보존 기간이 눈에 보이는 반응 표면과 함께 간다.

따라서 한 호스트를 `suspend`하면 그 authority의 새 관측도 멈춰야 한다. **지금은 그 연결이 없다.**

사용자 개인의 도메인 차단은 또 다른 층이다. 그것은 표시 정책이므로 mute/ignore와 같은 자리에
두고, 원장에도 instance 정책에도 넣지 않는다.

관리 화면이 생긴다면 `Actors › Instances` 하나로 묶는다. 같은 테이블을 보는 화면이기 때문이다.

### 5.9 발견 → 수정 — 전송 부재가 표현까지 가져갔다

Bridge는 준비 상태를 boolean 하나로 판단했다(`axismundi_activitypub_bridge_ready()`: 공식 AP +
Actors + OP + Activities). 그 하나가 **표현 양도와 전송을 같이** 결정했다. 그래서 Activities만
빠져도 공식 Router 해제가 풀리고, 공식 플러그인이 `/@handle`과 객체 협상을 가져갔다. 공식
플러그인의 Actor는 **다른 URI의 다른 정체성**이므로, 같은 사람이 두 actor로 보이게 된다.

측정: Activities 비활성 상태에서 우리 actor URI에 `Accept: application/activity+json`을 보내면
HTML이 돌아왔다.

주장을 둘로 나눠 고쳤다.

| claim | 조건 | 가져가는 것 |
|---|---|---|
| `..._representation_ready()` | 공식 AP + Actors + OP | 공식 Router 해제, `/@handle`·객체 협상, 스케줄러·mention 표현 |
| `..._ready()` | 위 + Activities | Inbox 소비, 배달, 전송 필드(`inbox`·`endpoints`·`publicKey`), 수명주기 소유, NodeInfo `protocols` |

수정 뒤 같은 조건에서 actor 문서가 우리 URI로, `application/activity+json`으로 응답한다.

**공식 플러그인 쪽의 구조 문제도 기록해 둔다.** 그쪽 Router는 actor와 객체를 한 콜백에서
처리하므로(`render_activitypub_template`, `template_redirect`), 표면별로 떼어낼 API가 없다.
우리는 OP 라우터가 `template_redirect` 우선순위 1에서 먼저 답하고 모르는 소스는 그대로 흘려보내는
방식으로 per-source 양도를 한다. 업스트림 제안 후보: **presentation router를 actor와 object로
나누고 각각에 필터를 두는 것.**

### 5.10 C축 결론

AP §6이 정의하는 부작용 중 **`Add`/`Remove`를 뺀 전부가 모델에 자리를 갖고 있다.** 특히
`Undo`를 payload 재작성이 아니라 파생 상태로 처리한 것, `Delete`를 Tombstone 교체로 처리한 것,
`Follow`의 자동 승인을 Group에서 멈추고 제품에 넘긴 것은 표준과 도메인 경계를 둘 다 지킨
선택이다.

후속 항목 세 가지: 아웃바운드 `Block` 배달(5.4), `Add`/`Remove` 수신 계약(5.5),
forwarding 대체 경로의 범위(5.6).

## 6. B축 — 객체와 주소 지정 (대조 완료)

### 6.1 판정

| 항목 | 표준·관행이 요구하는 것 | 우리 구현 | 판정 |
|---|---|---|---|
| `id` ≠ `url` | `id`는 AS 식별자, `url`은 사람용 표현 | Article·Note 모두 `id`는 객체 URI, `url`은 `{type: Link, href: 퍼머링크, mediaType: text/html}`. renderer가 `id`와 선언된 객체 URI가 다르면 **오류로 거부**(`renderer.php:160`) | 담고 있음 |
| 주소 지정 | 가시성 → `to`/`cc` | `axismundi_act_resolve_audience()`가 **순수 함수 하나**로 4가지를 매핑한다. public(to=Public, cc=followers+mentions), unlisted(to=followers, cc=Public+mentions), followers(to=followers, cc=mentions), mentioned(to=mentions). Mastodon 문서의 public/unlisted/private/direct 규칙과 일치 | 담고 있음 |
| followers 주소 | 팔로워 컬렉션 URI로 주소 지정 | 인박스 목록을 펼치지 않고 **followers Collection URI로만** 주소 지정. 펼치는 일은 Bridge 전송 층에서 한다 | 담고 있음 |
| `as:Public` | 매직 컬렉션 | 출력은 항상 정규 IRI `https://www.w3.org/ns/activitystreams#Public`. 입력은 `as:Public`·`Public` 축약형도 받아들임(`audience.php:148`) — 수신 관용, 발신 엄격 | 담고 있음 |
| 비공개 객체 노출 | 공개가 아니면 익명에게 주면 안 됨 | 공개가 아닌 Article은 JSON 경로에서 **404**, HTML discovery 메타데이터도 출력하지 않음(`router.php:174,222,257`) — fail-closed | 담고 있음 |
| content negotiation | `application/ld+json; profile=…`에 응답해야 하고 `application/activity+json`도 지원 권고 | 둘 다 받는다. 맨 `application/json`과 profile 없는 `ld+json`은 **일부러 거부**(API 클라이언트가 뜻밖에 AS를 받지 않도록). 다만 응답 `Content-Type`은 요청이 무엇이든 항상 `application/activity+json` | 부분적 — §6.3 |
| `attributedTo` | 작성자 | 로컬 Actor URI. Note는 envelope의 `actor_uri` | 담고 있음 |
| `inReplyTo` | 답글의 부모 | URI 키 스레드 그래프(`wp_ax_thread_edges`). 자식은 **직접 부모 URI만** 저장하고 root/depth는 편의값. 부모를 모를 때도 엣지를 버리지 않고 `resolution_state`로 기록했다가 자동 승격. `wp_comments`를 대체하지 않는 재구축 가능 인덱스 | 담고 있음 |
| `context` / 대화 | 대화 묶음 | **Note만** `context_uri`를 갖는다(`note/includes/envelope.php:554`). Article과 Forum Topic은 `context`를 내지 않는다 | 부분적 |
| Tombstone | 삭제된 객체의 표현 | 원격은 캐시를 Tombstone으로 교체, 로컬은 AP 경로 410 / HTML 404 | 담고 있음 (410/404는 의도적 비표준, §10) |
| `sensitive` | Mastodon 확장. 미디어 가림 + `summary`를 경고로 | Note는 envelope의 `is_sensitive`, 첨부는 Media Library의 민감 표시. 수신 측 유효값은 `object.sensitive OR attachment.sensitive`(fail-closed) | 담고 있음 |
| `tag` | Mention / Hashtag / Emoji | 셋 다 생성·해석. Mention은 `href`가 Actor URI, Hashtag는 로컬 아카이브 URL, Emoji는 FEP-9098 | 담고 있음 |
| `attachment` | 미디어 | Media Library 통합 어댑터가 서술자를 만들고 민감 표시를 함께 싣는다 | 담고 있음 |
| 언어 | `contentMap`/`nameMap` | Note는 스칼라 + 맵, Article은 언어를 알면 맵만, Actor는 둘 다(맵이 스칼라와 같으면 생략). 타입별 수신 실태에 맞춘 결정 — §6.2 | 의도적 (근거 주석 미기재) |

### 6.2 `content` 대 `contentMap` — 타입마다 다른 정책 (의도적)

Note는 **둘 다** 낸다. 코드에 이유까지 적혀 있다.

```php
// `content` remains the interoperable baseline. Some peers, including Misskey,
// do not read contentMap-only Notes; the map adds language precision beside it.
$object['content'] = $content;
if ( 'und' !== $language ) {
    $object['contentMap'] = array( $language => $content );
}
```

Article은 **언어를 알면 스칼라를 빼고 맵만** 낸다(`post-article.php:535`).

```php
if ( 'und' === $language ) {
    $article['name']    = $name;
    $article['content'] = $content;
} else {
    $article['nameMap']    = array( $language => $name );
    $article['contentMap'] = array( $language => $content );
}
```

Actor는 또 세 번째 정책이다. 스칼라와 맵을 **둘 다** 내되, 맵이 스칼라와 같은 한 줄뿐이면
생략한다. 그 이유도 주석에 있다(Actor는 누구나 처음 해석하는 대상이라 빈 프로필은 곧 보이지
않는 계정이 된다).

JSON-LD 의미론으로는 세 정책 모두 맞다. 문제는 wire format이다. **Note의 주석이 기록한 그
위험(맵만 있으면 못 읽는 피어)이 Article에는 그대로 남아 있다.** 언어가 설정된 사이트의 모든
Article이 여기에 해당한다.

**세 정책의 차이는 사고가 아니라 타입별 수신 실태에 맞춘 결정이다.** owner 확인(2026-09-20):

- **Misskey는 현재 Article을 수신하지 못한다.** 그래서 Misskey를 위해 스칼라를 중복할 이유가
  Article에는 없다. Note에서 스칼라를 유지하는 이유가 정확히 Misskey였다.
- **Mastodon은 맵만 있어도 본문을 읽는다.** 그래서 Mastodon을 위해서도 중복이 필요 없다.
- **Lemmy도 우리가 보내는 Article을 수신한다.** 그래서 Forum의 기본 타입이 Article이다(§6.4).
- **중복의 비용이 본문 길이에 비례한다.** Note는 짧아서 스칼라와 맵을 같이 실어도 부담이
  작지만, Article은 본문이 1만 자일 수 있다. 읽지도 않을 사본을 매 배달마다 두 번 싣는 것은
  트래픽 낭비다. 짧은 글에서 옳은 선택이 긴 글에서도 옳은 것은 아니다.

즉 Note의 "스칼라 + 맵"과 Article의 "맵만"은 **같은 원칙(피어가 읽는 최소 형태로 보낸다)을 서로
다른 수신 실태에 적용한 결과**다. 판정은 **의도적 결정**이며, 지금 고치지 않는다.

다만 두 가지가 남는다.

1. **근거가 코드에 없다.** Note에는 이유가 주석으로 적혀 있는데(`federation.php:458`),
   Article 쪽(`post-article.php:535`)에는 없다. 이 결정을 모르는 사람이 나중에 "일관성"을
   이유로 Note에 맞춰 바꾸기 쉽다. 같은 형식의 주석을 다는 것이 후속 작업이다.
2. **근거가 시간에 의존한다.** "Misskey가 Article을 수신하지 못한다"는 상대 구현체의 현재
   상태다. 다만 지원이 추가되는 것만으로는 정책이 바뀌지 않는다. **Misskey가 Article을 받고,
   그때 맵만으로 읽지 못해야** 스칼라를 되살릴 이유가 생긴다. 재측정 조건을 그렇게 적는다(§9.2).

### 6.3 발견 — 응답 `Content-Type`이 요청 프로파일을 따라가지 않는다

`axismundi_op_accepts_activitystreams()`는 `application/activity+json`과
profile이 붙은 `application/ld+json` 둘 다 받아들인다. 그런데 응답은 어느 경우든
`application/activity+json`으로 나간다(`router.php:144,189`).

AP는 객체 GET에 대해 `application/ld+json; profile="https://www.w3.org/ns/activitystreams"`로
응답할 것을 규범으로 적는다. 실무에서 문제가 된 사례는 알려진 바 없고 대부분의 구현이 같은
방식이지만, **요청한 미디어 타입과 응답 미디어 타입이 다르다**는 사실 자체는 기록해 둘 값이다.
고치는 비용은 요청을 보고 헤더를 고르는 정도로 작다.

### 6.4 Forum의 기본 타입이 `Page`가 아니라 `Article`인 이유

Lemmy의 글은 `Page`다. 우리는 그 관행을 따르지 않고 Article을 보낸다. 근거는 둘이다.

- **Lemmy가 Article을 수신한다**(owner 측정). 상호운용 비용이 없다.
- **`Page`는 Document 계열이다.** 그 타입을 Forum이 쓰면 Media Library가 소유하는 문서·첨부
  경계와 겹친다. 같은 타입이 두 도메인에서 서로 다른 뜻으로 쓰이면, 수신 쪽에서 무엇으로
  렌더링할지가 모호해지고 우리 안에서도 소유자가 흐려진다.

즉 **상호운용이 허락하는 범위에서 우리 도메인 경계를 지키는 선택**이다. 표준이 `Page`를
요구하는 것은 아니므로 비표준이 아니고, Lemmy 관행과 다르다는 사실만 기록해 둔다.

### 6.5 제품 경계는 연합 프로파일을 따라 그어졌다

타입 선택이 제품 경계와 같은 결정이다. owner 확인(2026-09-20):

```text
WP 기본 post  → Article        (OP 소유, 본문 길이와 무관)
Note 플러그인 → Note · Question · Quote · inReplyTo
Forum 플러그인 → Group 문맥의 Article (Topic)

Note  ↔ Mastodon · Misskey   (마이크로블로그 계열)
Forum ↔ Lemmy                (threadiverse 계열)
```

**왜 이렇게 갈랐는가.**

- **WP 기본 post는 길이와 무관하게 Article이다.** 짧은 글이라고 Note로 바꾸지 않는다. 타입이
  길이에 따라 흔들리면 같은 글의 표현이 편집마다 달라진다.
- **짧은 글끼리는 서로 변환된다.** Note ↔ Question ↔ Quote는 같은 계열이라 한 플러그인이
  전부 갖는 것이 맞다. 실제로 `federation.php`가 폴이 있으면 `Question`, 없으면 `Note`로
  같은 envelope에서 타입을 고른다.
- **long-form Article은 그 변환에 넣지 않는다.** Article을 `Question`으로 바꾸면 FEP 쪽과
  어긋나는 문제가 생긴다. 그래서 변환 계열은 Note 안에 가두고 Article은 밖에 둔다.
- **`inReplyTo`는 Note의 것이다.** 답글은 성격상 댓글이고, 댓글은 짧은 글 계열이다.
  (URI 스레드 그래프 인덱스 자체는 OP가 소유한다 — 인덱스와 저작은 다른 층이다.)

**이 문서의 다른 판정과 어떻게 맞물리는가.**

- §6.2의 타입별 언어 정책이 자의적이지 않은 이유가 여기 있다. Note는 Mastodon·Misskey를
  향하고, Article은 Mastodon·Lemmy를 향한다. 대상이 다르니 최소 형태도 다르다.
- §6.4의 Page 대 Article 결정도 같은 축이다. Forum은 Lemmy와 붙되 우리 타입 어휘를 지킨다.
- D축의 FEP 묶음(§7.4)이 제품별로 뭉쳐 있는 것도 우연이 아니다. 포럼 스레드 계열 FEP가
  Forum에, 인용·이모지 계열이 Note·Activities에 모인 것은 이 경계의 결과다.

### 6.6 수정 — Actors + OP만으로 published 글이 투영된다

OP의 기본은 **published = 공개**이고, Activities는 authored visibility와 Create/Update S2S를
결정한다. 즉 Activity가 없어도 published 글에는 JSON-LD가 붙어야 한다. 코드는 그렇지 않았다.

`axismundi_op_post_article_audience()`가 가시성 해석을 Activities에 전적으로 맡겨, Activities가
없으면 `WP_Error` → `publicly_readable` false → JSON 404였다. 치명적 오류 없이 **조용히 아무것도
나오지 않았다.**

고친 내용:

- `axismundi_op_default_public_audience()` — Activities가 없을 때 published·`public` 글을
  `to: [Public]`, `cc: [followers, mentions]`로 주소 지정한다.
- **비공개 값은 폴백하지 않는다.** `unlisted`/`followers`/`mentioned`는 정책 소유자 없이
  해석하지 않고 거부한다(`ax_op_post_audience_policy`). 이 층이 해서는 안 되는 단 하나의 실수가
  비공개 의도를 추측하는 것이다.
- Actor 문서가 `outbox`·`followers`·`following`을 **언제나** 이름 붙인다. 컬렉션 라우트도 Actors만
  있으면 등록된다. Outbox는 빈 `OrderedCollection`으로, follow 컬렉션은 셀 수 없을 때 아무 주장도
  하지 않는 형태로 답한다.

측정(공식 AP·Bridge·Activities 전부 비활성, Actors + OP만):

```text
GET /http-standalone-check/   Accept: application/activity+json
  → 200 application/activity+json
    type=Article, to=[…#Public], cc=[followers]
GET /actors/{uuid}
  → outbox / followers / following 있음, inbox 없음
GET …/outbox
  → OrderedCollection, totalItems 0
```

`inbox`가 없는 것은 남은 공백이다. AP가 actor에 MUST로 요구하는 필드이고, **원격 actor의
endpoint·key는 이미 Actors가 자기 테이블에 저장**하면서 로컬 actor에 대해서만 Bridge가 주입한다.
같은 개념을 방향에 따라 다르게 소유하고 있으므로, 로컬 endpoints·keys도 Actors가 소유하도록
옮기는 것이 다음 작업이다(§10의 12번).

### 6.7 B축 결론

객체 모델과 주소 지정은 잘 잡혀 있다. 특히 세 가지가 좋다.

- **가시성 해석기가 하나**다. Article과 Note가 같은 함수를 부르므로, 객체와 그것을 감싸는
  `Create`의 `to`/`cc`가 어긋날 수 없다.
- **발신은 엄격하고 수신은 관용적**이다(`as:Public` 축약형 수용, 정규 IRI 발신).
- **비공개가 fail-closed**다. JSON 404에 더해 HTML discovery 메타데이터까지 막는다.

후속 항목 둘: Article 맵 단독 정책의 **근거 주석**(§6.2 — 정책 자체는 유지), 응답 미디어
타입(§6.3). 그리고 대화 `context`를 Note 밖으로 넓힐지는 Forum 스레드 모델과 함께 판단할
항목이다.

## 7. D축 — 확장과 FEP 정책 (inventory 완료, 본문 대조 대기)

### 7.1 우리가 인용 중인 FEP 15개

제목과 상태는 **Codeberg `fediverse/fep` 인덱스 원문**에서 확인했다(2026-09-20). 기억으로
적지 않았다. 각 FEP **본문**과의 문장 단위 대조는 아직이며, 마지막 열이 그 대상이다.

| FEP | 제목 (인덱스 원문) | 상태 | 우리 인용 위치 | 실제 wire 필드 | 구현체 확인 대상 | 판정 |
|---|---|---|---|---|---|---|
| 044f | Consent-respecting quote posts | DRAFT | Activities `quote-authorizations.php`, `quote-outbound.php`, `quote-requests.php` | `QuoteRequest`, `quoteAuthorization`, `interactionPolicy.canQuote` | Mastodon(인용 승인), Misskey | 본문 대조 대기 |
| e232 | Object Links | **FINAL** | OP 인용 정규화(`quoteUri`/`quoteUrl`/`_misskey_quote`와 함께) | `quoteUri`, 레거시 `quoteUrl`, `_misskey_quote` | Misskey, Mastodon | 본문 대조 대기 |
| 9098 | Custom emojis | DRAFT | Emoji 전체, Activities 반응 태그 | `tag` 안의 `Emoji` + `icon`, 256 KB 상한 | Mastodon(`toot:Emoji`), Misskey | 본문 대조 대기 |
| c0e0 | Emoji reactions | DRAFT | Activities `reactions.php`, `reaction-summary.php` | `EmojiReact`, Fedibird 반응 컬렉션 IRI | Misskey(확인됨), Mastodon 계열 | 본문 대조 대기 |
| 1311 | Media Attachments | DRAFT | Media Library `federation.php`, OP `renderer.php` | `renditions`(+ `url`/`width`/`height`) | Mastodon, Misskey | 본문 대조 대기 |
| b2b8 | Long-form Text | DRAFT | OP `object-view-model.php`, `object-blocks.php` | `image`의 in-stream 대표 이미지 해석, 요약/경고 3줄 규칙 | Mastodon, Lemmy | 본문 대조 대기 |
| 1b12 | Group federation | **FINAL** | Forum `distribution.php`, `outbound-topics.php`, Activities `audience.php` | Group `Announce`, `audience`, `attributedTo` | **Lemmy(핵심)** | 본문 대조 대기 |
| 7888 | Demystifying the context property | DRAFT | Forum `thread-context.php`, `topics.php` | `context` | Lemmy, Mastodon | 본문 대조 대기 |
| 11dd | Context Ownership and Inheritance | DRAFT | Forum `thread-context.php`, Note `envelope.php` | 부모의 `context` 상속 | Lemmy | 본문 대조 대기 |
| f228 | Backfilling conversations | DRAFT | Forum `thread-context.php` | 대화 컬렉션 | Lemmy, Mastodon | 본문 대조 대기 |
| 9f9f | Collections | DRAFT | Forum `thread-context.php` | 쿼리 파라미터 없는 컬렉션 id, `first`/`next`/`partOf` | — | 본문 대조 대기 |
| 1985 | Signaling how an OrderedCollection is ordered | DRAFT | Forum `thread-context.php` | 정렬 방향 선언, `orderedItems` | — | 본문 대조 대기 |
| 8c13 | Context-Authority Routing with Object Integrity Proofs for Restricted Threads | DRAFT | Forum `thread-context.php`(주석에서 비용 언급) | 아직 구현 아님 | — | 참조만, 구현 없음 |
| 400e | Publicly-appendable ActivityPub collections | **FINAL** | Calendar `collection-projection.php`, `projection.php`, `event-placement.php` | 구독 대상 컬렉션을 `target`으로 지목 | — | 본문 대조 대기 |
| 8a8e | A common approach to using the Event object type | DRAFT | Calendar `envelope.php`, `event-locations.php`, `projection.php` | `Event`, `startTime`, `endTime`, `location`(복수), `joinMode`, `externalParticipationUrl` | Mobilizon, Gancio 계열 | 본문 대조 대기 |

상태 분포: **FINAL 3개**(e232, 1b12, 400e), **DRAFT 12개.** DRAFT에 의존한다는 것은 본문이
바뀔 수 있다는 뜻이므로, 이 표의 마지막 열은 한 번 채우고 끝이 아니라 **갱신 대상**이다.

### 7.2 인용하지 않았지만 우리 열린 질문과 직접 닿는 FEP

같은 인덱스에서 확인한 것 중, 이 검토가 남긴 질문에 대응하는 항목이다. 채택 여부는 결정하지
않는다. 질문을 풀 때 **먼저 읽을 문서**를 지목하는 것이 목적이다.

| 질문 | 볼 FEP | 상태 |
|---|---|---|
| 도메인 이전과 이전 URI (A축 §4.3) | 7628 Move actor · a427 Server Domain Migration · 1580 Move Actor Objects with a `migration` Collection · e965 Move Activity for Migrations… | 7628 **FINAL**, 나머지 DRAFT |
| 공개키 표현 (A축 Keys) | 521a Representing actor's public keys · 8b32 Object Integrity Proofs | 521a **FINAL**, 8b32 DRAFT |
| NodeInfo 소유와 버전 (A축 §4.3) | f1d5 NodeInfo in Fediverse Software · 0151 NodeInfo (2025 edition) | 둘 다 **FINAL** |
| WebFinger 발견 (A축) | d556 Server-Level Actor Discovery Using WebFinger · 2c59 · 4adb · 03c1 Actors without acct-URI | d556 **FINAL**, 나머지 DRAFT |
| `Block`과 차단 컬렉션 (C축 §5.4, §5.7) | c648 Blocked Collection | DRAFT |
| Follow 승인 대기 (C축 `Follow`) | 4ccd Pending Followers Collection and Pending Following Collection | DRAFT |
| 대화 `context`를 Note 밖으로 (B축 §6.7) | 2931 Representing context with a Collection · 171b Conversation Containers · 76ea Conversation Threads | DRAFT |
| replies 컬렉션 관행 (B축) | 7458 Using the replies collection | DRAFT |
| 해시태그 (B축 `tag`) | eb48 Hashtags | DRAFT |
| 인용의 다른 계보 (D축 044f/e232) | dd4b Quote Posts | DRAFT |
| 포럼 간 콘텐츠 공유 (Forum) | d36d Sharing Content Across Federated Forums | DRAFT |
| 연합 문서화 | **67ff FEDERATION.md** | **FINAL** |

마지막 줄은 값이 크고 비용이 작다. 67ff는 플러그인이 자기 연합 동작을 `FEDERATION.md`로
선언하는 관행이고, 우리는 이미 그 내용을 네 플러그인의 docs에 흩어 두고 있다.

### 7.3 `@context` 정책

renderer가 `@context`의 **단독 소유자**다. transformer는 평범한 배열을 돌려주고, renderer가
호출자가 넣은 `@context`를 지운 뒤 정규 context를 맨 앞에 붙인다(`renderer.php:195`). 확장은
`axismundi_op_jsonld_context` 필터 **하나의 경계**로만 들어온다. `preview` 같은 내장 객체의
`@context`도 제거한다.

이 구조는 §3에서 고정한 두 층 계약을 검사하기에 좋다. 검사 지점이 한 곳이기 때문이다.
남은 것은 검사 자체다.

1. **의미론**: 선언한 term이 기대한 IRI로 expand되는가, 기존 AS2 term을 덮지 않는가.
2. **wire format**: 우리가 실제로 내보내는 compact key가 상대가 찾는 이름인가.

1번은 테스트에서 JSON-LD 프로세서로 자동화할 수 있다(§3.2). 2번은 E축 측정이다.

### 7.4 D축 결론 (잠정)

FEP 인용은 흩어진 장식이 아니라 **제품별로 묶여** 있다. 포럼 스레드 계열(7888, 11dd, f228,
9f9f, 1985, 8c13)이 한 파일에, 인용 계열(044f, e232)이 Activities에, 이모지 계열(9098, c0e0)이
Emoji와 Activities에, 일정 계열(400e, 8a8e)이 Calendar에 모여 있다. 확장이 도메인 경계를
따라간다는 뜻이라 구조상 건강하다.

확정 판정은 각 FEP **본문**을 읽고 우리가 의존하는 규범 문장을 인용한 뒤에 내린다. 우선순위는
셋이다. **1b12**(Lemmy 상호운용의 핵심이자 FINAL), **044f/e232**(인용, 이미 Mastodon·Misskey와
접점), **1311**(미디어, Photon 갭 이후 계약이 바뀐 적 있음).

## 8. FEP-1b12 본문 대조 (Group federation, FINAL)

규범 문장은 Codeberg 원문에서 읽었다(2026-09-20). 우선순위 1번으로 고른 이유는 Lemmy
상호운용의 핵심이고 상태가 FINAL이기 때문이다.

| FEP-1b12가 말하는 것 | 우리 구현 | 판정 |
|---|---|---|
| Group은 `audience`로 콘텐츠 귀속을 밝힌다. Lemmy·Friendica·lotide는 호환을 위해 `to`에 Group을 넣는다 | Topic 투영이 `audience = Group URI`, `to = [Group]`을 함께 낸다(`topics.php:1013`). 권고와 현실 관행을 둘 다 만족 | 담고 있음 |
| 유효한 제출이 오면 Group은 **MUST** 그것을 `Announce`로 감싼다. 원 Activity를 object로 | `axismundi_forum_record_group_announce()`가 `Announce{actor: Group, object: <원 Create/Update>}`를 기록 | 담고 있음 |
| 감싼 Activity는 **MUST** 받은 그대로 보존한다. 속성을 바꾸거나 빼지 않는다 | `'object' => $submission->get_payload()` — 객체 URI가 아니라 원 Activity 페이로드 전체. 주석이 이 규범 문장을 직접 인용한다 | 담고 있음 |
| 공개 Group은 `Follow`–`Accept`를 **SHOULD** 지원하고, 수락하면 followers에 추가한다 | 멤버십 정책(`membership_policies`)으로 자동/승인 대기를 고르고, Activities의 Follow 관계와 별도로 Forum이 가입을 소유한다. 자동 수락은 Activities가 Group을 만나면 멈추고 제품에 넘긴다(§5.2) | 담고 있음 |
| 모더레이터는 Group의 `attributedTo` 컬렉션에 실린다 | `axismundi_forum_moderator_collection_url()`을 `attributedTo`로 싣고, 로컬 public community Group에만 붙인다(`moderators.php:209`) | 담고 있음 |
| 모더레이션 Activity의 actor는 **MUST** `attributedTo`에 있어야 한다 | 로컬 발신 쪽은 권한 커널을 지난다. **수신 쪽 검증이 없다** | **공백** |
| 팔로워는 그 모더레이션이 **Group이 Announce한 것인지 추가로 검증해야 한다** | 같음 — 수신 모더레이션 경로 자체가 없다 | **공백** |
| 어떤 Activity 타입이든 Announce할 수 있다. 사적인 것은 전달하지 않아도 된다 | 배포 대상은 `Create`/`Update`/`Delete`로 좁혀져 있다(`distribution.php:421`). 좁은 쪽이라 위반은 아니다 | 담고 있음(보수적) |

### 배포 주소 지정

`axismundi_forum_distribution_audience()`가 공개 범위와 멤버 전용을 나눈다.

```text
public  : to = [Public],     cc = [Group followers]
members : to = [followers],  cc = []
```

제출은 반대다. 작성자의 `Create`는 Group inbox로 **직접** 가고 `to = [Group]`이며, 공개
라우팅 신호로 `cc`에 Public이 붙는다(`topics.php:1002`). Bridge는 이것을 직접 제출로 알아보고
작성자의 팔로워에게 뿌리지 않는다. **배포 사건은 작성자의 Create가 아니라 Group의 Announce**라는
FEP의 모델과 일치한다.

### 공백 하나 — 수신 모더레이션

로컬 Group이 자기 커뮤니티를 모더레이션하는 경로는 있다. 없는 것은 **원격 Group의 모더레이션을
받아들이는 경로**다. `inbound-topics.php`에 모더레이터나 `attributedTo` 검증이 한 줄도 없다.

이는 §5.7에서 적은 Lemmy 보안 권고와 같은 자리다. 받은 moderation을 권한 확인 없이 적용하면
안 되고, FEP-1b12는 그 검증을 두 겹으로 요구한다.

1. actor가 그 Group의 `attributedTo`에 있는가
2. 그 Activity가 Group의 `Announce`로 왔는가

우리가 원격 커뮤니티에 참여하기 시작하면(우리 사용자가 Lemmy 커뮤니티에 글을 쓰고 그쪽
모더레이터가 지우는 경우) 바로 필요해진다. **구현 전에는 "원격 모더레이션을 따르지 않는다"가
현재의 정직한 상태**다.

### 판정

FEP-1b12의 발신 쪽 규범은 전부 담고 있다. `MUST` 두 개(Announce 래핑, 원문 보존)를 정확히
지키고, `audience` 권고와 `to` 관행을 동시에 만족한다. 공백은 수신 모더레이션 하나이며,
이는 §5.5(`Add`/`Remove` 수신 계약)와 같은 계열의 문제다. **우리가 보내는 것은 맞고, 남이
보내는 moderation을 아직 읽지 않는다.**

### 8.5 로컬 완결성 측정 (2026-09-20)

계약은 **공식 AP 플러그인과 Bridge 없이 Actors · Object Projections · Activities가 로컬에서
완전히 동작한다**는 것이다. 그 조건에서 감사를 돌렸다.

| 플러그인 | 결과 |
|---|---|
| Actors | 12종 통과 (account-header, acting-actor, addresses, admin, asset-cache, avatar, endpoints, follow-collections, follow-vocabulary, identity-registry, identity-relations, instances) |
| Activities | 14종 통과 — local-social 25, actor-feed 85, emoji-reactions 39, reaction-summary 27, repository 21, audience 19, relations 14, reactions 14, post-create 13, follow-button 13, quote-requests 10, announces 8, votes 7, public-outbox 4 |
| Object Projections | standalone-projection 8/8, router 9, view-model 29, replies 5 |
| Forum | topics 12, memberships 12, votes 14, person-community 10, thread-context 17/18 |

즉 팔로우·좋아요·이모지 반응·인용·피드·스레드·커뮤니티 가입과 투표가 전송 계층 없이 성립한다.

**감사 두 개는 전송 계층을 전제한다.** `audit-forum-moderation`은 Bridge의 `transport.php`를
`require_once`로 직접 읽어 Bridge가 꺼져 있으면 치명적 오류가 나고,
`audit-forum-thread-context`의 한 항목은 "원격 Lemmy 댓글에 대한 답글이 커뮤니티 inbox만
큐에 넣는지"를 본다. 둘 다 **전송을 검증하는 항목**이므로 로컬 완결성의 반례가 아니다. 전부
활성인 상태에서 26/26, 18/18로 통과한다.

다만 감사 파일이 다른 플러그인의 `includes/`를 직접 `require_once` 하는 것은 그 자체로 경계
위반이다. 전송을 보는 항목은 전송 플러그인 쪽 감사에 두거나, 파일을 읽는 대신 함수 존재를
확인하고 건너뛰어야 한다. 정리 대상으로 기록한다.

## 9. E축 — 상호운용 프로파일 (측정 대기)

Mastodon·Misskey·Lemmy·GoToSocial에서 실제로 겪은 차이를 A~D축 항목에 귀속시킨다.
이미 측정된 것: Misskey의 embedded url scalar, Lemmy의 공개 라우팅과 collection-moderation,
Mastodon의 sensitive 해석.

### 9.1 종결 — `contentMap`만 있는 Article (측정됨)

한때 이 검토의 첫 테스트였다. owner 측정으로 세 구현체가 모두 답을 갖고 있어 종결한다.

| 구현체 | 결과 | 따라 나온 결정 |
|---|---|---|
| Mastodon | `contentMap`만으로 본문을 읽는다 | 스칼라 중복 불필요 |
| Lemmy | 우리가 보내는 Article을 수신한다 | Forum 기본 타입 = Article (§6.4) |
| Misskey | Article 자체를 수신하지 못한다 | Article에 스칼라를 실을 이유 없음 |

결론: Article의 맵 단독 정책은 유지한다. 남은 작업은 **근거 주석**(§6.2)뿐이다.

### 9.2 재측정이 필요한 전제

상대 구현체의 현재 상태에 기댄 결정은 시간이 지나면 흔들린다. 측정일과 함께 관리한다.
**전제가 무너지는 조건을 함께 적는다** — "언젠가 바뀔 수 있다"는 관리 항목이 되지 못한다.

| 전제 | 근거 | 최초 확인 | 다시 볼 이유 |
|---|---|---|---|
| Misskey는 Article을 수신하지 못한다 | owner 측정 | 2026-09-20 기록 | **두 조건이 같이 성립할 때만** 정책이 바뀐다: Article 지원이 추가되고, 그때 맵만으로 읽지 못할 것 |
| Mastodon은 `contentMap`만으로 읽는다 | owner 측정 | 2026-09-20 기록 | 렌더링 경로 변경 시 |
| Lemmy는 우리 Article을 수신한다 | owner 측정 | 2026-09-20 기록 | Forum 기본 타입의 전제(§6.4). Lemmy가 Page 외 타입 처리를 바꾸면 다시 본다 |
| Misskey는 `contentMap`만 있는 Note를 읽지 못한다 | 코드 주석의 기록 | 미상 | Note 정책의 근거. 측정일이 없다는 것 자체가 약점이다 |

## 10. 열린 질문

1. 도메인 이전 뒤 이전 actor URI를 무엇이 계속 응답하는가 (A축).
2. ~~NodeInfo의 장기 소유자~~ → 결정됨: Actors가 문서를 소유하고 능력 소유자가 선언한다. 분리 조건은 §4.4.
3. 공식 ActivityPub 플러그인의 서명 계약을 무엇으로 검증할 것인가 — 문서인가 테스트인가.
4. 아웃바운드 `Block`을 대상에게 배달하는 것은 의도인가 간과인가 (C축 §5.4).
5. `Add`/`Remove` 수신 부작용을 공유 폴더 재개 전에 설계할 것인가 (C축 §5.5).
6. Group 밖의 원격 답글은 원 작성자의 팔로워에게 어떻게 닿는가 — §7.1.2 forwarding 없이
   Group Announce만으로 충분한 범위는 어디까지인가 (C축 §5.6).
7. mute/ignore를 어느 층에 둘 것인가 — Notifications가 생길 때까지 미루는가 (C축 §5.7).
8. Forum ban의 `scope`/`authority` 모델을 언제 설계하는가. 수신 ban의 권한 확인은
   설계와 동시에 들어가야 한다 (C축 §5.7).
9. Article의 맵 단독 정책과 Forum의 Article 기본값, 그 근거를 코드 주석으로 남기는 일
   (B축 §6.2, §6.4). 정책 자체는 측정으로 결정됨.
10. 응답 `Content-Type`을 요청 프로파일에 맞출 것인가 (B축 §6.3).
11. 대화 `context`를 Note 밖(Article·Forum Topic)으로 넓힐 것인가 (B축 §6.7).
12. 로컬 Actor의 endpoints(`inbox`·`sharedInbox`)와 공개키를 Actors가 소유하도록 옮기는 일.
    테이블(`wp_ax_actor_endpoints`, `wp_ax_actor_keys`)은 이미 있고 원격 Actor에만 쓰인다.
    게이트의 근거도 "Bridge가 있는가"에서 "키가 있는가"로 바뀐다 (B축 §6.7).
13. 공식 플러그인의 presentation router를 actor/object로 나누는 업스트림 제안 (C축 §5.9).
14. 원격 Group의 moderation을 수신할 때의 두 겹 검증(actor가 `attributedTo`에 있는가, Group의
    `Announce`로 왔는가)을 언제 구현할 것인가 (§8).
15. 다른 플러그인의 `includes/`를 `require_once` 하는 감사 파일 정리 (§8.5).

## 11. 의도적 비표준

축별 대조에서 발견되는 대로 여기에 모은다. 이미 알려진 것:

- Tombstone은 AP 경로에서 410, 사람용 HTML에서 404. 의도된 차이이며 되돌리지 않는다.
