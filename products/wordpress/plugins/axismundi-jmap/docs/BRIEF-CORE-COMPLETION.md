# Brief — Todo PoC 에서 Core 메서드와 Sharing 권한을 끝까지 검증한다

**"JMAP Core 완성" 이라고 적지 않는다.** §5.7 의 예제 메서드를 다 연결해도 Core 전체
적합성을 입증한 것이 아니다. 이 슬라이스가 끝나면 말할 수 있는 것은 **"Todo PoC 에서 표준
메서드 모양과 Sharing 권한이 끝까지 검증됐다"** 이고, 그 이상을 적으면 다음 사람이 적합성을
가정한다.

근거 셋을 고정한다. 추측으로 메우지 않는다.

```txt
RFC 8620 §5.7 Examples   Todo 로 표준 메서드를 예시한다 — 여기서 쓰이는 메서드가 목표 집합이다
RFC 9670 §4.1 Example    TodoList 공유 예제 — TodoListRights, 세 공통 속성, Principal capability
관리 화면                 /wp-admin/admin.php?page=axismundi-jmap-todo — 수동 확인 표면
```

## 측정한 격차 (2026-11-15)

```txt
§5.7 이 호출하는 것   Todo/set  Todo/query  Todo/queryChanges  Todo/get  Todo/copy  Todo/changes
우리가 가진 것        Todo/get  Todo/set  Todo/changes  TodoList/get  TodoList/set
                     Principal/get  Principal/changes  Core/echo
없는 것              **Todo/query  Todo/queryChanges  Todo/copy**
                     TodoList/changes
                     Principal/set  Principal/query  Principal/queryChanges
                     ShareNotification/*  (그리고 §4.1 의 Principal capability)
```

`/query` 와 `/queryChanges` 가 §5.7 예제의 절반을 차지한다. 그 둘이 없으면 "표준 메서드를
구현했다" 고 말할 수 없다.

## 순서와 그 이유

### ① `Todo/query` (§5.5)

첫 슬라이스는 `hasKeyword` 및 `AND`·`OR`·`NOT` 필터, 제목 정렬(동률은 ID 순),
position·anchor·limit·calculateTotal을 지원한다. ID와 total은 `Todo/get`과 같은 열람
조건 적용 후 계산한다. queryState는 페이지에 독립적이며 정렬된 가시 ID만 반영한다.
`canCalculateChanges`는 false이고 `Todo/queryChanges`는 이 슬라이스에 포함하지 않는다.

표준 메서드 중 **우리가 한 번도 만들지 않은 모양**이다. `/get` 은 id 를 받고 `/query` 는
filter·sort·position·limit 를 받아 **id 목록과 queryState** 를 돌려준다.

```txt
필수    filter, sort, position, limit, calculateTotal
        응답: ids, queryState, position, total(요청 시)
권리    공유받은 목록의 Todo 가 결과에 섞인다 → 결과 집합이 **권리로 걸러져야 한다**.
        걸러지지 않으면 id 유출이고, 그것이 이 메서드의 주된 위험이다
```

#### 첫 감사의 모양 — 같은 계정, 같은 필터, 두 Todo

빈 결과로 우연히 통과하는 경우와, id 는 숨겼지만 **개수로 존재를 드러내는** 경우를 한꺼번에
잡는 배치다.

```txt
배치   한 Account 안에 TodoList 둘.  L1 은 요청자에게 mayRead 로 공유, L2 는 공유 안 함
       각 목록에 Todo 하나씩.  **둘 다 같은 필터에 맞게** 만든다
단정   ids 에 L1 의 Todo 가 **있다**          ← 빈 결과로 통과하는 것을 막는다
       ids 에 L2 의 Todo 가 **없다**          ← id 유출을 막는다
       calculateTotal 을 지원하면 total 에도 L2 의 Todo 가 **포함되지 않는다**
                                             ← 개수를 통한 존재 노출을 막는다
금지   "결과가 비었다" 를 통과 기준으로 쓰는 것.  권리와 무관한 이유로도 참이 된다
```

`total` 이 중요한 이유는 따로 적어 둔다 — id 를 걸러도 개수를 걸러지 않은 집합에서 세면,
**보이지 않는 항목의 존재가 숫자로 샌다.** 필터링과 집계를 같은 권리 조건에서 수행한다.

### ② `Todo/queryChanges` (§5.6) — 적합한 거부부터

§5.6 이 두 오류를 명시한다.

```txt
cannotCalculateChanges  "The server cannot calculate the changes from the queryState
                        string given by the client, usually due to the client's state
                        being too old.  The client MUST invalidate its cache"
tooManyChanges          maxChanges 초과.  added/removed 각 항목이 한 변경이다
```

우리 state 는 사용자·Account·타입·소유 자격에 묶인 서명 토큰이고, 공유·구독 변경이 ACL epoch
를 바꾼다. 그래서 **쿼리 결과의 델타를 정직하게 계산할 수 없는 경우가 기본값에 가깝다.**

**완료 기준을 둘로 나눈다.** 하나로 묶으면 거부 경로가 녹색인 것을 동기화가 된다고 읽는다.

