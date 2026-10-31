# RSVP Transport

RSVP 가 사이트를 떠나고 돌아오는 경로. 결정은 2026-11-01 오너 확정이고, 표현
(JSCalendar · AS2 Event object)과 iMIP 수신은 **범위 밖**이다.

## 경계는 발신/수신이 아니라 "메일 수신" 이다

처음에 발신/수신으로 그었는데 그건 틀렸다. 어려운 절반은 **메일을 받는 것**이고, 응답을
전용 URL 로 받으면 그 절반이 사라진다.

```txt
최소 구현   초대·응답 메일 발신 -- text/calendar 에 METHOD 를 담아 wp_mail() 로
            + 전용 URL 로 응답 수신 -- 토큰 · POST 기록
            메일함 없음 · IMAP/JMAP 없음 · From 신뢰 결정 없음 -> 플러그인 하나
별도 슬라이스   메일로 들어오는 METHOD:REPLY 를 읽어 반영 (iMIP 수신)
            여기서만 메일함과 From 신뢰 결정이 필요하다
```

## 토큰이 자격증명이 된다

전용 URL 은 문제를 없애지 않고 옮긴다.

```txt
범위   참석자 1명당 하나.  이벤트 하나 · 응답만.  로그인이 아니다
수명   RSVP 는 바뀔 수 있으므로 일회용이 아니다.  이벤트까지 유효
성질   bearer 다.  메일에 평문으로 실려 가고 전달될 수 있다.  링크를 가진 사람은
       응답할 수 있다 -- Google Calendar · Eventbrite 도 같은 거래다
유출   Referer 와 프록시 로그에 남는다.  범위를 "응답만" 으로 좁히는 것이 피해 상한이다
```

## GET 으로 변경하지 않는다

**메일 스캐너가 URL 을 선취한다.** Outlook · Gmail 의 링크 보호가 수신 즉시 링크를 긁으므로,
GET 으로 응답을 기록하는 링크는 **사람이 열기 전에 승낙이 기록된다.**

```txt
링크는 페이지를 연다 -> 페이지에서 응답을 제출한다 (POST)
토큰은 페이지 접근용, 기록은 제출로
```

## 참석자 키는 이미 맞는 모양이다

참여 저장소는 전부 URI 문자열로 키잉한다(`participation_by_uri` ·
`event_participation_set` 등). iCalendar 의 `ATTENDEE` 가 `mailto:` URI 이므로 메일 초대
대상도 **새로운 키 종류 없이** `mailto:alice@example.com` 으로 들어간다.
`includes/ics-dataset.php:9` 가 이미 ORGANIZER · ATTENDEE · `mailto:` 를 다음 할 일로
적어 두었다.

다만 주변 코드가 `$actor_uri` 라는 이름과 Actor 전제로 쓰여 있다. **점검할 대상은 컬럼이
아니라 그 전제다.**

## 로컬 참석자는 사이트를 떠나지 않는다

주최자와 참석자가 둘 다 로컬 Account 면 알림이 사내 배달로 끝난다
(`axismundi-actors/docs/SYSTEM-ACTOR.md` 사내 메일 경계).

```txt
로컬 참석자   사내 메일 -- 외부 전송 없음
외부 참석자   초대는 외부 발신, 응답은 토큰 URL
              초대조차 "링크 복사" 로 두면 우리 쪽 외부 발신이 0 이 된다
```

## 받아들이는 거래

표준 캘린더 클라이언트(Outlook · Apple Calendar)는 초대 메일에 자기 Accept 버튼을 띄우고
`METHOD:REPLY` 메일을 보낸다. 메일 수신을 구현하지 않으면 **그 네이티브 응답은 우리에게
도달하지 않고**, 사용자는 우리 링크를 눌러야 한다. 받아들일 만하지만 **선택이라는 것을**
기록한다.

## 범위 밖인데 영향받지 않는 이유

메일 경로는 이미 출하되는 ICS 표면을 재사용한다(`includes/ics-dataset.php`, 공개 ICS 피드,
`tests/audit-ics.php`). iMIP 은 정의상 iCalendar-over-email 이므로 `text/calendar` +
`METHOD` 를 보내는 것은 **새 표현을 정하는 게 아니라 있는 표면을 쓰는 것**이다. JSCalendar 와
AS2 Event object 는 다른 표현 표면이고 메일 경로가 그 둘을 필요로 하지 않는다. 그래서
전송·흐름은 지금 정할 수 있고 표현은 미결로 남는다.

## 변하지 않는 제약

```txt
RSVP 엔진은 메일 프로토콜을 모른다.  메일은 그 서비스를 소비하는 어댑터다
wp_mail() 은 우리 플러그인이 하나도 없어도 작동해야 한다 -- 코어 알림이 우리 체인에
의존하면, Contacts 나 JMAP 이 깨질 때 관리자가 그 사실을 알리는 메일조차 받지 못한다
```

## 미결

```txt
옛 주소 배달        핸들 파생 주소가 바뀐 뒤 former_handle 로 해소할지
                    (axismundi-actors/docs/DATA-MODEL.md §9.2 미결과 같은 결정)
iMIP 수신           메일함 + From 신뢰
JSCalendar 표현 · AS2 Event object 표현
