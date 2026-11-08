# Spec Delta

## Purpose

Defines when a listing is on: its run from a start date to an end date, the optional specific days within that run, how people enter them, and how the listings pages, the bookmarks calendar, calendar export, duplication and the Office honour them.

## ADDED Requirements

### Requirement: A listing runs from its start date to its last day
Every listing SHALL have a start date. A listing MAY have an end date, which MUST NOT be before the start date. A listing with no end date, or with an end date equal to its start date, SHALL be on for that one day. Otherwise its run is every day from the start date to the end date inclusive, unless it has specific days.

#### Scenario: One-day listing
- **WHEN** a listing has a start date of 6 June 2026 and no end date
- **THEN** it is on 6 June 2026 only

#### Scenario: End date before start date
- **WHEN** someone saves a listing with a start date of 20 June 2026 and an end date of 1 June 2026
- **THEN** the listing is not saved and the end date field says it must be on or after the start date

### Requirement: A multi-day listing may have specific days
A listing whose run is longer than one day MAY have a set of specific days, each falling within its run. A listing with specific days SHALL be treated as on those days only. A listing without specific days SHALL be treated as on every day of its run, exactly as before this change.

#### Scenario: Listing with specific days
- **WHEN** a listing runs from 3 November to 15 December 2026 with specific days 3 November, 17 November, 1 December and 15 December
- **THEN** it is on those four days and on no other day

#### Scenario: Existing listing without specific days
- **WHEN** a listing created before this change runs from 2 to 30 November 2026
- **THEN** it is on every day from 2 to 30 November 2026

### Requirement: The Dashboard form offers specific days for multi-day runs
The Dashboard listing form SHALL show a "Set specific days" checkbox below Additional date information whenever the end date is after the start date, and SHALL hide it otherwise. Ticking it SHALL show a calendar, month by month with Monday first, of every day from the start date to the end date, each of which can be ticked or unticked.

#### Scenario: One-day event
- **WHEN** the form has a start date and no end date, or an end date equal to the start date
- **THEN** the "Set specific days" checkbox is not shown

#### Scenario: Multi-day event
- **WHEN** the start date is 1 June 2026 and the end date is 20 June 2026, and the person ticks "Set specific days"
- **THEN** a calendar of June 2026 appears with 1 to 20 June selectable and the other days of the month not selectable

#### Scenario: Editing a listing that has specific days
- **WHEN** someone opens the edit form of a listing with specific days
- **THEN** "Set specific days" is ticked and the calendar shows those days ticked

### Requirement: The calendar has select-all, clear-all and weekday shortcuts
The calendar SHALL offer Select all, which ticks every day of the run, and Clear all, which unticks them all. Each weekday button above the calendar, labelled Mon to Sun and outlined so it reads as a button, SHALL tick every day of that weekday in the run, or untick them when they are all already ticked. The calendar SHALL show how many days are chosen out of the days in the run.

#### Scenario: Gallery closed on Mondays
- **WHEN** the run is 12 September to 8 November 2026 and the person presses Select all, then Mon
- **THEN** every day except the eight Mondays is ticked and the calendar says 50 of 58 days are chosen

#### Scenario: Weekend festival
- **WHEN** the run is 1 to 20 June 2026, nothing is ticked, and the person presses Sat and Sun
- **THEN** 6, 7, 13, 14 and 20 June are ticked

#### Scenario: Weekday button when all of that weekday are ticked
- **WHEN** every Tuesday in the run is ticked and the person presses Tue
- **THEN** every Tuesday in the run is unticked and the other days are unchanged

### Requirement: Changing the run in the form leaves out days outside it
When the start or end date changes while days are ticked, the calendar SHALL redraw for the new run, leave any ticked days that fall outside it out of what is saved, and say how many were left out. A day left out SHALL be ticked again if the run is widened to reach it before saving, so a date mistyped on the way to the right one loses nothing.

#### Scenario: Run shortened
- **WHEN** 3 November, 17 November, 1 December and 15 December are ticked and the person changes the end date to 5 December
- **THEN** the calendar shows 3 of 33 days chosen and says one chosen day is outside the new dates, so will not be saved

