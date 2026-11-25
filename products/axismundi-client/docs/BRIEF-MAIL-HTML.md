# Brief — HTML 메일 표시와 `cid:` 인라인 이미지

> **범위**: 코어 + Tauri + GUI. 읽기 창(스레드 안의 각 메시지 포함)에 HTML 본문을 격리해 표시하고, 본문이 참조하는
> `cid:` 인라인 이미지를 JMAP blob 으로 가져와 보인다. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 읽기 창은
> [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md), 메서드 범위는 [MATRIX-JMAP-MAIL.md](MATRIX-JMAP-MAIL.md).

2026-11-25 작성·확정. "소유자 결정" 과 "측정" 은 확정, 나머지는 착수 지시다. 측정이 지시와
어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
목표      Email/get 으로 받은 상세(RFC 8621 §4.1) 중 읽기 창에 필요한 것만 보강 — HTML 본문, cid 인라인 이미지.
          MIME 파싱은 서버(Email/parse 안 씀), HTML 정제·격리는 클라이언트
재사용    HTML 격리·렌더링은 도메인 중립 부품 — 나중에 ActivityPub content(HTML)도 같은 부품을 쓴다.
          리소스 해석(cid:·원격)과 링크 처리는 호출자 정책으로 주입한다.  Mail 의 cid 해석은 Mail 에 둔다
blob      cid 와 이후 첨부 다운로드는 jmap::blob 다운로드 경로를 공유(나중에 FileNode 도).  업로드 영역 분리는
          그대로(WP Media · JMAP File · AP uploadMedia 섞지 않음).  blob 접근은 Account 단위(accountId + blobId)
후속      첨부 저장·열기, JMAP Email JSON 검사, 원본 MIME 보기, Files(FileNode) 탐색기 — 이번 범위 밖
원격      기본 차단.  "Show remote images" 는 그 메시지에만, 이미지만 허용(원격 CSS·폰트 등 다른 외부 콘텐츠는 계속
          차단), 저장 안 함.  cid 인라인 이미지는 차단과 무관하게 표시.  = Bulwark 의 "항상 묻기".
          나중 설정 후보: 항상 묻기/차단/허용.  신뢰하는 보낸 사람은 보류 — 표시된 From 이 같다고 자동 신뢰하지 않는다
          (Stalwart 가 위조 From 을 배달함을 측정함, BRIEF-MAIL-COMPOSE).  Bulwark 의 "신뢰 발신자" AddressBook 은 JMAP
          Contacts role 이 아닌 클라이언트 관례(NON-STANDARD) — Contacts 작업 때 판단
본문 선택 온전히 받아 정제 후 표시할 수 있는 HTML 우선.  HTML 이 잘렸거나 표시할 수 없으면 평문 대안으로.
          평문 대안이 없는 HTML 전용 메일이 잘렸으면 표시하되 "잘림" 안내.  평문/HTML 전환은 후속
본문 테마  기본은 앱의 현재 테마를 따른다. 읽기 설정에서 앱 테마 따르기 / 라이트 / 다크 중 하나를 선택한다.
          선택은 저장하며 HTML 본문에만 적용한다. 헤더·첨부 목록은 계속 앱 테마를 따른다.
          메시지마다 라이트/다크 토글(저장 안 함) — 읽기 어려우면 사용자가 전환한다.
          발신자 CSS(인라인·<style>)는 정제 후 그대로 렌더한다.  앱 쪽 기본 토큰·스타일은 @layer 로 낮은 우선순위.
          **갱신(2026-11-26, 소유자가 창에서 비교 후 채택)**: 라이트 = 발신자 원본 그대로.  다크 = 읽기용 대비 보정 —
          대비 4.5:1 미만 글자는 on-surface(링크는 primary), 밝은 무채색 패널은 surface-container, 버튼형 링크는
          색 유지·밑줄 제거.  색은 전부 M3 토큰, !important 는 이 보정 규칙에만, 라이트로 전환하면 보정 제거.
          보정은 첫 표시 전에 끝나야 한다(깜빡임 금지)
          알려진 잔여(2026-11-26 소유자 통과 판정): Show 로 원격 이미지를 허용할 때 보정이 다시 적용되며 짧게 깜빡인다.
          전체 흰색 깜빡임은 없음.  후속에서 보정을 srcdoc 생성 단계로 옮겨 없앤다
정제      1차는 DOMPurify 하나 + sandbox + CSP 를 별도로.  표준 Sanitizer API 는 대상 WebView2 에서 동작·정책이
          검증된 뒤 대체 후보
