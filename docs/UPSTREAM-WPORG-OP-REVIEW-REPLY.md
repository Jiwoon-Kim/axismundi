# Draft — wp.org 리뷰 답장 (Axismundi Object Projections)

> 상태: **초안.** 리뷰 이메일 `AUTOPREREVIEW axismundi-object-projections/kimjiwoon/21Sep26/T1`에
> 대한 답장. 업로드(0.1.1) 뒤 **같은 이메일 스레드에 회신**.
>
> 리뷰어 요청: 짧고 직접적으로. 변경 목록 나열 금지(전체를 다시 검토한다고 명시). 다음 검토에
> 도움이 될 맥락만.
>
> 슬러그 변경 요청 없음 — `axismundi-object-projections` 유지.
>
> 문구 교정(2026-09-23, 사용자): "꺼져 있으면 외부 요청을 전혀 하지 않는다"는 너무 넓다.
> 관리자가 Tools 화면에서 주소를 직접 조회하는 경로는 그대로 있다. 따라서 범위를 **자동
> fetch의 예약·실행**과 **애널리틱스·텔레메트리 없음**으로 좁혀 쓴다.

## Body (paste as is)

Thanks — 0.1.1 is uploaded.

On the phoning home report: background acquisition is now opt-in and disabled by default.
When disabled, the plugin does not schedule or perform automatic remote-object fetches; it
does not send analytics or telemetry. Disabling it also clears anything already queued, and
a queued job re-checks the setting before it would reach the network. Nothing is sent to our
own servers in any configuration.

The remote requests this plugin can make are fetches of an ActivityStreams document at an
address the administrator supplied or that arrived in a signed inbox activity, which is how
this plugin renders an announced post as something other than a bare link. That is described
in the readme under External services.

Escaping, the filtered markup and the i18n calls are addressed as well.
<!-- end of body -->