#### Scenario: Run widened again
- **WHEN** the person then changes the end date back to 15 December before saving
- **THEN** 15 December is ticked again and the calendar shows 4 of 43 days chosen

### Requirement: Specific days are validated on save
When "Set specific days" is ticked, the submitted days SHALL all fall between the submitted start and end dates inclusive, and there SHALL be at least one. Otherwise the listing SHALL NOT be saved, and the form SHALL show an error by the calendar. When the checkbox is unticked, the listing SHALL be saved with no specific days.

#### Scenario: No day ticked
- **WHEN** someone saves with "Set specific days" ticked and no day ticked
- **THEN** the listing is not saved and the form asks them to choose at least one day

#### Scenario: Day outside the run
- **WHEN** a submission for a 1 to 20 June 2026 run includes 21 June 2026 as a specific day
- **THEN** the listing is not saved and the form says each day must fall between the start and end dates

#### Scenario: Checkbox unticked
- **WHEN** someone edits a listing that has specific days, unticks "Set specific days" and saves
- **THEN** the listing has no specific days and is on every day of its run

### Requirement: Saving with specific days tightens the run
When a listing is saved with specific days, its start date SHALL become the first chosen day and its end date the last chosen day, whatever dates were typed. The form SHALL tell the person, before they save, that this will happen.

#### Scenario: Pub quiz every other Tuesday
- **WHEN** someone saves a listing typed as 1 November to 24 December 2026 with 3 November, 17 November, 1 December and 15 December ticked
- **THEN** the listing is saved running from 3 November to 15 December 2026 with those four specific days

#### Scenario: Weekend festival
- **WHEN** someone saves a listing typed as 1 to 20 June 2026 with 6, 7, 13, 14 and 20 June ticked
- **THEN** the listing is saved running from 6 to 20 June 2026 with those five specific days

### Requirement: One chosen day or every chosen day saves as an ordinary listing
When exactly one day is chosen, the listing SHALL be saved as a one-day listing on that day with no end date and no specific days. When every day of the run is chosen, the listing SHALL be saved as an ordinary run with no specific days.

#### Scenario: One day chosen
- **WHEN** someone saves a listing typed as 1 to 20 June 2026 with only 13 June ticked
- **THEN** the listing is saved as a one-day listing on 13 June 2026 with no specific days

#### Scenario: Every day chosen
- **WHEN** someone presses Select all on a 2 to 30 November 2026 run and saves
- **THEN** the listing is saved running from 2 to 30 November 2026 with no specific days

### Requirement: Day-based listings pages honour specific days
The Today, Tomorrow, This week and Next week pages, and the page for a chosen date range, SHALL show a listing only if it is on at least one day in that page's window. For a listing with specific days, that means at least one specific day falls in the window.

#### Scenario: Today on a day the event is not on
- **WHEN** the weekend festival runs from 6 to 20 June 2026 on its five specific days and today is Wednesday 10 June 2026
- **THEN** the Today page does not show it

#### Scenario: Today on a day the event is on
- **WHEN** today is Saturday 13 June 2026
- **THEN** the Today page shows the weekend festival

#### Scenario: This week counts from today
- **WHEN** the pub quiz's specific days are 3 November, 17 November, 1 December and 15 December 2026, and the This week page is opened on Monday 16 November 2026
- **THEN** it shows the pub quiz, and opened on Wednesday 18 November 2026 it does not

#### Scenario: Chosen date range
- **WHEN** someone opens the listings for 18 to 30 November 2026
- **THEN** the pub quiz is not shown, because none of its specific days falls in that range

#### Scenario: Listing without specific days
- **WHEN** a listing runs from 2 to 30 November 2026 with no specific days and the Today page is opened on 10 November 2026
- **THEN** it is shown