설정 화면 이번에는 읽기 설정에 HTML 본문 테마만 추가한다. 다른 읽기·첨부·개인정보 설정은 후속 설계 참고
```

## 측정 — Stalwart 1.1.0 (2026-11-25, alice Account c, 프로브 메일 만들고 지움)

```txt
구조       multipart/mixed[ alternative[ text/plain, related[ text/html, image/png cid=logo@probe inline ] ],
           application/pdf attachment ] 로 만든 메일 →
             textBody   [2 text/plain]
             htmlBody   [4 text/html]                       ← 인라인 이미지는 htmlBody 에 없다
             attachments[5 image/png cid=logo@probe inline, 6 application/pdf attachment]
           → cid 해석은 attachments 에서 찾는다.  HTML 이 참조하는 cid 파트는 첨부 목록에서 숨긴다(§4.1.4 알고리즘)
본문 값    bodyValues 의 HTML 은 원문 그대로 — <script>·원격 <img> 포함.  정제는 전부 클라이언트 몫
hasAttachment  true (pdf 때문).  preview 는 text/plain 대안에서
다운로드   downloadUrl(accountId, blobId, type, name) + 인증 헤더 → 200 image/png.  인증이 필요하므로 웹뷰/iframe 이
           직접 받을 수 없다 — 코어(Rust)가 받아 바이트를 넘긴다
```

RFC 8621 §9.2(HTML 표시): MUST 없음, 권고 — 검증된 라이브러리·화이트리스트, 렌더러와 같은 파서로 정제, iframe
sandbox(스크립트·폼 차단, CSS 격리, 고유 origin), 강한 CSP, referrer policy, `crossorigin=anonymous`, `target=_blank`
에 `rel=noopener`. **§9.3 MUST**: 각 파트를 따로 렌더링, 원문 텍스트 값을 이어 붙여 렌더링하지 않는다.

Bulwark 1.21.3 (`D:/axismundi-interop/bulwark-webmail-1.21.3`, AGPL — 참고만, 코드 복사 금지):
`lib/email-sanitization.ts` DOMPurify 설정, `components/email/email-viewer.tsx` — `srcdoc` iframe,
`sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"`(스크립트 허용 없음), srcdoc 안 meta CSP
(`default-src 'none'; img-src data: blob:` — 원격 허용 시 http(s) 추가, `form-action 'none'`), `referrer no-referrer`,
cid 를 미리 `blob:` URL 로 바꿔 넣음, SVG data: 는 제외.

## 이미 있는 것 — 읽고 시작

```txt
코어   mail::read — fetchHTMLBodyValues 이미 true, maxBodyValueBytes = MAX_BODY_VALUE_BYTES(65536)
       jmap::blob::download — RFC 8620 다운로드(인증 포함)
Tauri  mail.rs fn body() — textBody 의 text/plain 만 보고, 없으면 Body::HtmlOnly. **여러 text 파트를 "\n\n" 로 이어
       붙인다(§9.3 MUST 위반 — 이번에 고친다)**.  테스트 "HTML is never handed to the webview"(mail.rs:979) 는 이번
       변경으로 의도적으로 바뀐다 — 지우지 말고 새 계약(정제 전 HTML 은 격리 부품으로만 간다)으로 바꿔 쓸 것
       tauri.conf.json security.csp = null (앱 CSP 없음 — 이번 범위 밖, 아래 "보고")
GUI    mail-detail(.ts/.html) — Body 종류별 문구, 첨부 목록(이름·크기), 스레드 안 여러 메시지
       @tauri-apps/plugin-opener 의존성 이미 있음(외부 링크를 시스템 브라우저로)
```

## 지시

1. **본문 모델(Tauri `DetailView.body`)**: 파트 목록으로 바꾼다 — 각 항목 `{kind: text|html, partId, value, truncated,
   encodingProblem}`. HTML 은 `htmlBody` 의 `text/html` 파트, 없으면 `textBody` 의 text/plain. 파트끼리 이어 붙이지 않는다
   (§9.3). GUI 는 파트마다 따로 그린다(텍스트는 텍스트 노드, HTML 은 지시 3 의 부품 하나씩).
   - HTML 크기: 65536 은 뉴스레터에 작다. 상세 읽기에서 HTML 은 더 큰 한도(예: 1MB, 실측해 정함)로 받는다. 그래도
     `isTruncated` 면 평문 대안으로(소유자 결정 "본문 선택"), 평문이 없을 때만 잘린 HTML + "잘림" 안내.
