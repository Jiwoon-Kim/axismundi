# Brief — `Todo/set`: 쓰기와 **항목 수준** 오류

> 공유 쪽은 같이 가야 한다 — [BRIEF-TODO-SET-SHARING.md](BRIEF-TODO-SET-SHARING.md).
> `isSubscribed`·`myRights`·`shareWith` 는 Todo 가 발명한 것이 아니라
> **RFC 9670 §4 가 MUST 로 요구하는** 세 속성이다.

이 조각의 목적은 Todo 가 아니다. **`/set` 의 모양**을 코어에 넣는 것이다. Stalwart 는 Todo 를
구현하지 않으므로 이 슬라이스는 상호운용 증거가 되지 않는다. 그런데도 Contacts 보다 먼저
하는 이유는 하나다 — **capability 는 옮겨가지 않지만 `/set` 의 모양은 그대로 옮겨간다.**
나중 `ContactCard/set` 이 쓰는 것이 여기서 만드는 층이다.

## 왜 이것이 GUI 보다 먼저인가

코어가 지금 모델링하는 오류는 **둘**이고 `/set` 에는 **셋째**가 있다.

```txt
요청 수준   problem+json                                  Error::Problem        ✅
메서드 수준  HTTP 200 안의 name="error" invocation          Invocation::is_error  ✅
항목 수준   HTTP 200 + **성공한** Todo/set 응답 안의
           notCreated / notUpdated / notDestroyed 의
           id 별 SetError                                 **없음**              ← 이 슬라이스
```

셋째 층을 모르는 클라이언트는 **일부 실패한 저장을 성공으로 읽는다.** GUI 가 여기서 처음
만나면 "저장됐습니다" 를 띄우고 사용자 입력을 버린다. Todo PoC 문서가 이미 그 위험을
적어 둔 자리다 — "저장 실패는 입력 초안을 지우지 않는다."

## RFC 8620 §5.3 에서 확인한 것 (추측 아님)

```txt
stateMismatch   **메서드 수준**이다.  "the method will be aborted and a stateMismatch
                error returned" — 항목별 SetError 가 아니라 호출 전체가 error invocation 이다
notCreated      키가 **creation id** 다
notUpdated      키가 **객체 id** 다        ← 두 맵의 키 종류가 다르다.  뭉치면 틀린다
notDestroyed    키가 객체 id 다
created         creation id → 클라이언트가 보내지 않은 속성(서버 설정값·기본값).
                보낸 속성은 돌아오지 않는다
updated         "Id[Foo|null]|null" — 값이 **null 일 수 있다.**  요청하지 않은 변경이 없으면
                null 이고, 객체를 기대하는 클라이언트는 여기서 깨진다
oldState/newState  낙관적 동시성의 기준값
```

`create` 로 만든 객체는 같은 배치 안에서 `#creationId` 로 참조한다. 응답의 `created` 는
**creation id 로 키가 잡힌다** — 서버 id 로 키가 잡힌다고 가정하면 만든 객체를 못 찾는다.

## 작업

### ① 코어: `SetOutcome`

`Todo/set` 응답을 통째로 `Value` 로 넘기지 말고 타입으로 받는다. 이유는 하나 — **성공과
부분 실패를 호출자가 구분하지 않고 지나갈 수 없게** 만드는 것이다.

```txt
필수 필드   old_state, new_state
            created:   BTreeMap<creation_id, Value>
            updated:   BTreeMap<id, Option<Value>>     ← Option 이 핵심이다
            destroyed: Vec<id>
            not_created / not_updated / not_destroyed: BTreeMap<id, SetError>
SetError    type (필수), description, properties (invalidProperties 일 때)
술어        fully_applied() -> bool.  세 not_* 맵이 모두 비어야 true
금지        not_* 를 무시하는 편의 접근자.  "성공 목록만 보는" 경로를 만들면 셋째 층이
            다시 없는 것이 된다
```

`stateMismatch` 는 **여기로 오지 않는다** — 메서드 수준이라 기존 `Invocation::is_error`
경로다. 둘을 같은 타입에 담지 말 것.

### ② CLI: 만든 것만 지운다

프로브가 실제 데이터를 쓴다. 그래서 규칙을 코드로 박는다.

```txt
수열      create → get(확인) → update → get(확인) → destroy(**만든 id 만**)
불변식    프로브는 자기가 만들지 않은 id 를 destroy 하지 않는다.
          --id 로 임의 id 를 받는 플래그를 만들지 않는다
실패 시    destroy 까지 못 가면 남은 id 를 **출력한다**.  조용히 남기지 않는다
대상      개발 서버만.  스테이징·운영에 쓰라는 지시가 없으면 붙지 않는다
```

### ③ 판정 — 네 가지를 의도적으로 실패시킨다

통과 경로만 재면 셋째 층을 재지 않은 것이다. 각 항목은 **예상한 자리에서** 실패해야 한다.

```txt
ifInState 충돌      오래된 state 로 set → **메서드 수준** stateMismatch.
                   항목 수준으로 오면 그것이 서버 결함이고, 그렇게 기록한다
서버 설정 속성 쓰기   myRights 를 update 에 넣는다 → 항목 수준 SetError.
                   Todo PoC 는 myRights 를 서버가 계산한다고 못박았다
권리 없는 쓰기       읽기 전용 공유자로 update → 항목 수준 또는 forbidden.
                   어느 쪽인지 **재서** 적는다
존재하지 않는 id     update/destroy 에 없는 id → notFound SetError
```

그리고 **한 배치에 `Todo/set` 두 번**을 넣어 호출마다 권리를 다시 확인하는지 본다 — Todo PoC
문서가 "동일 요청 배치에서도 매 호출마다 권리를 다시 확인한다" 고 적어 둔 계약이다.

### ④ `/changes` 와의 접점

`set` 뒤에 `Todo/changes` 를 돌려 방금 만든·고친·지운 id 가 나오는지 본다. 그리고 Todo PoC
문서가 적어 둔 것 — 목록 공유·구독 변경과 목록 사이 항목 이동은 **ACL epoch 를 바꿔서** 이전
state 토큰이 재조회를 요구한다. 그 경우 `cannotCalculateChanges` 가 정상 답이고, 이미 CLI 가
REFETCH 로 출력한다.

## 바꾸지 말 것

```txt
Session 이 URL 의 유일한 출처    경로를 조립하지 않는다
오류 층을 합치지 않는다          Transport / Problem / Status / Shape / **SetError**
자격증명 로깅 금지               Credentials 의 Debug 가 비밀을 가린다.  테스트가 걸려 있다
core 와 cli 의 경계             core 는 출력하지 않고 cli 는 HTTP 를 말하지 않는다
TLS 검증 우회 플래그 금지         로컬 자체서명에서 유혹이 오고, 한 번 들어가면 남는다
```

## 다음 조각과의 관계

```txt
Tauri      이 슬라이스가 끝나야 붙인다.  첫 화면은 Todo 편집기가 아니라
           "서버 연결 → 발견된 계정·기능 → 조회" — 서버를 바꿔도 같은 화면이 도는 것이 결과다
Contacts   여기서 만든 SetOutcome 을 ContactCard/set 이 그대로 쓴다.
           Stalwart 가 urn:ietf:params:jmap:contacts 를 광고하므로 그쪽은 **상호운용** 슬라이스다
```
