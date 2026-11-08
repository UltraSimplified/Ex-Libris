<?php

use App\Models\Listing;
use App\Models\ListingDay;

test('a listing hands out its specific days earliest first', function () {
    $listing = Listing::factory()->onDays('2026-12-15', '2026-11-03', '2026-12-01', '2026-11-17')->create();

    $days = $listing->fresh()->days->map(fn (ListingDay $day): string => $day->day->toDateString());

    expect($days->all())->toBe(['2026-11-03', '2026-11-17', '2026-12-01', '2026-12-15']);
});

test('deleting a listing deletes its specific days', function () {
    $listing = Listing::factory()->onDays('2026-11-03', '2026-11-17')->create();
    $days = $listing->days;

    $listing->delete();

    $days->each(fn (ListingDay $day) => $this->assertModelMissing($day));
});
