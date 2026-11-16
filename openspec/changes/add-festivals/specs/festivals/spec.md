# Spec Delta

## Purpose

Defines festivals: a promoter's set of events across one or more venues, under a name and branding of its own. It covers who creates and runs a festival, which listings may belong to one, how a festival shows on its listings, its public page and gig widgets, and what search engines are told about it.

## ADDED Requirements

### Requirement: A festival belongs to one promoter
Every festival SHALL belong to exactly one promoter, chosen when the festival is created. The promoter SHALL NOT change once the festival exists, in Backstage or in the Office. An account runs a festival when it runs the festival's promoter and it has the Runs Festivals role.

#### Scenario: Choosing the promoter
- **WHEN** an account with Runs Festivals that runs Northgate Promotions creates Riverside Fest 2027
- **THEN** the festival belongs to Northgate Promotions

#### Scenario: Promoter fixed after creation
- **WHEN** Riverside Fest 2027 is edited in Backstage or in the Office
- **THEN** its promoter is shown but cannot be changed

### Requirement: The Office grants Runs Festivals
Staff SHALL be able to give any account the Runs Festivals role, and take it away, from the account's page in the Office. No account SHALL have it unless it is ticked there, and nothing outside the Office SHALL change it. Unlike the other roles, a staff account SHALL NOT have it unless it is ticked on that account.

#### Scenario: Granting the role
- **WHEN** staff tick Runs Festivals on the account that runs Northgate Promotions
- **THEN** that account's Backstage shows festivals from the next page it loads

#### Scenario: A new account
- **WHEN** an account is created, by sign-up or in the Office
- **THEN** it does not have Runs Festivals

#### Scenario: A staff account
- **WHEN** a staff account that runs a promoter has not had Runs Festivals ticked on it
- **THEN** its Backstage shows nothing about festivals

#### Scenario: A staff account that ticks it
- **WHEN** staff tick Runs Festivals on their own account, which runs Northgate Promotions
- **THEN** its Backstage shows Northgate Promotions' festivals

#### Scenario: Listing who runs festivals
- **WHEN** staff open the Festivals list under Users in the Office
- **THEN** it lists every account with Runs Festivals ticked

### Requirement: Backstage shows nothing about festivals to other accounts
An account without Runs Festivals SHALL see nothing about festivals in Backstage. There SHALL be no festival items or sections in its menu, no Festival choice or festival-mark setting on its listing form, and no festival offered when it makes a gig widget. The public site SHALL be unaffected.

#### Scenario: A promoter without the role
- **WHEN** the account that runs Northgate Promotions does not have Runs Festivals and opens Backstage
- **THEN** the menu has no Festivals entry and no festival sections, and the listing form has no Festival choice and no festival-mark setting

#### Scenario: Public site unchanged
- **WHEN** Riverside Fest 2027 exists and its promoter's account loses Runs Festivals
- **THEN** the festival's page, its listings' marks and "Part of" lines, and its widgets on other sites are unchanged

### Requirement: A festival has its own details and branding
A festival SHALL have a name. It MAY have a website, email, phone, about text, logo, banner and the four brand colours, as a promoter may. Each is the festival's own: none SHALL be taken from its promoter, whether set or not. A colour the festival leaves unset SHALL fall back to Good on Paper's palette.

#### Scenario: Colours of its own
- **WHEN** Northgate Promotions' brand colour is green and Riverside Fest 2027's is pink
- **THEN** everything painted in Riverside Fest 2027's brand colour is pink

#### Scenario: Unset colour
- **WHEN** Riverside Fest 2027 has no accent colour and Northgate Promotions has one
- **THEN** Riverside Fest 2027's accent is Good on Paper's, not Northgate Promotions'

#### Scenario: No logo
- **WHEN** Riverside Fest 2027 has no logo and Northgate Promotions has one
- **THEN** Riverside Fest 2027 has no logo anywhere it would be shown

### Requirement: Accounts with Runs Festivals create festivals in Backstage
An account with Runs Festivals that runs a promoter SHALL be able to create a festival for that promoter from Backstage, with the same fields as the promoter's own edit form. The festival SHALL be public as soon as it is saved, with no review. Any other account SHALL NOT be able to create a festival.

#### Scenario: Creating a festival
- **WHEN** an account with Runs Festivals that runs Northgate Promotions fills in the new festival form and saves it
- **THEN** the festival exists, belongs to Northgate Promotions and its page can be visited

