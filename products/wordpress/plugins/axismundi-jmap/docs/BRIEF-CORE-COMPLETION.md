# Brief — Todo PoC 로 JMAP Core 를 최대한 완성한다

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

표준 메서드 중 **우리가 한 번도 만들지 않은 모양**이다. `/get` 은 id 를 받고 `/query` 는
filter·sort·position·limit 를 받아 **id 목록과 queryState** 를 돌려준다.

```txt
필수    filter, sort, position, limit, calculateTotal
        응답: ids, queryState, position, total(요청 시)
권리    공유받은 목록의 Todo 가 결과에 섞인다 → 결과 집합이 **권리로 걸러져야 한다**.
        걸러지지 않으면 id 유출이고, 그것이 이 메서드의 주된 위험이다
감사    권리 없는 사용자의 query 가 그 id 를 **포함하지 않는 것**을 단정한다.
        "빈 목록" 이 아니라 "그 id 가 없음" 으로 단정한다 — 전자는 다른 이유로도 참이 된다
```

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

```txt
1차   queryChanges 를 등록하고 cannotCalculateChanges 로 **적합하게 거부**한다.
      미등록(unknownMethod)과 거부는 다르다 — 전자는 공백이고 후자는 계약이다
금지  계산할 수 없는 델타를 추측해서 내보내는 것.  클라이언트는 그것을 신뢰해 캐시를 고친다
후속  델타를 계산할 수 있는 조건을 **먼저 적고** 그 조건에서만 계산한다
```

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
