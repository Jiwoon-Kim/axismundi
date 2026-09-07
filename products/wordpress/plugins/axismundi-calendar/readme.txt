=== Axismundi Calendar ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0-beta.1
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: calendar, events, icalendar, activitypub, federation

Calendars, schedules, occurrences and events -- with JSCalendar and iCalendar as
the canonical shapes, and FEP-8a8e on the wire.

== Description ==

The time layer, in four parts that stay separate because collapsing any two of
them is what makes recurring events intractable later:

* **Calendar** -- a collection of events, and a subscribable resource.
* **Schedule** -- when something happens: timezone, start and end, recurrence.
* **Occurrence** -- one actual instance, and the exceptions that apply to it
  alone.
* **Event** -- what happens: body, place, organizer, participation policy.

Sharing follows the model people already know from Google Calendar and which
keeps two different questions apart: what a calendar's owner has granted, and
which calendars a person has chosen to see. Accepting an invitation adds a
calendar to your list; it does not grant anybody a permission.

Participation is an Activity before it is a row. A Join, an Accept, a Reject, a
withdrawal and an organizer's removal are each their own state transition with
their own federated act, so there is always something to undo and always an
answer to who said what when. Who may see the participant list is one
evaluator, asked with the viewer as an argument, and every projection --
JSCalendar `participants`, FEP `attendees`, remaining capacity -- goes through
it.

A cancelled event keeps its place in the calendar. Deleting it would leave
everybody who had cleared that evening with nothing to have been told about.

Secondary calendar systems (lunar and others) are formatted for the reader's
own locale rather than stored as labels.

= Without the federation stack =

Calendars, schedules, recurring events and iCalendar subscriptions all work with
nothing else installed. Axismundi Actors and Axismundi Object Projections add
ownership by an Actor and publication to other servers; Axismundi Activities
adds participation. A site using this purely as a calendar is never told to
install a federation stack.

== Changelog ==

= 0.1.0-beta.1 =
* First pre-release.