#### Scenario: Several promoters
- **WHEN** an account with Runs Festivals that runs two promoters opens the new festival form
- **THEN** it chooses which of its two promoters the festival belongs to, and no other promoter is offered

#### Scenario: No promoter
- **WHEN** an account with Runs Festivals that runs only a venue asks for the new festival form
- **THEN** it is refused

#### Scenario: Without Runs Festivals
- **WHEN** an account that runs Northgate Promotions but does not have Runs Festivals asks for the new festival form
- **THEN** it is refused

### Requirement: A new festival can start from an earlier one
The new festival form SHALL offer an account with Runs Festivals the festivals it runs as a starting point. Choosing one SHALL fill the form with that festival's about, contact details, colours, logo and banner, choose its promoter, and leave the name blank. Its listings and widgets SHALL NOT be copied. A festival the account does not run SHALL NOT be offered or accepted.

#### Scenario: Next year's festival
- **WHEN** the account that runs Northgate Promotions, with Runs Festivals, starts a new festival from Riverside Fest 2027, names it Riverside Fest 2028 and saves it
- **THEN** Riverside Fest 2028 belongs to Northgate Promotions and has Riverside Fest 2027's about, contact details, colours, logo and banner, and no listings

#### Scenario: Someone else's festival
- **WHEN** an account asks to start from a festival it does not run
- **THEN** the form starts blank, and saving with that festival named is refused

### Requirement: A festival started from another has pictures of its own
Saving a festival started from another SHALL give it its own copies of the logo and banner it kept, and SHALL leave the earlier festival unchanged. Nothing in the Office SHALL copy a festival in a way that shares its picture files.

#### Scenario: Pictures of its own
- **WHEN** Riverside Fest 2028 was started from Riverside Fest 2027, and Riverside Fest 2027 is then deleted
- **THEN** Riverside Fest 2028 still has its logo and banner

#### Scenario: Changing a picture on the way
- **WHEN** the account starts from Riverside Fest 2027, uploads a new logo and ticks Remove on the banner
- **THEN** Riverside Fest 2028 has the new logo and no banner, and Riverside Fest 2027 keeps both of its own

#### Scenario: Not copied in the Office
- **WHEN** staff view a festival in the Office
- **THEN** they are not offered Nova's Replicate, which would share the festival's picture files

### Requirement: Accounts with Runs Festivals edit their festivals in Backstage
An account SHALL be able to edit a festival it runs, including uploading, replacing and removing the logo and banner. It SHALL NOT be able to edit any other festival.

#### Scenario: Editing its own festival
- **WHEN** the account that runs Northgate Promotions, with Runs Festivals, changes Riverside Fest 2027's banner
- **THEN** the festival page shows the new banner

#### Scenario: Someone else's festival
- **WHEN** an account that does not run Northgate Promotions asks to edit Riverside Fest 2027
- **THEN** it is refused

#### Scenario: Runs Festivals taken away
- **WHEN** staff untick Runs Festivals on the account that runs Northgate Promotions, and it then asks to edit Riverside Fest 2027
- **THEN** it is refused, and the festival is unchanged

### Requirement: Accounts with Runs Festivals delete their festivals in Backstage
An account SHALL be able to delete a festival it runs, after confirming. Its listings SHALL remain, no longer in any festival. The festival's gig widgets SHALL be deleted with it, and its page SHALL no longer be found.

#### Scenario: Deleting a festival with listings
- **WHEN** Riverside Fest 2027 has six listings and its promoter's account deletes it
- **THEN** the six listings are still listed under Northgate Promotions, none of them in a festival, and the festival's page is not found

#### Scenario: Its widgets go with it
- **WHEN** Riverside Fest 2027 has a gig widget and is deleted
- **THEN** the widget is deleted too

### Requirement: The Office manages every festival
Staff SHALL be able to create, edit and delete any festival in the Office, choosing its promoter when they create it, whether or not they have Runs Festivals. Deleting a festival in the Office SHALL have the same effect on its listings and widgets as deleting it in Backstage.

#### Scenario: Staff create a festival
- **WHEN** staff create a festival in the Office for a promoter with no account
- **THEN** the festival exists and belongs to that promoter

### Requirement: A listing may belong to one festival of its own promoter
A listing SHALL belong to no festival or to one. A listing in a festival SHALL have that festival's promoter as its own. In Backstage, the listing form SHALL offer an account with Runs Festivals a Festival choice listing only the festivals of the chosen promoter. Changing the promoter SHALL clear the festival.

