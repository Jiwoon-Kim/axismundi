# Draft — wp.org 리뷰 답장 (Axismundi Object Projections)

> 상태: **초안.** 리뷰 이메일 `AUTOPREREVIEW axismundi-object-projections/kimjiwoon/21Sep26/T1`에
> 대한 답장. 업로드(0.1.1) 뒤 **같은 이메일 스레드에 회신**.
>
> 리뷰어 요청: 짧고 직접적으로. 변경 목록 나열 금지(전체를 다시 검토한다고 명시). 다음 검토에
> 도움이 될 맥락만.
>
> 슬러그 변경 요청 없음 — `axismundi-object-projections` 유지.

## Body (paste as is)

Thanks — 0.1.1 is uploaded.

On the phoning home report: background acquisition is now a setting under Tools > Remote
Objects, off by default. While it is off the plugin makes no outbound request on its own;
the only remote requests are the ones an administrator starts on that screen. Turning the
setting off also clears anything already queued, and a queued job re-checks the setting
before it would reach the network. No analytics, no telemetry, and nothing is sent to our
own servers in any configuration.

The remote requests this plugin can make are fetches of an ActivityStreams document at an
address the administrator supplied or that arrived in a signed inbox activity, which is how
this plugin renders an announced post as something other than a bare link. That is described
in the readme under External services.

Escaping, the filtered markup and the i18n calls are addressed as well.
<!-- end of body -->