2. **인라인 파트 목록**: `attachments` 중 `cid` 가 있고 HTML 이 `cid:<값>` 으로 참조하는 파트를 "inline" 으로 표시해
   넘긴다(cid 비교는 `<>` 제거·대소문자 규칙을 RFC 2392 대로). 첨부 목록에서는 이 파트를 뺀다. 참조되지 않는 cid
   파트는 첨부로 남긴다.
3. **격리 HTML 부품**(`safe-html-frame` — Mail 비의존, 이름·위치는 판단해 보고):
   - 입력: HTML 문자열, `resolveResource(url) → 허용된 URL | null`, `openLink(href)`, `allowRemote: boolean`.
   - 정제: 렌더링하는 엔진(WebView2)의 파서로 — DOMPurify(웹뷰 DOM 파서 사용 — 렌더러와 같은 파서). 화이트리스트. `script`·`iframe`·
     `object`·`embed`·`form`·이벤트 속성·`javascript:` 제거. `<link rel=stylesheet>`·`@import` 등 원격 CSS 경로도 차단.
     SVG data: 제외(래스터 data: 만).
   - 격리: `srcdoc` iframe, `sandbox` 에 **`allow-scripts` 를 절대 넣지 않는다**. 높이 맞춤 때문에 `allow-same-origin`
     을 쓸지(Bulwark 방식) 고유 origin 으로 두고 iframe 안 스크롤로 갈지 — 측정해 고르고 이유를 보고.
     **`allow-scripts` + `allow-same-origin` 조합은 금지**(격리가 사라진다).
   - srcdoc 안 meta CSP: `default-src 'none'; img-src data: blob:` (allowRemote 면 **img-src 에만** `https:` 추가 —
     `http:` 는 넣을지 판단해 보고; font·style·media 의 원격은 계속 차단), `style-src 'unsafe-inline'`, `form-action 'none'`, `base-uri 'none'`, `frame-src 'none'`.
     `<meta name="referrer" content="no-referrer">`, 원격 img 에 `crossorigin="anonymous"`.
   - 링크: iframe 안에서 이동하지 않는다. 클릭을 가로채 `openLink` 로 — Mail 은 `http(s)`·`mailto` 만 허용하고 시스템
     브라우저로(plugin-opener). `mailto:` 는 일단 시스템 처리(작성 창 연결은 후속).
   - 표면: HTML 본문은 읽기 설정에서 결정한 테마를 사용한다. 가로 넘침은 iframe 안 가로 스크롤.
4. **cid 이미지**: Tauri `mail_part_bytes(connectionId, accountId, blobId)` 같은 명령 — 코어 `jmap::blob::download` 로 받아
   바이트를 넘긴다. GUI 는 그 메시지가 참조하는 inline 파트만, 래스터 이미지 타입만(`image/png·jpeg·gif·webp·avif·bmp`),
   크기 상한(실측해 정함, 넘으면 깨진 이미지 자리표시) 으로 받아 `blob:` 또는 `data:` URL 로 바꿔 넣는다 — 고유 origin
   iframe 에서 부모의 `blob:` URL 이 열리는지 먼저 실측하고 안 되면 `data:`. 메시지를 닫으면 만든 `blob:` URL 을 해제.
   이번엔 디스크 캐시 없음(바이트 원장은 첨부 브리프에서).
5. **원격 이미지**: 차단 중이면 본문 위에 "Remote images are blocked · Show" 한 줄 — 원격 참조가 실제로 있을
   때만. 누르면 그 메시지만 `allowRemote: true`(이미지만) 로 다시 그린다. 저장하지 않는다.
6. **AP 재사용 경계 확인**: 부품 API 가 Mail 타입(Email·partId·cid)을 하나도 모르는지 — 테스트에서 Mail 없이 부품만으로
   렌더링해 보인다.

## 하지 않는 것

첨부 저장·열기·미리보기, 디스크 캐시·바이트 원장, 보낸 사람별 원격 허용 목록, HTML/텍스트 전환 토글, 발신자 색 변환·재매핑,
JMAP Email JSON 검사·원본 MIME 보기, Files/FileNode, 작성 창의 HTML 작성, ActivityPub 화면 적용, 앱 전체 CSP 설정,
S/MIME·PGP.

## 검증

