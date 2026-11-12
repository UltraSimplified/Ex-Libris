# Spec Delta

## ADDED Requirements

### Requirement: Listings are ordered by the next day they are on
The listings pages SHALL place each listing on the next day it is on, counted from the page's first day, and list them day by day, earliest first:
- a listing that has not started by the first day is placed on its start date
- one already under way is placed on the first day
- one with specific days is placed on its first chosen day on or after the first day

#### Scenario: A show that opens in two days
- **WHEN** the all listings page is opened on Thursday 12 November 2026, with a show running from 14 to 29 November and a one-night gig on 20 November
- **THEN** the show is placed on 14 November and comes before the gig

#### Scenario: An exhibition under way
- **WHEN** the all listings page is opened on 12 November 2026, with an exhibition running from 5 September to 30 November and a one-night gig on 13 November
- **THEN** the exhibition is placed on 12 November and comes before the gig

#### Scenario: Between two pub quizzes
- **WHEN** the all listings page is opened on 10 November 2026, with the pub quiz (specific days 3 November, 17 November, 1 December and 15 December), a gig on 16 November and a gig on 18 November
- **THEN** the pub quiz is placed on 17 November, after the gig on the 16th and before the gig on the 18th

### Requirement: Each listings page counts from its first day
A listings page's first day SHALL be:
- today, for the all listings page, the pages for a venue, a promoter and a category, bookmarked listings, Today and This week
- tomorrow, for Tomorrow
- the Monday of next week, for Next week
- the range's first day, for a chosen date range

#### Scenario: Chosen date range
- **WHEN** the listings for 18 to 30 November 2026 are opened on 12 November, with an exhibition running from 1 to 30 November and a gig on 19 November
- **THEN** the exhibition is placed on 18 November and comes before the gig

### Requirement: Listings on the same day are ordered by how soon their run ends
Listings placed on the same day SHALL be ordered by the last day of their run, the soonest first, and then by title. A run's last day is its end date, or for a one-day listing its start date. Specific days SHALL NOT count, except that a listing with specific days ends on its last chosen day.

#### Scenario: Today's group
- **WHEN** the all listings page is opened on 12 November 2026, with a one-night gig that evening, an exhibition closing on 13 November and an exhibition closing on 22 November
- **THEN** they are listed in that order: the gig, the exhibition closing on the 13th, then the exhibition closing on the 22nd

#### Scenario: The pub quiz on a quiz night
- **WHEN** the all listings page is opened on 12 November 2026, and the pub quiz, a one-night gig on 17 November and a play on 17 and 18 November are all placed on 17 November
- **THEN** the gig comes first, then the play, then the pub quiz, whose run ends on 15 December

#### Scenario: Same last day
- **WHEN** two one-night gigs, "Shindig" and "Loom Weaving", are both on 14 November 2026
- **THEN** "Loom Weaving" comes before "Shindig"

### Requirement: The Dashboard bookmarks panel follows the same order
The Dashboard's panel of bookmarked events SHALL order them as the bookmarked listings page does, counting from today, and SHALL show no headings.

#### Scenario: Three saved events
- **WHEN** someone opens the Dashboard on 12 November 2026, having bookmarked a one-night gig on 20 November, a play on 14 and 15 November, and an exhibition running until 30 November
- **THEN** the panel lists the exhibition, then the play, then the gig, with no headings

### Requirement: The listings pages head each day
The listings pages SHALL show a heading above the first listing placed on each day:
- TODAY or TOMORROW where it applies
- otherwise the weekday in full, then the day, and the month and year as listing dates write them (SATURDAY 14 NOV), the year only when it is not this year

Days with no listing shown SHALL have no heading.

#### Scenario: The next three days
- **WHEN** the all listings page is opened on Thursday 12 November 2026, with listings placed on 12, 13 and 14 November and none on 15 November
- **THEN** the headings read TODAY, TOMORROW and SATURDAY 14 NOV, each above its own listings, and there is no heading for 15 November

#### Scenario: A day in the new year
- **WHEN** the all listings page is opened on 20 December 2026, with a listing placed on 9 January 2027
- **THEN** its heading reads SATURDAY 9 JAN 27

### Requirement: Listings further out are headed by month
Listings placed after the last day of the month following the first day's month SHALL be headed by month, not by day. The heading SHALL give the month in full, with the year when it is not this year. Listings under a month heading keep their order.

#### Scenario: January seen from November
- **WHEN** the all listings page is opened on 12 November 2026, with listings placed on 31 December 2026, 6 January 2027 and 20 January 2027
- **THEN** the listing on 31 December is headed THURSDAY 31 DEC, and the two in January share one heading, JANUARY 2027

#### Scenario: A month of this year
- **WHEN** the all listings page is opened on 10 March 2027, with a listing placed on 3 May 2027
- **THEN** its heading reads MAY

#### Scenario: A chosen range far ahead
- **WHEN** the listings for 1 to 7 March 2027 are opened on 12 November 2026
- **THEN** they are headed by day, because the page's first day is 1 March

### Requirement: Pages about a single day have no headings
The Today and Tomorrow pages, and the page for a chosen date range of one day, SHALL show no day or month headings.

#### Scenario: Today page
- **WHEN** someone opens the Today page
- **THEN** no heading appears among the listings

#### Scenario: One-day range
- **WHEN** someone opens the listings for 20 to 20 November 2026
- **THEN** no heading appears among the listings

### Requirement: Headings follow the listings shown
A heading SHALL be shown only while at least one listing under it is shown. This applies when the listings are narrowed by search or by genre, and when a heart is emptied on the bookmarked listings page.

#### Scenario: Searching
- **WHEN** someone searches the all listings page and only a gig on Saturday 14 November is left
- **THEN** the only heading shown is SATURDAY 14 NOV

#### Scenario: Emptying the last heart of a day
- **WHEN** someone's bookmarked listings page has one event on Friday 13 November and they empty its heart
- **THEN** the event and the TOMORROW heading above it both go

### Requirement: Adverts never split a heading from its listings
An advert placed among the listings SHALL NOT come between a heading and the first listing under it. Adverts SHALL be placed by counting listings alone, as before, whatever headings lie between them.

#### Scenario: Advert at the end of a day
- **WHEN** an advert is due after every five listings and the fifth listing is the last one placed on Friday 13 November
- **THEN** the advert comes after that listing and before the SATURDAY 14 NOV heading
