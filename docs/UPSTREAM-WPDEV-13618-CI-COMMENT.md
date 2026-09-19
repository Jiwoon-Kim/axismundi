# Draft — wordpress-develop#13618 comment: PHPUnit matrix timeouts

> 상태: **게시됨** 2026-09-20 — [issuecomment-5743361482](https://github.com/WordPress/wordpress-develop/pull/13618#issuecomment-5743361482), `--github` 일치. 이어서 PR을 ready for review로 전환(draft=false, OPEN). 올릴 곳: [WordPress/wordpress-develop#13618](https://github.com/WordPress/wordpress-develop/pull/13618) 댓글(CI 결과라 코드 리뷰 범위).
>
> 사용자 결정(2026-09-20): 원인을 "infrastructure problem"으로 단정하지 않고 관측 사실만. 빈 커밋 재실행 유도 안 함(재실행 권한 없음: `push:false`, `maintain:false`). 댓글 뒤 draft → ready.
>
> 확인(2026-09-20, run `35452606788`):
> - 실패 5개(PHP 7.4/MariaDB 10.11 multisite, 8.0/MariaDB 10.11, 8.0/MariaDB 11.4, 8.3/MariaDB 11.8 multisite+memcached, 8.5/MySQL 5.7 multisite) 모두 `Build WordPress` 단계 `##[error]The action 'Build WordPress' has timed out after 5 minutes.`, PHPUnit 단계 미실행.
> - 로그: grunt `Done.` 15:43:21(시작 약 25초 뒤) → 15:48:08 타임아웃, 정리 시 `npm run build:dev`·`grunt` orphan 프로세스 종료.
> - 같은 run의 통과한 PHPUnit job들은 `Build WordPress` 14–29초.
> - trunk: run `35335345549`(2026-09-18 10:34), `35304568318`(03:48)에서 각 1개 job이 같은 오류.
> - 전체: pass 88, skipping 18, fail 5.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-WPDEV-13618-CI-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

The five PHPUnit matrix failures all time out in `Build WordPress` after Grunt reports `Done.`; PHPUnit does not start. The same timeout appears in recent trunk runs 35335345549 and 35304568318. The build step completed normally in the other matrix jobs.
<!-- end of body -->