```txt
cargo test   Tauri: 본문 파트 목록(텍스트 여러 파트를 이어 붙이지 않음), HTML 우선 선택, inline 파트 분류(참조된 cid 만),
             첨부 목록에서 inline 제외 — fixture 갱신(AXISMUNDI_UPDATE_FIXTURES=1)
node --test "gui/src/**/*.spec.ts"   cid 비교·추출, 원격 참조 감지, 허용 URL 판정
npm --prefix gui run build
```

창에서(원격 디버깅 CDP — gui/AGENTS.md). 테스트 메일은 측정과 같은 구조로 외부 Email/set(끝에 지움), 하나에 다음을 넣는다:
`<script>`, `onerror` 속성, `<form>`, `javascript:` 링크, 원격 `<img>`·원격 CSS `@import`·`<link>`, `position:fixed`
요소, cid 이미지, `target=_blank` 링크.

1. 스크립트·이벤트 속성·폼이 동작하지 않음(전역 변수·네트워크 요청으로 확인), `position:fixed` 가 iframe 밖으로 안 나옴.
2. 원격 이미지·CSS 요청이 차단 상태에서 **네트워크에 한 건도 없음**(CDP Network 로 확인) → Show 후에만 요청.
3. cid 이미지 표시, 첨부 목록엔 pdf 만.
4. 링크 클릭 → 앱 안 이동 없음, 시스템 브라우저로(실제로 열지는 말고 openLink 호출만 확인해도 됨).
5. 스레드에서 메시지 여러 개가 각자 다른 iframe.
6. 좁은 창·넓은 창, 라이트·다크 테마 스크린샷.
   앱 테마 변경을 열린 본문이 즉시 따라가는지, 라이트/다크 고정 시 앱과 독립적으로 표시되고 선택이 유지되는지 확인.

## 보고에 같이 적을 것

- `allow-same-origin` 사용 여부와 이유, `blob:`/`data:` 선택 근거, HTML 크기 한도·이미지 크기 상한 실측값.
- 앱 전체 CSP 가 없다(`tauri.conf.json` `csp: null`) — 이번에 바꾸지 말고, 이 브리프의 격리가 앱 CSP 없이도 성립하는지
  (srcdoc 의 meta CSP 만으로) 확인 결과.

## 수정 — 창 검토 (2026-11-25, Opus, CDP 1400px, alice Inbox "[Axismundi] HTML + cid inline image test" — 지우지 않음)

확인됨: cid 이미지 표시(인증 blob → data: URL), 첨부 목록엔 pdf 만, iframe 안 script·form 0, `javascript:` 링크 제거,
링크는 `data-link` 로 가로챔, 원격 요청 Show 전 0 → 후 1, sandbox `allow-same-origin`(scripts 없음), CSP 는 지시대로.

```txt
1  Show 뒤에도 원격 이미지가 깨짐(naturalWidth 0).  원인: 원격 <img> 의 crossorigin="anonymous" — CORS 헤더 없는 서버
   (대부분의 메일 이미지 CDN, 측정한 w3.org 도 ACAO 없음)의 이미지는 로드 실패.  §9.2 권고를 브리프에 그대로 옮긴 실수
   → crossorigin 제거.  자격 증명 미전송은 다른 방법으로:
     (a) <iframe credentialless> — WebView2 지원 여부 실측
     (b) 안 되면 코어(Rust)가 쿠키 없이 원격 이미지를 받아 data: 로 넣는다(cid 와 같은 경로, 크기·타입 상한 동일)
   쿠키 전송을 조용히 허용하는 쪽으로 물러서지 않는다
2  본문 iframe 높이 240px 고정 + 안쪽 스크롤.  본문 높이에 맞춰 늘려 읽기 pane 하나만 스크롤되게
3  발신자 CSS 를 전부 지운다(ALLOWED_ATTR 10개, style·<style>·class·bgcolor·color·align 제거) — 실제 메일 대부분이
   서식 없는 텍스트가 된다.  소유자 결정(2026-11-25): 발신자 CSS 유지.  iframe 이 CSS 를 가두므로(§9.2) 인라인 style·
   <style>·class·표 속성(bgcolor·align·width·cellpadding 등)은 남긴다.  원격 CSS 의 위험은 코드 실행이 아니라 요청 자체
   (열람 추적 — 원격 이미지와 같은 통로)이므로 CSP 가 막는다: style-src 'unsafe-inline' 만(원격 <link>·@import 차단),
   font-src 는 앱 글꼴 경로만, CSS 의 url() 배경 이미지는 img-src 를 따른다(Show 전 차단).  sanitizer 가 CSS 를 깊게
   파싱할 필요는 없다 — 스크립트 통로(expression·behavior 등 구식 IE 문법 포함)만 제거
4  iframe 기본 스타일: 앱 M3 토큰의 light-dark() 정의를 넣고 iframe 루트의 color-scheme은 읽기 설정에서 결정한다.
   기본값(앱 테마 따르기)에서는 OS 선호가 아니라 앱의 현재 라이트/다크 값을 반영한다. 고정 설정은 iframe에만 적용한다.
   배경 surface·글자 on-surface·링크 primary·앱 타이포그래피를 기본값으로 사용하고 발신자 스타일을 우선한다.
   앱 기본 스타일은 @layer 안(낮은 우선순위), 발신자 CSS 는 손대지 않고 그대로.  메시지별 라이트/다크 토글은 iframe
   color-scheme 만 바꾼다(재매핑 없음).  토글 버튼 자리는 지금 읽기 창 툴바에 두고, 툴바 재배치는 읽기 창 UX 브리프에서
```

