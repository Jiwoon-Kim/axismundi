# 조사: 블록 쪽 overlay 선례 — axismundi-dialogs

## 상태

**조사 기록. 2026-10-11.** 판정이 아니다. `DECISION-FRONTEND-OVERLAY-SURFACE-OWNERSHIP.md`가
"React 표면은 평행한 자체 Surface를 갖는다"를 이미 판정했고, 이 문서는 **그 구현을 소비하지
않기로 한 뒤에도 읽을 값어치가 있는 것**을 적는다. 코드를 가져오지 않고 판정을 가져온다.

출처: products/wordpress/plugins/axismundi-dialogs/
읽은 것: blocks/dialog/view.js (1058줄) 헤더·섹션 넷, includes/surface.php 요지

## 핵심 판정: 플랫폼이 하는 것은 플랫폼이 한다

view.js 머리말이 계약을 직접 쓴다 — "The <dialog> opens and closes natively;
this module adds what the platform does not, and nothing it does."

그래서 손으로 만들지 않은 것:
  - focus trap      showModal()의 top layer가 함
  - scroll lock     같음
  - scrim           ::backdrop
  - Escape          cancel 이벤트
  - portal          top layer라 DOM 위치와 무관

모듈이 더한 것 넷:
  - invoker fallback   commandfor/command 미지원 브라우저
  - trigger state      aria-expanded를 모든 트리거에 미러
  - initial focus      첫 컨트롤이 아니라 dialog 자신 (author autofocus는 존중)
  - motion             WAAPI
  - drag handle        bottom sheet 두 높이

## 모션: material-web choreography를 비율로 가져옴

material-web dialog/internal/animations.ts 기준.
  open   dialog     translate -50px -> 0
         container  높이의 35%에서 전체로 clip
         scrim      linear fade in
         content    첫 20% 대기 후 duration 절반 동안 fade
         actions    첫 50% 대기 후 0.6 동안 fade
  close  역방향, slots는 2/3 동안 fade out

비율은 material-web의 250/300 of 500ms. 속도가 바뀌어도 슬롯이 보조를 맞춤.
material-web은 inner container 높이를 애니메이트하지만 이 dialog는 inner가 없어서
clip-path로 대체 — 내용을 짓누르지 않고 드러냄. open clip이 박스 밖에서 끝나므로
elevation 그림자가 잘렸다 되돌아오지 않음.

CSS transition이 아니라 WAAPI인 이유: 조각마다 시작 오프셋이 다르고, 닫기가
dialog보다 먼저 끝나야 함. native close는 즉시 숨김. 그래서 close 요청(close 커맨드,
Escape, scrim 클릭)을 preventDefault로 붙잡고 → 모션 → close().

Chrome 실측 기록: command와 cancel 둘 다 cancelable, ::backdrop이 WAAPI를 받음,
토큰의 cubic-bezier가 easing으로 통과.
reduced motion 또는 WAAPI 없음 → 즉시 열고 닫음.

시트 모션은 M3가 발행하지 않아서 "시트로 읽히는 가장 단순한 모션"이라고 명시.

## standard side sheet = 페이지를 공유함  ← 소유자가 지목한 자리

M3 standard side sheet은 medium 이상에서 콘텐츠 **옆에** 앉고, compact에서는 modal.
구현:
  - docked + medium 이상  →  측정된 폭이 <html>의 --axismundi-dialog-push 가 되고
                             edge 클래스가 블록테마 root에 그만큼 padding
                             padding이 시트 모션을 따라 움직임
  - compact               →  같은 시트가 modal로 열림. 닫히면 복원
  - 경계를 넘으면          →  열려 있던 쪽이 닫힘
  - page-share="move"     →  페이지 전체를 시트 폭만큼 translate (off-canvas drawer)
                             compact 포함 모든 윈도우. 그게 그 모드의 목적

599px = M3 compact (surface.yml / layout.yml)

→ React 표면에서 같은 규칙이 필요하지만 push 대상이 블록테마 root가 아니라 Scaffold.
  그리고 scroll-ownership 3-2절 금지(.ax-scaffold 계열은 visible 외 overflow 금지)와
  정면으로 만나는 지점. 이 문서가 그 금지를 해제하거나 유지함.

## 상태 보관

  motions        WeakMap  dialog -> 돌고 있는 애니메이션, 닫는 중인지
  openedHere     WeakSet  여기서 연 것. toggle이 한 task 뒤에 와서 두 번 시작 방지
  pushes         Map      시트 -> root, edge, size observer
  compactModals  Map      compact에서 modal로 연 standard 시트 -> 복원할 것

## 이벤트

toggle은 <dialog>에서 열릴 때와 닫힐 때 모두 발생하고 **버블하지 않음** → capture로 청취.
문서 위임인 이유: page-end 렌더가 이 모듈 실행 뒤에 surface를 추가할 수 있음.

## React 표면으로 옮길 때의 질문 (아직 답 없음)

  1. <dialog> + showModal()을 React에서도 쓰는가  → 쓰면 위 넷이 전부 공짜
  2. standard side sheet의 push 대상 = Scaffold. 3-2절 금지와의 관계
  3. motion 비율을 공유하는가, 아니면 표면이 자기 것을 갖는가
  4. drag handle의 두 높이는 M3 bottom sheet guidelines 발행값인지 확인 필요
  5. surface.yml이 857줄인데 prose 인용 6건 — 구조 대비 문장 근거가 얇음