#### Scenario: Choosing a festival
- **WHEN** a listing's promoter is Northgate Promotions
- **THEN** the Festival choice offers Northgate Promotions' festivals and no others

#### Scenario: Changing the promoter
- **WHEN** a listing in Riverside Fest 2027 has its promoter changed on the form
- **THEN** its festival is cleared

#### Scenario: Promoter without festivals
- **WHEN** the chosen promoter has no festivals, or no promoter is chosen
- **THEN** there is no festival to choose and the listing is saved in no festival

### Requirement: Saving checks a listing's festival
A listing SHALL NOT be saved, in Backstage or the Office, with a festival that does not belong to its promoter, or with a festival and no promoter. The form SHALL show an error by the Festival field.

#### Scenario: Mismatched festival
- **WHEN** a request saves a listing under one promoter with another promoter's festival
- **THEN** the listing is not saved and an error is shown by the Festival field

#### Scenario: In the Office
- **WHEN** staff save a listing in the Office whose festival belongs to a different promoter
- **THEN** the listing is not saved and an error is shown by the Festival field

### Requirement: Editing keeps a festival the editor does not run
When an account edits a listing in a festival it does not run, saving the listing SHALL keep its festival and its festival-mark setting unchanged. This covers a venue owner editing an event in their building, and an account without Runs Festivals editing its own promoter's event.

#### Scenario: Venue owner edits a festival event
- **WHEN** the owner of the Corn Exchange edits the time of a Riverside Fest 2027 listing at the Corn Exchange
- **THEN** the listing is saved still in Riverside Fest 2027

#### Scenario: Without Runs Festivals
- **WHEN** the account that runs Northgate Promotions does not have Runs Festivals and edits a Riverside Fest 2027 listing
- **THEN** the listing is saved still in Riverside Fest 2027, with its festival-mark setting unchanged

### Requirement: Duplicating keeps the festival
Duplicating a listing from Backstage SHALL give the copy the same festival and the same festival-mark setting as the original.

#### Scenario: Building a programme
- **WHEN** a Riverside Fest 2027 listing is duplicated
- **THEN** the copy is in Riverside Fest 2027 with the same festival-mark setting

### Requirement: Listings choose whether to show the festival mark
Every listing SHALL have a "Show the festival mark on the cover" setting, ticked by default. The listing form SHALL show it only while a festival is chosen. The listing preview SHALL show the mark as it will appear.

#### Scenario: New festival listing
- **WHEN** a new listing is put in Riverside Fest 2027 without touching the setting
- **THEN** it shows the festival mark

#### Scenario: Turned off
- **WHEN** the setting is unticked on a Riverside Fest 2027 listing
- **THEN** that listing's cover shows no festival mark

### Requirement: The festival mark sits in the cover's bottom right
When a listing in a festival shows the mark and the festival has a logo, the logo SHALL be drawn over the bottom right of the cover, on a square tile in the festival's background colour, a third of the cover's width. This SHALL apply on the listing's page and in lists of listings, and SHALL NOT change the stored image.

#### Scenario: Listing page
- **WHEN** a Riverside Fest 2027 listing that shows the mark is opened
- **THEN** the cover has Riverside Fest 2027's logo in its bottom right, a third of the cover's width, on a tile in the festival's background colour

#### Scenario: Lists of listings
- **WHEN** the same listing appears on the all listings page or a venue's page
- **THEN** its cover there has the mark too, at a third of that cover's width

#### Scenario: Sold out as well
- **WHEN** the listing is also sold out
- **THEN** the sold-out band and the festival mark both show, and the mark never hides the band

### Requirement: Some places never show the festival mark
The festival mark SHALL NOT be shown on the small bookmark images in Backstage, in the image used when a listing is shared, or in gig widgets.

#### Scenario: Shared link
- **WHEN** a Riverside Fest 2027 listing that shows the mark is shared on social media
- **THEN** the preview image is the plain cover

### Requirement: A listing in a festival links to it
Every listing in a festival SHALL say, under its promoter, that it is part of the festival, linking to the festival's page. This SHALL appear whether or not the listing shows the mark.

#### Scenario: Part of a festival
- **WHEN** a Riverside Fest 2027 listing is opened
- **THEN** it reads "Part of Riverside Fest 2027", linking to the festival's page

### Requirement: Each festival has a public page
Each festival SHALL have a public page at `/listings/festival/{id}/{slug}`. Its header SHALL show the festival's banner, logo, name, about text, website and contact details, and a link to its promoter's page. The festival's colours SHALL NOT be applied to the page.