```txt
완료 A — 호출하지 말라고 말한다   둘 중 하나로 만족한다.
                        (a) `Todo/query` 응답이 `canCalculateChanges: false` 를 광고한다.
                            §5.5 가 그 필드를 바로 이 용도로 정의하므로 **적합한 클라이언트는
                            애초에 호출하지 않는다** — 스펙 자신의 기전이라 더 깔끔하다
                        (b) queryChanges 를 등록하고 cannotCalculateChanges 로 거부한다.
                            tooManyChanges 도 maxChanges 초과에서 돌아온다
                        (a) 를 택하면 unknownMethod 상황이 생기지 않는다.  그래도
                        미등록과 거부는 **다른 사실**이므로 어느 쪽을 택했는지 적는다.
                        **(a) 로 완료됨 (2026-11-15)**
완료 B — 실제 델타        계산할 수 있는 조건을 **먼저 문서에 적고**, 그 조건에서만 계산한다.
                        added/removed 가 정확한 것을 단정한다.  **별건이고, A 없이는 못 한다**
금지                     계산할 수 없는 델타를 추측해서 내보내는 것.
                        클라이언트는 그것을 신뢰해 캐시를 고친다
```

A 가 끝났을 때 적을 문장은 "queryChanges 가 적합하게 거부한다" 이고, "증분 동기화를
지원한다" 가 아니다.

### ③ `TodoList/changes`

`TodoList/get` 과 `set` 만 있어 동기화가 반쪽이다. 목록 이름 변경·공유 변경·구독 변경이
클라이언트에 전달될 경로가 없다. ①②보다 작고 독립적이다.

### ④ `Todo/copy` (§5.4) — 두 RFC 가 만나는 자리

`/copy` 는 **Account 를 건넌다**. 인자가 `fromAccountId`, `ifFromInState`, `create`,
`onSuccessDestroyOriginal` 이다.

```txt
의미    공유받은 Account 의 Todo 를 내 Account 로 복사한다 — 공유 모델 없이는 의미가 없다
권리    **양쪽**을 검사한다.  출처는 mayRead, 대상은 mayWrite
위험    onSuccessDestroyOriginal 은 출처에서 **삭제**한다 → 출처 쪽 권리를 따로 검사한다.
        복사 권한이 삭제 권한을 함의하지 않는다
감사    mayRead 만 가진 사용자의 onSuccessDestroyOriginal 이 **원본을 남기는 것**을 단정한다
```

### ⑤ RFC 9670 §4.1 의 Principal capability

§4.1 이 Todo 용 Principal capability 를 정의한다. 우리에게 없다.

```txt
accountId     "The accountId containing the Todo data for this Principal, if it has been
              shared with the requesting user."  공유되지 않았으면 null
mayShareWith  "The user may give this Principal permission to access a TodoList."
```

이것이 공유 UI 가 **유효한 공유 대상을 서버에서 받는** 경로다. 지금은 PoC 디렉터리 정책으로
사용자 목록을 내주는데, 그것은 우리 발명이고 이쪽이 표준 경로다. 둘을 같은 것으로 적지 말 것.

### ⑥ `Principal/query` 와 ShareNotification — 범위를 정한다

```txt
Principal/query  §1.4 가 "접근 가능한 Principal 집합" 의 발견 경로로 지목한다.
                 없으면 **"접근 가능한 전부를 훑어 구독을 관리하는 화면"** 을 만들 수 없다.
                 §4.1 도 isSubscribed=false 인 목록을 그런 화면에서 보이라고 한다
ShareNotification  §3.  공유가 생겼음을 알리는 타입.  이번 범위에 넣지 않아도 되지만,
                 **넣지 않는다고 적는다** — 미구현을 공백으로 기록하는 것과 침묵은 다르다
```

## RFC 안의 긴장 하나 — 우리 정책을 RFC 요구로 적지 말 것

```txt
§4     myRights 는 "The set of permissions the user currently has" — 서버가 계산한다
§4.1   mayAdmin 은 "The user may see and modify the **myRights** property of this TodoList"
```

§4.1 의 그 문장은 §4 의 정의와 어긋난다(문맥상 `shareWith` 를 뜻한 것으로 읽힌다). 우리
Todo PoC 는 **`myRights` 는 서버가 계산한다**로 정했고 그 선택은 §4 와 맞는다. 그러나
`BRIEF-TODO-SET-SHARING.md` 가 "`myRights` 쓰기 거부" 를 **RFC 요구처럼** 적은 것은
과했다. 거부는 **우리 정책**이고 근거는 §4 이며, §4.1 에 반대로 읽히는 문장이 있다.
감사 라벨도 그렇게 적는다.

## 바꾸지 말 것

```txt
capability 문자열        urn:com.example:jmap:todo.  RFC 8620 의 예제 문자열이다.
                        제품 capability 로 승격할 때 소유 URI 와 버전 계약을 따로 정한다
권리 재확인              같은 배치 안에서도 호출마다 다시 확인한다.  기존 계약이다
쓰기와 이력의 트랜잭션     같은 InnoDB 트랜잭션으로 커밋한다
Account 행 잠금          capability 정책 변경과 Todo 읽기·쓰기가 공유한다
미구현은 unknownMethod   거부(cannotCalculateChanges)와 공백(unknownMethod)을 섞지 않는다
```

## 검증

```bash
python tools/wp-env/run_audits.py run --plugin axismundi-jmap
```

```txt
필수   새 메서드마다 **권리 없는 호출이 id 를 보지 못하는 것**을 단정한다.
       /query 는 결과 집합이 유출 표면이므로 여기가 가장 중요하다
필수   음성 대조군으로 깨 본다.  기존 방식대로 제품 파일을 고치지 않고 프로세스 안에서
       권리·state·Account-bound 등록을 깨뜨려, 예상 단언을 잡았을 때만 CAUGHT + PASS 다
클라이언트  axj 로 왕복한다.  /query 와 /copy 는 CLI 서브커맨드가 필요하다
```
