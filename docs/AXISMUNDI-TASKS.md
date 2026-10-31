# Axismundi Tasks

할 일 목록. 2026-11-01 현재 제품이 아니라 **JMAP 공유·메서드 기반을 관통 검증하는
PoC**(`axismundi-jmap/modules/todo-poc`)로만 존재한다. 이 문서는 그 PoC 가 제품이 될 때
쓰일 결정과 참조 자료를 모아 둔다.

## 결정: Task 와 Calendar 는 별개 서비스다 (오너, 2026-11-01)

데이터는 상호운용 가능하되 **그 상호운용은 내부 정책이지 표준이 아니다.** 하나를 다른 하나의
표현으로 보지 않는다.

이 질문은 이미 한 번 드리프트를 만들었다 -- 2026-11-01 에 "task 는 캘린더 쪽 아닌가" 에서
JMAP Calendars 초안으로 갔다가 철회했다. 그래서 근거를 함께 적는다.

```txt
JMAP Calendars 초안   VTODO·VJOURNAL 을 범위 밖으로 명시한다.  §5.1.1 의
                      `Context: Event, Task` 는 JSCalendar 속성 하나가 양쪽에 쓰인다는
                      뜻이지 초안이 Task 접근을 정의한다는 뜻이 아니다
                      (codex 의 초안 독해, 2026-11-01)
Google Tasks 의 due   "Only the date is kept, and the time portion is discarded.
                      It is not a deadline."  이벤트 시각과 성질이 다르다
                      (discovery 문서 실측, 2026-11-01)
```

**두 모델의 시간 개념이 애초에 다르므로, 연결은 매핑이지 동일시가 아니다.**

## PoC 와의 관계

PoC 는 `urn:com.example:jmap:todo` 를 쓴다 -- RFC 9670 §4.1 의 예제를 **그대로** 구현하기
때문이고, 예제 이름을 쓰는 한 데이터 모델도 예제대로여야 정직하다.

**아래 참조 모델을 채택하는 순간이 우리 URI 로 바꿀 시점이다.** 상수 하나를 바꾸는 일이고,
그 전환 조건은 `axismundi-jmap` 쪽에 이미 기록되어 있다.

표면은 나중에 프런트앱의 `/tasks/` 라우팅이 된다. Material 을 쓰므로 일반적인 할 일 목록
레이아웃을 그대로 가져올 수 있다.

## 참조 모델: Google Tasks (결정 아님, 2026-11-01 실측)

출처: `https://www.googleapis.com/discovery/v1/apis/tasks/v1/rest`.
JMAP 예제가 비워 둔 갭(계층·순서·완료·기한)을 메우는 데 쓴다.

### 가져올 것

```txt
parent · position   계층과 형제 순서.  position 은 읽기 전용 사전식 정렬 키다
status / completed  needsAction | completed + 완료 시각
hidden              목록을 비울 때 완료였던 항목.  deleted 와 다른 개념이다
due                 날짜만.  시각은 버린다.  마감이 아니다
```

### 가져오지 말 것

```txt
updatedMin 동기화   타임스탬프 기반이다.  JMAP 은 불투명 state 문자열과 /changes 를 쓰고,
                    시계 오차와 같은 초 편집 때문에 그렇게 정해져 있다.  이걸 복제하면
                    PoC 가 세운 state 층을 스스로 깎는다
deleted 플래그      Google 은 플래그(+showDeleted)와 delete 메서드를 **둘 다** 갖는다.
                    JMAP 은 /set destroy + /changes 의 destroyed 목록이다.
                    플래그를 복제하면 "사라졌는가" 의 출처가 둘이 된다
```

### 자체 메서드가 필요한 것

```txt
순서 변경   Google 은 move( previous, parent ) 로만 바꾼다.  JMAP /set 에는 위치 이동
            개념이 없으므로 capability 자체 메서드가 필요하다.
            그 메서드는 ifInState 에 참여해야 한다 -- 아니면 두 클라이언트가 재정렬할 때
            충돌이 조용히 지나간다
```
