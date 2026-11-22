# Brief — Mail 목록 다중 선택과 일괄 읽음·별표

> **범위**: 메일 목록의 선택 UI와 일괄 작업 툴바. 코어·Tauri 명령·결과 형태는 이미 있다
> (`mail_set_keywords`, `mail-keywords.ts`) — 건드리지 않는다. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md),
> 목록·쓰기 규칙은 [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md) "쓰기" 절.

2026-11-23 작성. "소유자 결정" 과 "이미 있는 것" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 지시를
고치지 말고 멈추고 보고한다.

## 소유자 결정

```txt
선택 범위    현재 JMAP Account 의, 지금 불러온 목록의 message 행만.  unavailable 행은 고를 수 없다
             "전체 선택" 도 불러온 행까지 — 메일함 전체가 아니다.  라벨이 그 범위를 말해야 한다
스레드 행    보류.  목록은 지금 message 행이라 해당 없음.  Threads 모드가 생기면 그때 정한다
작업         읽음 · 안 읽음 · 별표 · 별표 해제.  휴지통·이동은 다음 단계 (여러 메일함에 속한 메일의 정책 미정)
반영 시점    서버가 확인한 뒤.  낙관적 갱신 없음 — 단건과 같은 규칙
결과         성공한 행만 선택 해제.  적용 안 됨과 결과 모름은 둘 다 선택에 남기되 다르게 보인다
결과 모름    서버가 거부한 게 아니다.  그 메일·그 키워드는 조회로 상태가 확인될 때까지 쓰기 금지 (단건·일괄 모두).
             조회가 실패하면 잠금 유지.  다시 할 수 있는 건 쓰기가 아니라 조회.  선택 해제·재선택으로 안 풀린다.
             이 잠금은 KeywordWriter 가 이미 강제한다 — GUI 가 다시 구현하거나 우회하지 않는다
투자         Material·CDK 부품 그대로 (mat-checkbox, SelectionModel, mat-toolbar 류).  자체 선택 엔진 금지
```

## 이미 있는 것 — 읽고 시작

```txt
Tauri   mail_set_keywords({connectionId, accountId, emailIds: string[], keyword: "seen"|"flagged", on})
        → KeywordsView.  id 마다 결과 하나, 처음 요청한 순서, 중복 id 는 한 번.
        분할(maxObjectsInSet)·집계·응답 유실 확인은 Rust 가 한다.  GUI 는 id 배열을 한 번에 넘긴다 — 행마다
        invoke 하지 않는다
TS      gui/src/app/mail/mail-keywords.ts
          KeywordResult  confirmed | notApplied{reason} | unknown{reason}
          settle(view, action) → {confirmed, notApplied, unknown, keepSelected, refresh, notice}
          KeywordWriter  결과 모름 잠금.  write() 는 잠긴 id 를 보내지 않고 대신 mail_check_keywords(조회)로 확인,
                         조회가 키워드를 보여 주거나 메일이 없어졌을 때만 잠금 해제.  쓰기 요청 자체가 실패해도
                         결과 모름으로 잠근다.  spec 이 "모름 → 조회 실패 → 다시 눌러도 쓰기 IPC 없음" 을 고정
        mail-keywords.fixture.json 은 Rust 테스트가 쓴다.  형태를 바꾸려면 Rust 쪽부터 (AGENTS.md "Verify")
        mail_check_keywords({connectionId, accountId, emailIds}) → MailRow[]  조회 전용
서비스  gui/src/app/mail/mail-actions.ts  MailActions
          setKeyword(...)   단건 = setKeywords 의 id 하나
          setKeywords(accountId, ids, kw, on) → WriteResult | null   일괄.  알림·changed 처리까지 한다
          unverified(accountId, id, kw)  결과 모름으로 잠긴 상태인가 (시그널로 다시 읽힌다)
          shown(accountId, id, kw, viewHas, loadedFrom)  화면에 보일 값.  서버가 확인한 값(확인된 쓰기·조회)이
                         그 뷰의 마지막 로드 이후면 그것, 아니면 뷰 값.  mark() 를 로드 시작에 찍는다
        mail-page.component.ts  starred() · unread() 가 이미 shown() 을 쓴다.  토글 방향·표시·일괄 툴바의
                         "읽음/안 읽음" 판단은 전부 이 값으로 — row.flagged·row.unread 를 직접 쓰지 않는다
          changed          확인된 변경 뒤 올라가는 신호.  목록·읽기 창이 이걸 보고 다시 읽는다
          busy(id, kw)     진행 중 잠금
          actionName(kw, on)  알림 문구용 이름
```

## 화면

```txt
행       지금 별표가 있는 맨 앞 자리에 체크박스가 들어가고, 별표는 그 오른쪽으로 옮긴다 (Gmail: ☐ ☆ 보낸이…).
         체크박스와 별표는 둘 다 행 링크 밖 — 눌러도 메일이 열리지 않는다.  hover 날짜 자리 읽음 토글은 그대로
머리줄   하나라도 선택되면 목록 머리줄(.list-head)이 일괄 툴바로 바뀐다:
           [선택 해제 ✕] "3 selected"   [읽음] [안 읽음] [별표] [별표 해제]
         0 개면 원래 머리줄 (메일함 이름·개수)로 돌아온다
전체     머리줄의 체크박스 하나: 없음 / 일부(indeterminate) / 전부.  라벨 "Select all loaded messages"
결과     선택에 남은 행에 표시: notApplied 와 unknown 을 아이콘·문구로 구분 (색만으로 구분 금지, WCAG 1.4.1)
         행의 표시는 그 행의 reason 을 툴팁/aria-description 으로 갖는다.  알림은 MailActions 가 한 줄로
잠금     actions.unverified(...) 인 행·버튼은 "상태 확인 필요" 로 보인다.  누르면 조회가 돈다(쓰기 아님) —
         툴팁·라벨이 그렇게 말한다 ("Check status").  단건 행 버튼과 읽기 창 버튼도 같은 표시
```

