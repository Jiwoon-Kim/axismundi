# Brief — 메일 목록 무한 스크롤

> **범위**: GUI 만. 목록 끝에 가까워지면 다음 페이지를 이어 붙인다. 코어·Tauri 의 `mail_more` 는 이미 있다 — GUI 가
> 아직 부르지 않을 뿐이다. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 목록·선택은 [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md).

2026-11-24 작성. "소유자 결정" 과 "이미 있는 것" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
방식      무한 스크롤(페이지 번호 없음).  Gmail 안드로이드와 같다
선택      전체 선택 = 불러온 행만(지금 라벨 "Select all loaded messages" 그대로).  메일함 전체 선택은 이번에 없다
          새로 붙은 행은 선택되지 않는다 → 머리 체크박스는 "일부 선택"(indeterminate) — Gmail 은 해제로 보이지만
          일부 선택이 M3 체크박스 정의에 맞다
```

## 이미 있는 것 — 읽고 시작

```txt
코어   cache::more(client, session, store, account, mailbox, rows) — 캐시된 목록의 다음 페이지를 같은 queryState 로
         position 요청해 이어 붙인다.  서버 state 가 다르면 델타 sync 를 먼저 돌리고 한 번만 다시 묻는다; 두 번째도
         다르면 오류(다른 state 의 행을 이어 붙이면 순서가 틀리거나 중복).  페이지 크기 = window_size(session)
       sync 는 이미 늘어난 목록의 길이를 유지한다(cache.rs "A listing scrolled past its first window keeps the
         length it has") — 푸시·새로고침이 이어 붙인 행을 첫 창으로 자르지 않는다.  창에서 확인할 것
Tauri  mail_more(connectionId, accountId, mailboxId, rows) → ListView(rows 전체, total, hasMore, queryStale, partialCache)
GUI    mail-page.component.ts: view = resource(mail_sync), listing(linkedSignal, 같은 연결·Account·메일함이면 로드 중
         이전 값 유지), rows / eligible(= 불러온 message 행) / selected(Set)
       머리 체크박스: checked = eligible.length > 0 && selected.size === eligible.length,
                      indeterminate = 0 < selected.size < eligible.length — 행이 늘면 저절로 일부 선택이 된다
       선택에서 사라진 행을 빼는 effect, 실시간 갱신·새로고침(같은 view.reload 경로)
```

## 지시

1. **불러오기 트리거**: 목록(`.list` 스크롤 컨테이너) 끝 근처 감지 — 마지막 행 근처의 sentinel 을
   `IntersectionObserver`(root = `.list`, rootMargin 으로 한 화면 정도 미리). 스크롤 이벤트 폴링 금지.
   - `hasMore === false` 면 부르지 않는다. `hasMore === null`(서버가 모름)이면 받은 행 수가 페이지 크기보다 작을 때 멈춘다
     — 판단 근거를 코드 주석에.
   - 진행 중이면 겹쳐 부르지 않는다. 연결·Account·메일함이 바뀌면 진행 중 응답을 버린다(기존 scope 비교 — 늦은 응답이
     다른 메일함 목록에 붙지 않게).
2. **결과 반영**: `mail_more` 의 ListView 를 목록 값으로 바꾼다(뒤에 붙은 행만 새로 렌더 — `track row.id` 로 기존 행·포커스·
   선택 유지). `view` resource 와 따로 노는 두 번째 진실을 만들지 말 것 — resource 값을 갱신(`view.set`)하든 listing
   소스를 하나로 모으든, 다음 푸시 reload 가 이어 붙인 행을 되돌리지 않아야 한다.
3. **표시**: 목록 맨 아래에 작은 spinner(새로고침 규칙과 같은 300ms 지연·500ms 최소). 실패하면 그 자리에 "Couldn't load
   more · Retry" 버튼(자동 재시도 없음). `hasMore === false` 면 아무것도 두지 않는다(또는 조용한 "No more messages" — 판단).
   첫 로드·새로고침 표시와 겹치지 않게.
4. **선택**: 바꿀 것 없음 — 위 체크박스 식이 그대로 동작하는지 확인만. "N selected" 문구, 일괄 작업의 대상(불러온 행 중
   선택된 것)도 그대로.
5. **가상 스크롤은 이번에 하지 않는다**: 수천 행에서 DOM 이 무거워지는지 먼저 잰다(행 수별 스크롤 프레임·메모리). 필요해지면
   `cdk-virtual-scroll-viewport` 를 선택 모드·행 체크박스·포커스·푸시 갱신과 함께 다시 설계 — 별도 브리프.

## 하지 않는 것

메일함 전체 선택(id 만 받아 전부 선택), 페이지 번호, 가상 스크롤, 스레드 보기(대화형) 전환, 검색 결과 목록, 위로 스크롤
시 앞쪽 페이지 버리기.

## 검증

```txt
node --test "gui/src/**/*.spec.ts"   더 불러올지 판단(hasMore·받은 수·진행 중·scope) 을 순수 함수로 빼서
npm --prefix gui run build
```

창에서(원격 디버깅 CDP — DESIGN-MAIL-GUI.md "메일함 트리" 측정 경로). 로컬 fixture 메일함은 5통 안팎이라 페이지 경계를
못 넘는다 — 테스트 메일함에 페이지 크기의 2~3배를 만들고(외부 Email/set, 끝에 지움) 잰다:

1. 끝까지 스크롤 → 다음 페이지가 붙고 행 수가 늘어난다, 중복·순서 어긋남 없음(행 id 집합·receivedAt 내림차순 확인).
2. 전체 선택 → 더 불러옴 → 머리 체크박스가 indeterminate, 기존 선택 유지, 새 행은 미선택.
3. 이어 붙인 뒤 외부 생성(푸시) → 목록 길이가 첫 창으로 줄지 않는다, 스크롤 위치 유지.
4. 불러오는 중 메일함 전환 → 늦은 응답이 새 메일함에 붙지 않는다.
5. 실패(Caddy 중지) → Retry 버튼, 재시작 후 Retry 로 이어짐.

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.

## 구현 및 실측

- 변경 범위는 GUI만이다. `mail_more`가 돌려주는 누적 목록을 기존 resource에 반영한다.
  IPC에는 서버별 페이지 크기가 없으므로 `hasMore: null`이면 행이 비어 있거나 다음 응답에서 행 수가
  늘지 않았을 때 멈춘다. 첫 페이지가 50개라고 가정하지 않는다.
- 로컬 Stalwart의 격리된 테스트 메일함에서 50 → 100행으로 이어 붙였고, 순서와 ID 중복을 확인했다.
  기존 50통을 선택한 채 이어 붙이면 새 행은 미선택이고 헤더는 일부 선택이다.
- 외부 생성 푸시 뒤 100행을 유지했고 보던 행의 화면 위치도 같았다. 이어 받기 중 Inbox로 전환해도
  이전 메일함 응답이 붙지 않았다.
- Caddy를 중지하지 않고 개발 창에서 잘못된 메일함 ID로 실제 IPC 오류를 유도했다.
  자동 재시도 없이 Retry가 나타났고, 정상 ID로 돌아와 Retry를 누르자 121행과 `hasMore: false`가 확인됐다.
