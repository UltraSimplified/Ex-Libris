# Spec Delta

## MODIFIED Requirements

### Requirement: Run-wide listings pages show a listing until its last day
The listings pages that are not about particular days (all listings, a venue's, a promoter's, a festival's, a category's, and bookmarked listings) SHALL show a listing from now until its last day has passed, whether or not it has specific days. A festival's page is the one exception: once every listing in the festival has had its last day, it SHALL show all of them again.

#### Scenario: Between specific days
- **WHEN** the pub quiz runs from 3 November to 15 December 2026 and the all listings page is opened on 10 November 2026
- **THEN** the pub quiz is shown

#### Scenario: After the last specific day
- **WHEN** the all listings page is opened on 16 December 2026
- **THEN** the pub quiz is not shown

#### Scenario: A festival that has finished
- **WHEN** every listing in a festival had its last day by 6 June 2027 and the festival's page is opened on 1 July 2027
- **THEN** every listing in the festival is shown