### Requirement: Run-wide listings pages show a listing until its last day
The listings pages that are not about particular days (all listings, a venue's, a promoter's, a category's, and bookmarked listings) SHALL show a listing from now until its last day has passed, whether or not it has specific days.

#### Scenario: Between specific days
- **WHEN** the pub quiz runs from 3 November to 15 December 2026 and the all listings page is opened on 10 November 2026
- **THEN** the pub quiz is shown

#### Scenario: After the last specific day
- **WHEN** the all listings page is opened on 16 December 2026
- **THEN** the pub quiz is not shown

### Requirement: The bookmarks calendar shows only specific days
The calendar view of bookmarked listings SHALL place a listing with specific days only on those days, from today on. A listing without specific days SHALL be placed on every day of its run from today on, as now.

#### Scenario: Bookmarked pub quiz
- **WHEN** someone has bookmarked the pub quiz and views their bookmarks calendar on 10 November 2026
- **THEN** the pub quiz appears on 17 November, 1 December and 15 December and on no other day

### Requirement: Add to calendar writes one event per specific day
The calendar file for a listing with specific days SHALL hold one all-day event for each specific day, each with the listing's title, place, notes and link. Each day's event SHALL keep the same identity every time the file is downloaded, so importing it again updates rather than duplicates. A listing without specific days SHALL still get one event spanning its run.

#### Scenario: Pub quiz calendar file
- **WHEN** someone downloads the calendar file for the pub quiz
- **THEN** it holds four all-day events, on 3 November, 17 November, 1 December and 15 December 2026

#### Scenario: Ordinary run calendar file
- **WHEN** someone downloads the calendar file for a listing running from 2 to 30 November 2026 with no specific days
- **THEN** it holds one all-day event from 2 to 30 November 2026

### Requirement: Duplicating in the Dashboard copies specific days
Duplicating a listing from the Dashboard SHALL give the copy the same specific days as the original, along with its start and end dates.

#### Scenario: Duplicate the pub quiz
- **WHEN** someone duplicates the pub quiz in the Dashboard
- **THEN** the copy runs from 3 November to 15 December 2026 with the same four specific days

### Requirement: Duplicating in the Office copies specific days within the copy's run
Duplicating a listing in the Office SHALL give the copy those of the original's specific days that fall within the copy's saved run, and tighten the copy's run to them. When none fall within it, the copy SHALL have no specific days and keep its run as saved.

#### Scenario: Dates left as they were
- **WHEN** staff duplicate the pub quiz in the Office without changing its dates
- **THEN** the copy has the same four specific days and runs from 3 November to 15 December 2026

#### Scenario: Dates moved past every specific day
- **WHEN** staff duplicate the pub quiz in the Office and change its dates to 5 to 26 January 2027
- **THEN** the copy runs from 5 to 26 January 2027 with no specific days

### Requirement: The Office shows specific days read-only
A listing's page in the Office SHALL list its specific days, and SHALL link to that listing's edit form in the Dashboard for changing them. A listing without specific days SHALL show no such field. Specific days SHALL NOT be editable in the Office.

#### Scenario: Listing with specific days
- **WHEN** staff open the pub quiz in the Office
- **THEN** they see its specific days as 3 Nov, 17 Nov, 1 Dec and 15 Dec 2026, and a link that opens its edit form in the Dashboard

### Requirement: Office dates are read-only for listings with specific days
While a listing has specific days, its start and end dates SHALL be read-only in the Office's edit form, with a note that they follow the specific days set in the Dashboard. Listings without specific days SHALL keep editable dates in the Office.

#### Scenario: Editing the pub quiz in the Office
- **WHEN** staff open the pub quiz's edit form in the Office
- **THEN** its start and end dates cannot be changed there, and the form says to change them in the Dashboard

### Requirement: Additional date information stays free text
Additional date information SHALL remain optional free text, shown in brackets after a listing's dates wherever those are written out, whether or not the listing has specific days.

#### Scenario: Pub quiz dates line
- **WHEN** the pub quiz has the additional date information "every other Tuesday"
- **THEN** its dates are written "TUES 3 NOV - TUES 15 DEC (every other Tuesday)"