#### Scenario: Header
- **WHEN** someone opens Riverside Fest 2027's page
- **THEN** the header shows the festival's banner and details, and links to Northgate Promotions' page

#### Scenario: Wrong slug
- **WHEN** the page is opened with the right id but an out-of-date slug
- **THEN** the festival's page is still shown

### Requirement: A festival page shows what is still to come
While any of a festival's listings has not yet had its last day, the festival page SHALL show those listings, ordered and headed by day as a promoter's page is, counting from today.

#### Scenario: Halfway through
- **WHEN** Riverside Fest 2027 runs from 4 to 6 June 2027 and its page is opened on 5 June 2027
- **THEN** the page shows the listings on 5 and 6 June, under those days' headings, and not those that finished on 4 June

### Requirement: A finished festival page shows the whole programme
Once a festival has listings and every one of them has had its last day, the festival page SHALL say the festival has finished and show all of its listings, ordered and headed by day from the festival's first day.

#### Scenario: After the festival
- **WHEN** Riverside Fest 2027 ran from 4 to 6 June 2027 and its page is opened on 1 July 2027
- **THEN** the page says the festival has finished and shows every listing from 4 to 6 June, headed by day

#### Scenario: Nothing listed yet
- **WHEN** a festival has no listings
- **THEN** its page shows its header and says nothing is listed yet, and does not say it has finished

### Requirement: A promoter's page lists its festivals
A promoter's page SHALL link to each of its festivals that has a listing still to come or on today. Festivals that have finished or have no listings SHALL NOT be listed there.

#### Scenario: Current and past festivals
- **WHEN** Northgate Promotions has Riverside Fest 2026, which has finished, and Riverside Fest 2027, which is still to come
- **THEN** its page links to Riverside Fest 2027 only

### Requirement: Backstage shows festivals in the menu
For an account with Runs Festivals, Backstage SHALL give each promoter it runs a Festivals entry, listing that promoter's festivals and offering a new one. It SHALL also give each festival the account runs its own section, with Event Listings narrowed to that festival and its Gig Widgets.

#### Scenario: A promoter with one festival
- **WHEN** the account that runs Northgate Promotions, with Runs Festivals, opens Backstage
- **THEN** the menu shows Festivals under Northgate Promotions, and a section for Riverside Fest 2027 with Event Listings and Gig Widgets

#### Scenario: Adding a listing from the festival
- **WHEN** the account adds a listing from Riverside Fest 2027's Event Listings
- **THEN** the new listing form starts with Northgate Promotions and Riverside Fest 2027 chosen

#### Scenario: No festivals
- **WHEN** an account with Runs Festivals runs a promoter with no festivals
- **THEN** the menu still shows Festivals under the promoter, and no festival sections

### Requirement: Festivals have gig widgets
An account that runs a festival SHALL be able to make gig widgets for it, as for a promoter. A festival's widget SHALL show the festival's listings in the festival's colours, unless the widget has colours of its own. Its credit and "more events" links SHALL go to the festival's page.

#### Scenario: Festival widget
- **WHEN** the account makes a widget for Riverside Fest 2027
- **THEN** the widget shows Riverside Fest 2027's listings, still to come, in the festival's colours, each saying where it is

#### Scenario: Branding changed
- **WHEN** Riverside Fest 2027's colours are changed
- **THEN** its widgets that follow the festival's colours show the new colours

### Requirement: A finished festival's widget shows the whole programme
Once a festival has listings and every one of them has had its last day, its widgets SHALL show its listings from its first day, rather than showing nothing.

#### Scenario: Widget after the festival
- **WHEN** Riverside Fest 2027 has finished and its widget is loaded on the festival's own website
- **THEN** the widget shows the festival's listings from its first day

### Requirement: Search engines are told about festivals
The festival page SHALL describe the festival to search engines as a festival, with its name, dates, organiser and its listings as the events within it. A listing in a festival SHALL name the festival as the event it is part of. Every festival with a listing SHALL appear in the sitemap.

#### Scenario: Festival page data
- **WHEN** a search engine reads Riverside Fest 2027's page
- **THEN** it finds a festival from 4 to 6 June 2027, organised by Northgate Promotions, with each of its listings as an event within it

#### Scenario: Listing page data
- **WHEN** a search engine reads a Riverside Fest 2027 listing's page
- **THEN** the event it finds names Riverside Fest 2027 as the event it is part of
