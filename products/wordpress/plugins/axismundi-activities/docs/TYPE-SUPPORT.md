# Activity 타입 지원 범위

2026-11-02 실측. **타입을 기록할 수 있는 것과 그 효과를 구현한 것은 다르다** -- 그래서 한 줄짜리
"지원 목록" 은 쓸 수 없고 네 칸으로 나눈다.

```txt
어휘 등록    OP 어휘 레지스트리가 그 용어를 아는가
원장 수용    Activity 원장이 그 타입의 행을 쓸 수 있는가
C2S 제출     로컬 클라이언트가 outbox 로 제출할 수 있는가
도메인 집행  수신했을 때 실제로 상태가 바뀌는가
```

## 앞의 두 칸은 빈 자리가 없다

`axismundi_op_vocabulary_types()` 를 `axismundi_op_vocabulary_is_a($term,'Activity')` 로 걸러
덤프한 결과 **32개**다.

```txt
Accept Activity Add Announce Arrive Block Create Delete Dislike EmojiReact Flag
Follow Ignore IntransitiveActivity Invite Join Leave Like Listen Move Offer
Question QuoteRequest Read Reject Remove TentativeAccept TentativeReject Travel
Undo Update View
```

AS2 활동 타입 28개 전부에 `EmojiReact`·`QuoteRequest`·추상 둘이다. 원장 허용 타입은
**고정 목록이 아니라 이 레지스트리에서 수집**된다(`includes/repository.php:278-283`). 그러므로
**"어휘·원장 칸에 미지원" 이라는 범주는 존재하지 않는다.** 그 두 칸을 근거로 타입 커버리지 작업을
계획하면 없는 일을 만드는 것이다.

> 측정 함정: 타입 문자열을 grep 해서 세면 레지스트리를 안 읽은 것이 되고, `ax_forum` 처럼
> `WP_Error` 코드 접두사와 겹치는 토큰은 결합도를 과장한다. 둘 다 이 문서를 쓰는 과정에서
> 실제로 틀린 적이 있다.

## 뒤의 두 칸

측정 범위는 **Activities · Bridge · OP · Note · Forum** 이다. **Calendar 는 보류** -- JMAP 의존
리팩토링 때 다시 보기로 했으므로 지금 세어 기록하면 곧 교체될 상태를 남긴다(Event 투영 포함.
Event 는 별도 플러그인이 아니라 Calendar 의 CPT + OP `Event` 투영이다).

```txt
타입              C2S 제출   도메인 집행
Create               O          O
Delete               O          O
Like                 O          O
Undo                 O          O
Add                  O          O     컬렉션 연산 -- featured
Remove               O          O     컬렉션 연산 -- featured
Move                 -          -     컬렉션 연산.  아래 참조
Accept               -          O
Reject               -          O
Follow               -          O
Block                -          O
Announce             -          O
Update               -          O
Dislike              -          O
EmojiReact           -          O
QuoteRequest         -          O
Flag                 -          -     피어가 보내는데 못 받는다
Ignore               -          -     로컬 전용 개념 (mute)
Invite Join Leave    -          -
Offer                -          -
Arrive Travel        -          -     intransitive
Listen Read View     -          -
TentativeAccept      -          -     Calendar 에 있으나 보류 범위
TentativeReject      -          -     같음
Question             -          -     레지스트리상 Activity 하위지만 우리는 object_type 으로 쓴다
```

`Question` 은 설명이 필요하다. AS2 에서 `Question` 은 `IntransitiveActivity` 하위라 레지스트리가
Activity 로 분류하는데, 우리 구현에서는 **투표의 object type** 이다. 그러므로 위 표의 "집행 없음"
은 활동으로서 집행하지 않는다는 뜻이고, object 로서는 쓰이고 있다.

## Move 와 계정 이주는 두 행이다

하나의 "Move 지원" 항목으로 뭉치면 무관한 두 작업이 섞인다.

```txt
AS2 Move       컬렉션 연산.  "the actor has moved object from origin to target"
               예제가 List A -> List B 이고 Add/Remove 와 한 가족
               원장이 이미 가족으로 다룬다: repository.php:1174 이 Add|Remove|Move 에 target 요구
               → 집행은 컬렉션 슬라이스(Announce 중첩 · moderators)와 같은 이음매로 들어온다
               → 레미의 커뮤니티 이전(모더레이터를 맨 앞으로)과 미디어컬렉션의 origin 사용이 이 모양
계정 이주       Activity 타입이 아니라 **속성**이다.  movedTo
               출처: ActivityPub Miscellaneous Terms
               (Final Community Group Report, 2026-08-28, Social Web Incubator CG)
               상태: "It is not a W3C Standard nor is it on the W3C Standards Track"
               정의하는 속성 셋: manuallyApprovesFollowers · movedTo · sensitive
               movedTo 는 domain/range 가 Actor 이고 functional, 컨텍스트는
               https://purl.archive.org/miscellany
               alsoKnownAs 는 그 보고서에 없다 -- AS2 에도 AP 에도 없고
               Mastodon 이 자기 컨텍스트에서 as:alsoKnownAs 로 선언해 쓴다 (de-facto)
               → 별개 작업 항목.  권위 층이 다르다 (→ actors/docs/PRINCIPAL-BOUNDARIES.md)
```

같은 표에 들어갈 수 없다는 것이 요점이다. 하나는 활동 타입이고 하나는 액터 문서의 속성이다.

## 그래서 남는 일

```txt
집행 없음 중 피어가 실제로 보내는 것   Flag
Calendar 보류에 묶인 것                 TentativeAccept · TentativeReject
무해 수용만 하면 되는 것                Arrive Travel Listen Read View
로컬 전용                               Ignore
별개 항목                               계정 이주 (movedTo)
```

**"스펙 전체 타입 구현" 이라고 부를 커버리지 작업은 앞 두 칸에는 없다.** 뒤 두 칸은 쓸어담는
작업이 아니라 기능별 결정이다. 특히 **C2S 제출 칸은 좁게 두고 나중에 넓히는 쪽이 안전**하다 --
풀어놨다 조이면 쓰던 기능이 사라진다. 수신 집행은 반대로 넓게 두고 나중에 조여도 된다.
(→ actors/docs/PRINCIPAL-BOUNDARIES.md 의 축 세 개)