## 구현 지시

### 1. 선택 상태

`SelectionModel<string>`(`@angular/cdk/collections`, 이미 의존성에 있음)을 목록 컴포넌트가 갖는다. 키는 Email
id. 다음일 때 비운다: Account 전환, 메일함 전환, 연결 전환. 목록이 다시 읽혀 행이 사라지면 사라진 id 는
선택에서도 뺀다(남은 행에 없는 id 가 선택돼 있으면 안 된다). 선택은 URL 에 넣지 않는다.

### 2. 일괄 작업

`MailActions` 에 일괄 메서드를 더한다(단건 `setKeyword` 를 이것 위로 옮겨도 된다). 흐름:

1. 툴바 버튼 → 선택된 id 를 목록 순서로 → `mail_set_keywords` 한 번.
2. 진행 중엔 툴바 버튼을 잠그고 선택을 바꾸지 못하게 한다. 각 행의 단건 버튼도 그 id 는 잠근다.
3. `actions.setKeywords(...)` 가 돌려준 `WriteResult` 로 선택을 정리한다: `settled.keepSelected` 와 `held`
   (잠겨서 안 보낸 id)를 남기고 나머지는 해제. 알림과 `changed` 는 `MailActions` 가 이미 처리한다.
4. 쓰기 요청 자체가 실패하면 `writeError` 가 차 있고 보낸 id 는 전부 결과 모름으로 잠긴다 — 선택에 남긴다.

### 3. 행 결과 표시

notApplied 표시는 다음 작업 또는 선택 해제 때 지운다. unknown 은 표시가 아니라 상태다: `actions.unverified()`
가 참인 동안 보인다 — 선택을 해제해도, 목록이 다시 읽혀도 남는다. 풀리는 건 조회(`mail_check_keywords`)가
그 키워드를 보여 줬을 때뿐이고, 그 판단은 `KeywordWriter` 가 한다. `changed` 로 목록이 다시 읽혔다는 사실은
확인이 아니다 — 갱신이 진행 중이거나 실패했을 수 있다. 잠긴 행에서 버튼을 누르면 조회가 돈다. 별도 "재시도"
쓰기 버튼은 두지 않는다.

### 4. 키보드·접근성

- 체크박스는 Tab 으로 닿는다. 행 링크와 순서: 체크박스 → 별표 → 행 → 행 동작.
- 툴바는 `role="toolbar"` + aria-label. 선택 개수 변화는 `aria-live="polite"` 로 알린다.
- 전체 선택 체크박스는 indeterminate 를 `aria-checked="mixed"` 로 (mat-checkbox 가 해 준다 — 확인만).
- AXE 깨끗, WCAG AA.

### 5. 좁은 창

compact(<600)에서도 체크박스 열은 보인다. 툴바 버튼은 아이콘 버튼 + 툴팁으로 줄인다. 레이아웃 계약
(`@container list`)은 [mail-page.component.css](../gui/src/app/mail/mail-page.component.css) 를 따른다.
스타일 예산 경고(4 kB 초과)는 이 동작을 줄여서 맞추지 않는다 — 필요하면 `angular.json` 예산을 올린다.

## 하지 않는 것

- Rust·Tauri 명령·`mail-keywords.ts` 의 형태 변경, `KeywordWriter` 우회(직접 `mail_set_keywords` invoke).
  필요하면 멈추고 보고.
- 메일함 전체 선택, 필터 기반 일괄 처리(JMAP 은 update 를 query 결과로 받을 수 없다 — DESIGN 문서).
- 휴지통·이동·삭제.
- 낙관적 갱신, 오프라인 큐, 자동 재시도.

## 검증

```txt
npm --prefix gui run build
node --test "gui/src/**/*.spec.ts"           순수 로직(선택 정리 등)을 뽑았다면 spec 을 붙인다
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib   형태를 안 건드렸다면 그대로 통과해야 한다
```

Tauri 창에서 (Stalwart fixture, alice):

1. 세 통 선택 → 읽음 → 세 행 모두 선택 해제, 미읽음 수가 메일함 옆에서 줄어든다.
2. 전체 선택 → indeterminate·전부 전환, 라벨이 "불러온 메일" 범위를 말한다.
3. 메일함 전환 → 선택이 비어 있다.
4. 실패 경로는 창에서 만들기 어렵다 — `settle` 과 `KeywordWriter` 의 spec 이 덮는다. 행 표시 컴포넌트는 notApplied·unknown
   각각을 가짜 결과로 한 번씩 렌더해 보고 스크린샷을 남긴다.

끝나면 바꾼 파일 목록, 창에서 본 것, 못 본 것을 보고한다.
