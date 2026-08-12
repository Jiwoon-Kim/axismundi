# Axismundi Calendar — calendar systems

Status: design settled, unimplemented. Nothing in this document exists in code yet.

## What this is not

The lunar calendar is **not a system calendar**. A system calendar is a dataset of days somebody
publishes — 대한민국의 휴일 is one, and it has rows, a catalog, and an editor. The lunar calendar has
none of those: it is a *second way of naming the same day*. 2026-08-12 is not two events, it is one
day with two names.

That distinction decides almost everything below. A system calendar goes in the sidebar and can be
ticked off. A calendar system is a lens over every day the grid already draws.

Precisely: it is not a **`CalendarList` selection**. The annotation itself must still be switchable
in settings — grid composition, like the weekday column, which is also configurable and also not a
dataset you join. Turning off the lunar row is a display preference; turning off 대한민국의 휴일 is
leaving a dataset. Same verb, different object, and conflating them puts a display preference in the
sharing model.

## The layers

```
                          AbsoluteDay
                              │
       ┌──────────────────────┼──────────────────────┐
       ▼                      ▼                      ▼
   Gregorian            Korean lunisolar          Julian
  (canonical)            (representation)      (representation)
```

**Canonical storage stays Gregorian/ISO. Always.** `canonical_date`, `start_utc`, `end_utc` do not
change and do not grow a `calendar` column that could make them mean something else. WordPress,
`WP_Query`, REST, ActivityPub, iCalendar and schema.org all read ISO dates, and a stored date that
means a different day depending on a sibling column is a date nobody outside this plugin can read.

The internal type is **`AbsoluteDay`**, not `JDN`. It is an integer identifying one civil Gregorian
day, and `LunarMonth.startAbsoluteDay` means exactly that. The name matters more than it looks:
"Julian Day" drags the noon-UT convention along with it, and a provider that needs an astronomical
instant should reach for a separate `JulianDate`/UTC instant rather than reinterpreting the day
number it was already handed. Two things that differ by half a day must not share a type.

`AbsoluteDay` exists so that N calendar systems need `toAbsolute()`/`fromAbsolute()` rather than N×N
converters between each other.

## Two things that look alike and are not

```
CalendarDate            CalendarRecurrence
├─ calendar             ├─ calendar
├─ year                 ├─ month
├─ month                ├─ day
└─ day                  ├─ leapMonth
                        └─ fallback
```

`음력 1592년 4월 13일` is a historical date: the year carries meaning. A lunar birthday on
`음력 7월 1일` has no year at all — it is a rule, and next year lands on a different Gregorian day.
Forcing both through one field structure makes `year` mean "the year" in one row and "nothing" in
another, which is the kind of ambiguity that surfaces years later as a wrong birthday.

**A lunar birthday's Gregorian date is derived output, never the source of truth.** Storing
`2026-08-12, repeats yearly` records the wrong fact and will be wrong in 2027.

### Leap-month policy is a user's decision, not arithmetic

Somebody born on 윤4월 10일 has no birthday in a year with no 윤4월. Which of

```
none              — no birthday that year
regular-month     — 4월 10일 instead
following-month   — 5월 10일 instead
```

is right cannot be computed. It is a policy field on the recurrence, defaulted but never inferred.
This edge case alone is a good argument for opt-in: no installation should carry it to store a
birthday.

## Opt-in

Default UI is one field:

```
Birthday
[ 2026-08-12 ]
```

and, only where it makes sense (registered provider + relevant locale), one checkbox:

```
☐ 음력 생일
```

which reveals calendar system / month / day / leap month. Throwing Gregorian ∕ Korean ∕ Islamic ∕
Hebrew at every user is a worse default than not shipping the feature.

The plugin's identity here is **"Calendar semantics are extensible"**, not "we support the lunar
calendar." Korean lunisolar is the first reference implementation of the provider API.

## The KASI provider

Source: 한국천문연구원 음양력 정보, via 공공데이터포털 (data.go.kr), endpoint
`https://apis.data.go.kr/B090041/openapi/service/LrsrCldInfoService`.

Operations that matter:

| Operation | Takes | Used for |
| --- | --- | --- |
| `getLunCalInfo` | `solYear`, `solMonth`, optional `solDay` | month overlay (Gregorian → lunar) |
| `getSolCalInfo` | `lunYear`, `lunMonth`, `lunDay` | lunar → Gregorian |
| `getSpcifyLunCalInfo` | `fromSolYear`, `toSolYear`, `lunMonth`, `lunDay`, `leapMonth` | recurrence lookup over a year range |
| `getJulDayInfo` | `solJd` | Julian day → everything (convert at the boundary) |

