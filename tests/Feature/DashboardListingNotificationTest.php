<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Notifications\NovaNotification;

/**
 * The office hears in the Office's notifications of every listing added
 * from a dashboard. Uses listingPayload() from DashboardListingTest.php,
 * so run the two together.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake('do');

    $this->office = collect(User::OFFICE_EMAILS)
        ->map(fn (string $email) => User::factory()->create(['email' => $email]));
});

/**
 * What the office was told, for each member of staff told.
 *
 * @return array<int, array<string, mixed>>
 */
function officeToldOf(): array
{
    return test()->office
        ->mapWithKeys(fn (User $staff) => [$staff->id => Notification::sent($staff, NovaNotification::class)
            ->map->toNova()
            ->first()])
        ->filter()
        ->all();
}

test('the office is told of a listing added from a dashboard', function () {
    $promoter = User::factory()->has(Promoter::factory())->create(['name' => 'Jo Leahy']);

    $this->actingAs($promoter)
        ->post(route('dashboard.listings.store'), listingPayload(['title' => 'Quiz Night']));

    $listing = Listing::query()->latest('id')->first();
    $told = officeToldOf();

    expect($told)->toHaveCount(3);

    foreach ($told as $notification) {
        expect($notification['message'])->toBe('New event created by Jo Leahy: Quiz Night')
            ->and((string) $notification['actionText'])->toBe('View listing')
            ->and((string) $notification['actionUrl'])->toContain("/resources/listings/{$listing->id}")
            ->and($notification['type'])->toBe('info');
    }
});

/**
 * Listings are no longer held back for the office to approve. An account it
 * has not linked to a promoter or venue is turned away, so there is nothing
 * to tell anybody about.
 */
test('the office hears nothing from an account it has not linked', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('dashboard.listings.store'), listingPayload(['title' => 'School Fete']))
        ->assertForbidden();

    expect(Listing::query()->where('title', 'School Fete')->exists())->toBeFalse();

    Notification::assertNothingSent();
});

test('the office is told of a duplicated listing', function () {
    $owner = User::factory()->has(Promoter::factory())->create(['name' => 'Jo Leahy']);
    $listing = Listing::factory()->for($owner)->create(['title' => 'Quiz Night']);

    $this->actingAs($owner)->post(route('dashboard.listings.duplicate', $listing));

    expect(collect(officeToldOf())->pluck('message')->unique()->all())
        ->toBe(['New event created by Jo Leahy: Copy of Quiz Night']);
});

test('staff adding a listing are not told of their own', function () {
    $robin = $this->office->first();

    $this->actingAs($robin)->post(route('dashboard.listings.store'), listingPayload());

    expect(officeToldOf())->toHaveCount(2)->not->toHaveKey($robin->id);
});

test('nothing is sent when a listing fails to save', function () {
    $this->actingAs(User::factory()->has(Promoter::factory())->create())
        ->post(route('dashboard.listings.store'), listingPayload(['title' => '']));

    Notification::assertNothingSent();
});