검증 대상 메일(admin Inbox, 지우지 않음 — 2026-11-25 import):
- Google Calendar 공유 알림 — `<style>`, 원격 폰트 `<link>`(Google Fonts), 인라인 스타일, https 원격 이미지
- 공공데이터포털 공지 — HTML 전용(평문 없음), 인라인 스타일, **http 추적 픽셀**(Show 후에도 요청 0 이어야 함)
- "이 날의 추억"(OneDrive, 네이버 경유) — multipart/related, `<style>`, **cid 인라인 이미지 5개**, https 원격 이미지 5개

검증 기준(이 절): Show 전 원격 요청 0, Show 후 이미지가 **실제로 표시**(naturalWidth > 0), 원격 요청에 Cookie 헤더 없음
(CDP Network 로 요청 헤더 확인), iframe 높이 = 본문 높이.

참고 — Bulwark 1.21.3 은 같은 메일에서 본문 텍스트 `<code>cid:gradient@…</code>` 까지 `blob:http://localhost:3000/…` 로
바꿔 버린다(문자열 치환으로 보임). 우리는 `img[src]` 만 바꾸고 텍스트는 그대로 둔다 — 현재 구현이 맞다, 따라 하지 말 것.

## 수정 2 — 리뷰 (2026-11-26, Opus)

```txt
1  자동 대비 보정 제거 — mail-body-contrast.ts 와 #app-contrast·data-reader-contrast 경로 전부.  소유자 결정 위반
   (발신자 CSS 그대로, 보정·재매핑 없음): 계산색을 읽어 !important 고정 RGB 로 덮었다.  @layer 는 !important 를
   안 쓰려고 둔 것이다.  다크에서 읽기 어려운 메일은 사용자가 메시지별 토글로 라이트로 본다
2  Show 때 흰색 깜빡임 — 새 srcdoc 의 #app-defaults(토큰)·color-scheme 을 load 뒤에 채운다(safe-html-frame:84,86-114).
   → 현재 토큰과 color-scheme 을 srcdoc 을 만들 때 처음부터 넣는다(<html style="color-scheme:…">, 토큰 @layer 포함).
   load 뒤 갱신은 테마가 실제로 바뀔 때만
3  cid 메일의 두 번째 재로드 — resolveResource 가 downloaded() 에 의존해 cid 를 받기 전 첫 렌더(이미지 제거) 뒤
   srcdoc 전체를 다시 만든다(mail-body:66).  "이 날의 추억"(cid 5) 이 열 때마다 깜빡이고 레이아웃이 튄다.
   → cid 다운로드가 끝난 뒤 첫 srcdoc 을 쓰거나, 이미지 상자는 두고 src 만 나중에 채운다(재로드 없이)
4  차단된 원격 이미지를 img.remove() — Show 때 레이아웃이 밀리고 alt 가 사라진다.  src 만 빼고 요소·크기·alt 는 둔다
5  (선택) 본문 기본 글자 크기를 Bulwark 처럼 14px 로 맞춘다면 리터럴이 아니라 M3 body-medium 토큰
```

검증: Show·테마 토글·cid 메일 열기에서 흰색 프레임이 한 번도 안 보임(CDP 로 load 직후 프레임 캡처 또는 스크린샷 연속),
"이 날의 추억" 을 열 때 srcdoc 할당 1회(카운트 로그), 다크에서 발신자 색이 원본 그대로(computed color 비교).

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
