# Spec Delta

## ADDED Requirements

### Requirement: The Office form offers specific days for multi-day runs
The Office's listing form, when creating, editing or replicating, SHALL offer the same "Set specific days" calendar as the Dashboard, placed just after the dates. It SHALL be offered on the same terms, with the same controls, leave out days a shortened run no longer reaches in the same way, and save by the same rules as the Dashboard form.

#### Scenario: Editing the pub quiz in the Office
- **WHEN** staff open the edit form of the pub quiz, whose specific days are 3 November, 17 November, 1 December and 15 December 2026
- **THEN** "Set specific days" is ticked and the calendar shows those four days ticked, with the start and end dates still editable

#### Scenario: One-day event in the Office
- **WHEN** the Office form has a start date and no end date
- **THEN** the "Set specific days" checkbox is not shown

#### Scenario: Setting days in the Office
- **WHEN** staff edit a listing running from 1 to 20 June 2026, tick "Set specific days", press Sat and Sun, and save
- **THEN** the listing is saved running from 6 to 20 June 2026 with specific days 6, 7, 13, 14 and 20 June

#### Scenario: Unticking in the Office
- **WHEN** staff edit the pub quiz, untick "Set specific days" and save
- **THEN** the listing has no specific days and is on every day of its run

### Requirement: The Office calendar keeps to the Office's colours
The Office's calendar SHALL take its colours from the Office's own palette: ticked days and weekday chips that are on SHALL use the Office's primary colour, and nothing in it SHALL introduce a colour from outside that palette, so that in Goblin mode it is drawn in the same single shade of green as the rest of the Office.

#### Scenario: Goblin mode
- **WHEN** staff view the pub quiz's Office edit form in Goblin mode
- **THEN** its ticked days and any weekday chip that is on are green, and nothing in the calendar is drawn in another hue

### Requirement: The Office lists specific days on a listing's page
A listing's page in the Office SHALL list its specific days, giving the year once when they all fall in one year and on each day when they do not. A listing without specific days SHALL show no such field.

#### Scenario: Listing with specific days
- **WHEN** staff open the pub quiz's page in the Office
- **THEN** they see its specific days as 3 Nov, 17 Nov, 1 Dec, 15 Dec 2026

#### Scenario: Listing without specific days
- **WHEN** staff open the page of a listing that is on every day of its run
- **THEN** no specific days field is shown

## MODIFIED Requirements

### Requirement: The calendar has select-all, clear-all and weekday shortcuts
The calendar, in the Dashboard and in the Office, SHALL offer Select all, which ticks every day of the run, and Clear all, which unticks them all. Each weekday chip above the calendar, labelled Mon to Sun, SHALL tick every day of that weekday in the run, or untick them when they are all already ticked, and SHALL show as on while every day of that weekday in the run is ticked. The calendar SHALL show how many days are chosen out of the days in the run.

#### Scenario: Gallery closed on Mondays
- **WHEN** the run is 12 September to 8 November 2026 and the person presses Select all, then Mon
- **THEN** every day except the eight Mondays is ticked and the calendar says 50 of 58 days are chosen

#### Scenario: Weekend festival
- **WHEN** the run is 1 to 20 June 2026, nothing is ticked, and the person presses Sat and Sun
- **THEN** 6, 7, 13, 14 and 20 June are ticked, and the Sat and Sun chips show as on

#### Scenario: Weekday button when all of that weekday are ticked
- **WHEN** every Tuesday in the run is ticked and the person presses Tue
- **THEN** every Tuesday in the run is unticked, the Tue chip shows as off, and the other days are unchanged

### Requirement: Specific days are validated on save
When "Set specific days" is ticked, in the Dashboard or the Office, the submitted days SHALL all fall between the submitted start and end dates inclusive, and there SHALL be at least one. Otherwise the listing SHALL NOT be saved, and the form SHALL show an error by the calendar. When the checkbox is unticked, the listing SHALL be saved with no specific days.

#### Scenario: No day ticked
- **WHEN** someone saves with "Set specific days" ticked and no day ticked
- **THEN** the listing is not saved and the form asks them to choose at least one day

#### Scenario: Day outside the run
- **WHEN** a submission for a 1 to 20 June 2026 run includes 21 June 2026 as a specific day
- **THEN** the listing is not saved and the form says each day must fall between the start and end dates

#### Scenario: Checkbox unticked
- **WHEN** someone edits a listing that has specific days, unticks "Set specific days" and saves
- **THEN** the listing has no specific days and is on every day of its run

#### Scenario: No day ticked in the Office
- **WHEN** staff save an Office form with "Set specific days" ticked and no day ticked
- **THEN** the listing is not saved and the Office form asks them to choose at least one day, with the same message as the Dashboard

### Requirement: Duplicating in the Office copies specific days within the copy's run
Replicating a listing in the Office SHALL open the copy's form with the original's specific days ticked, so that saving it gives the copy those days that fall within its run, with its run pulled in to them. Days a changed run no longer reaches SHALL be left out as on any listing form, and the copy SHALL be saved by the same rules as any other listing.

#### Scenario: Dates left as they were
- **WHEN** staff replicate the pub quiz in the Office and save without changing its dates or days
- **THEN** the copy has the same four specific days and runs from 3 November to 15 December 2026

#### Scenario: Dates moved past every specific day
- **WHEN** staff replicate the pub quiz, change its dates to 5 to 26 January 2027 and save with "Set specific days" still ticked
- **THEN** the copy is not saved and the form asks them to choose at least one day, or untick "Set specific days"

#### Scenario: Dates moved and the days dropped
- **WHEN** staff replicate the pub quiz, change its dates to 5 to 26 January 2027, untick "Set specific days" and save
- **THEN** the copy runs from 5 to 26 January 2027 with no specific days

## REMOVED Requirements

### Requirement: The Office shows specific days read-only
**Reason**: The Office now edits specific days in the listing form, so they are no longer read-only there and the link to the Dashboard is no longer needed.
**Migration**: Staff set and change days in the Office listing form. A listing's page in the Office still lists its days, as "The Office lists specific days on a listing's page" describes.

### Requirement: Office dates are read-only for listings with specific days
**Reason**: Dates and days are now edited together in the Office, and saving pulls the run in to the chosen days there just as it does in the Dashboard, so the dates cannot drift away from the days.
**Migration**: None. Staff edit the dates as on any listing.
