# Gutenberg 작업용 로컬 툴체인

`C:/Users/thaum/dev/gutenberg`에서 `npm` 명령이 전부 실패할 때 읽는다. 2026-09-24에 하루를
쓴 문제라 해결 절차만 남긴다. 메모리가 아니라 문서인 이유는, 요구 버전이 바뀌면 아래 **확인**
단계가 새 값을 알려주기 때문이다. 값을 외우지 말고 매번 읽을 것.

## 증상

```txt
npm error EBADDEVENGINES Invalid semver version ">=24.18.0" does not match "v24.15.0" for "runtime"
npm error EBADDEVENGINES Invalid semver version ">=11.16.0" does not match "11.12.1" for "packageManager"
```

`npm run build`, `npm run test:unit`, `npm run wp-env`, 그리고 **husky pre-commit 훅**까지
전부 막힌다. 훅이 막힌다고 `--no-verify`로 우회하지 않는다(AGENTS 금지 사항).

## 확인 — 무엇을 요구하는지 먼저 읽는다

```bash
cd /c/Users/thaum/dev/gutenberg
node -e "const p=require('./package.json'); console.log(JSON.stringify(p.devEngines))"
```

2026-09-24 기준 `node >= 24.18.0`, `npm >= 11.16.0`. 시스템에 깔린 것은 node 24.15 / npm 11.12라
둘 다 미달이었다.

## 왜 PATH 하나로 안 끝나는가

`npm run X`가 내부에서 `cmd.exe /d /s /c npm run Y`를 다시 부르고, 그 중첩 호출마다 검사가
다시 돈다. 그래서 **자식 프로세스가 찾는 `npm`도** 새 것이어야 한다. 그런데 찾는 이름이 셸마다
다르다.

```txt
cmd.exe    → npm.cmd
Git Bash   → 확장자 없는 npm  (sh 스크립트)
```

한쪽만 두면 다른 쪽이 `C:\Program Files\nodejs`의 구버전으로 떨어진다. 실제로 겪은 함정 둘:

- 휴대용 node 배포본의 `npm.cmd`를 복사해도 `%~dp0\node_modules\npm\bin\npm-cli.js`를 찾는데,
  그 배포본은 `bin/`이 비어 있는 부분 설치였다.
- 그 배포본의 최상위 `npm`은 파일이 아니라 **디렉터리**라, Git Bash가 그걸 잡아 아무것도 실행하지
  못했다. 그래서 shim은 별도 디렉터리에 둔다.

## 설치

1. node 24.18 이상 zip 배포본을 받아 푼다(설치 관리자 말고 zip — 시스템 node를 건드리지 않는다).
2. 최신 npm을 격리 설치한다. 전역 `npm install -g`는 쓰지 않는다.

```bash
npm install --prefix "<NPMHOME>" npm@latest --no-audit --no-fund
```

3. shim 디렉터리를 하나 만들고 두 파일을 넣는다. `<NODEDIR>`, `<NPMHOME>`은 위 경로.

`<BIN>/npm` (sh, 실행권한 필요):

```sh
#!/bin/sh
exec "<NODEDIR>/node.exe" "<NPMHOME>/node_modules/npm/bin/npm-cli.js" "$@"
```

`<BIN>/npm.cmd` (CRLF, 경로는 역슬래시):

```bat
@ECHO OFF
"<NODEDIR>\node.exe" "<NPMHOME>\node_modules\npm\bin\npm-cli.js" %*
```

> `.cmd`를 셸 heredoc이나 `printf`로 쓰지 말 것. 백슬래시가 이스케이프로 먹혀
> `node.exe`가 개행이 된다(AGENTS "이 기계" 항목의 그 함정). 파일 편집 도구나 Python
> `io.open(..., newline='\r\n')`으로 쓴다.

## 사용

```bash
export PATH="<BIN>:<NODEDIR>:$PATH"
node -v   # 24.18 이상
npm -v    # 11.16 이상
```

이 PATH면 `npm run build`, `npm run test:unit`, `git commit`(husky 훅 포함)이 모두 통과한다.

## 브라우저 테스트

`*.browser.test.jsx`는 Playwright 브라우저가 있어야 돈다. 없으면
`browserType.launch: Executable doesn't exist`로 죽는다.

```bash
npm exec --no -- playwright install chromium
```

`npx`는 쓰지 않는다(AGENTS: 레지스트리에서 아무 패키지나 받아올 수 있음).

## 같이 겪은 것 — wp-env

이 기계에서 `npm run wp-env start`가 `Could not find the current WordPress version in the
cache and the network is not available`로 실패한다. wp-env가 `dns.resolve('WordPress.org')`로
온라인 여부를 판단하는데 raw DNS가 막혀 있어 오프라인으로 오판한다(HTTPS는 정상).

**일반 지식이 아니라 이 환경 특유의 상황이므로 우회는 기록만 한다.** 캐시 파일에 버전을 심으면
기동은 되지만, 코어를 못 받아 `This does not seem to be a WordPress installation`이 뒤따른다.
즉 온전한 우회가 아니다. wp-env가 필요한 측정은 다른 방법을 찾는 편이 빨랐다 — 2026-09-24에는
엔진 함수를 직접 호출해 답을 얻었다(#83459).