### Rules

**The service key never reaches the browser.** WordPress proxies; the key lives in a constant or an
option and is write-only in the UI. A key in a JS bundle is a published key. Ship no key: each site
registers its own.

**The client asks for `/calendar/korean-lunisolar/{year}/{month}`, not `/kasi/…`.** The provider is
an implementation detail; a local ephemeris library replacing KASI later must not change the
contract.

**Coverage is a provider property, not an error.** KASI's stated range is `-59-02-13 ~ 2050-12-31`;
the OpenAPI guide does not restate it, so probe the boundaries. Outside the range the Gregorian
calendar renders exactly as before and the overlay is simply absent — never an error, never a blank
grid. This split (primary range ≠ provider range) is the second good reason for the abstraction.

### Cache: store the compressed model, not the responses

A month response is 28–31 rows, but the *facts* in it are three:

```php
[ 'startAbsoluteDay' => 739475, 'year' => 2026, 'month' => 7, 'leapMonth' => false, 'days' => 29 ]
```

~13 rows per year, and every date in the month follows from `absoluteDay - startAbsoluteDay`. That is exact
arithmetic, not interpolation.

**Do not sparsely sample and interpolate between anchors.** Between two anchors 94 days apart,
`29+30+29` and `30+29+30` are indistinguishable by total, and leap months make it worse. Month
boundaries are the irreducible input. Fetch monthly, extract the boundaries, discard the response.

Bootstrap cost is trivial: 1950–2050 is `101 × 12 = 1,212` requests against a 10,000/day dev quota,
producing ~1,250 stored rows. This is durable derived data, not a transient — an expiring cache
would re-spend a quota on facts that changed a millennium ago. Invalidate on a provider version
bump; extend by appending months when KASI's range grows.

For a lunar birthday, `getSpcifyLunCalInfo` answers ten years of occurrences in one request. That is
what it is for.

### Display

Annotation inside the day cell, not an event in the list:

```
┌─────────┐   ┌─────────┐   ┌─────────┐
│   12    │   │   13    │   │   14    │
│  6.30   │   │   7.1   │   │    2    │
└─────────┘   └─────────┘   └─────────┘
                 ↑ month shown on the first of the month, and only there
```

Leap months read `윤 7.1`.

## Astronomy is a different problem

Not this slice, recorded so the shape is not lost.

Holidays are *decided* and must be received from an authority. Most astronomical events are
*computed*, and receiving them is the weaker option:

- **Computed**: moon phases, equinoxes/solstices, perihelion/aphelion, conjunctions, oppositions,
  elongations. These are extrema of an ephemeris function, not a list to download.
- **Catalogued**: meteor showers (IAU Meteor Data Center — stored as *solar longitude of maximum*,
  not "August 12", so each year's peak is computed), comets, transients (JPL / MPC).
- **Either**: eclipses. Computable, but NASA's published circumstances carry contact times and the
  path of totality, which is Event + geodata and ties into the geo work.

**A moon phase is an instant, not an all-day event.** Full moon at 2026-08-28T00:30Z is the 28th in
Seoul and the 27th in Los Angeles. Storing the date discards the fact that produces both. Render it
as a day in month view if you like — but store the moment.

**Moon phase ≠ lunar date.** Related, not the same: 초하루 follows from 합삭 *plus* a civil rule and a
standard meridian, which is why KASI is authoritative for the calendar while the phase is just
astronomy. Google's "Phases of the Moon" ICS is worth having as a *validator* for a computation
engine, never as the source — an ICS is an output format here, not an input.

## Order of work

This is the order *within* the lunar work. Globally it comes after the workspace and event slices:

```
Browse calendars / workspace catalog UX
→ 일반 Event 작성 모델
→ provider registry + LunarMonth store
→ KASI client + secondary annotation
→ lunar recurrence / birthday
→ astronomy providers
```

1. Provider registry + `toAbsolute`/`fromAbsolute` + coverage range. No provider yet.
2. `LunarMonth` store and the JDN arithmetic over it. Unit-testable with fixtures, no network.
3. Server-side KASI client: key setting, month fetch, boundary extraction. SSRF rules as for ICS.
4. `GET /calendar/korean-lunisolar/{year}/{month}`.
5. Day-cell secondary label in the workspace grid, plus the "out of coverage" silence.
6. `CalendarRecurrence` storage + lunar birthday, `getSpcifyLunCalInfo` for occurrences.
7. Leap-month fallback policy.
8. Astronomy providers.

1–2 have no external dependency and are where the design is proven; 3 is the first step that can
fail for reasons outside the plugin.
