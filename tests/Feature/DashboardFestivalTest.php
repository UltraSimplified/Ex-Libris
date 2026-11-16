<?php

use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * An account the office has given Runs Festivals makes and keeps its
 * promoters' festivals from Backstage. Anybody else is refused, and a
 * festival never leaves the promoter it was made for.
 */
function festivalRunner(): User
{
    return User::factory()->runsFestivals()->create();
}

test('a festival runner creates a festival for its promoter', function () {
    Storage::fake('do');

    $account = festivalRunner();
    $promoter = Promoter::factory()->for($account)->create();

    $this->actingAs($account)
        ->post(route('dashboard.festivals.store'), [
            'promoter_id' => $promoter->id,
            'name' => 'Riverside Fest 2027',
            'about' => 'Three days by the water.',
            'brand_colour' => '#ff69b4',
            'brand_background_colour' => '',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.festivals.index'));

    $festival = Festival::sole();

    expect($festival->promoter_id)->toBe($promoter->id)
        ->and($festival->name)->toBe('Riverside Fest 2027')
        ->and($festival->brand_colour)->toBe('#ff69b4')
        ->and($festival->brand_background_colour)->toBeNull();

    Storage::disk('do')->assertExists($festival->logo);
});

test('the new festival form offers only the account\'s own promoters', function () {
    $account = festivalRunner();
    $first = Promoter::factory()->for($account)->create(['name' => 'Avon Sounds']);
    $second = Promoter::factory()->for($account)->create(['name' => 'Northgate Promotions']);
    Promoter::factory()->create(['name' => 'Somebody Else']);

    $this->actingAs($account)
        ->get(route('dashboard.festivals.create', ['promoter' => $second->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/festivals/Create')
            ->where('promoters', [
                ['id' => $first->id, 'name' => 'Avon Sounds'],
                ['id' => $second->id, 'name' => 'Northgate Promotions'],
            ])
            ->where('promoterId', $second->id));
});

test('a festival cannot be made for somebody else\'s promoter', function () {
    $account = festivalRunner();
    Promoter::factory()->for($account)->create();
    $elsewhere = Promoter::factory()->create();

    $this->actingAs($account)
        ->post(route('dashboard.festivals.store'), [
            'promoter_id' => $elsewhere->id,
            'name' => 'Not Mine Fest',
        ])
        ->assertSessionHasErrors('promoter_id');

    expect(Festival::count())->toBe(0);
});

test('a festival runner with no promoter is refused the new festival form', function () {
    $account = festivalRunner();

    $this->actingAs($account)->get(route('dashboard.festivals.create'))->assertForbidden();
    $this->actingAs($account)->get(route('dashboard.festivals.index'))->assertForbidden();
});

test('a promoter without Runs Festivals is refused every festival page', function () {
    $account = User::factory()->create();
    $promoter = Promoter::factory()->for($account)->create();
    $festival = Festival::factory()->for($promoter)->create();

    $this->actingAs($account)->get(route('dashboard.festivals.index'))->assertForbidden();
    $this->actingAs($account)->get(route('dashboard.festivals.create'))->assertForbidden();
    $this->actingAs($account)
        ->post(route('dashboard.festivals.store'), ['promoter_id' => $promoter->id, 'name' => 'Sneaky Fest'])
        ->assertForbidden();
    $this->actingAs($account)->get(route('dashboard.festivals.edit', $festival))->assertForbidden();

    expect(Festival::count())->toBe(1);
});

test('a festival runner edits its own festival, pictures included', function () {
    Storage::fake('do');
    Storage::disk('do')->put('old-banner.webp', 'old');

    $account = festivalRunner();
    $festival = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->create(['name' => 'Riverside Fest 2027', 'banner' => 'old-banner.webp']);

    $this->actingAs($account)
        ->get(route('dashboard.festivals.edit', $festival))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/festivals/Edit')
            ->where('festival.id', $festival->id)
            ->where('festival.promoter.id', $festival->promoter_id));

    $this->actingAs($account)
        ->put(route('dashboard.festivals.update', $festival), [
            'name' => 'Riverside Fest 2027',
            'banner' => UploadedFile::fake()->image('banner.jpg', 2000, 600),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.festivals.index'));

    $banner = $festival->fresh()->banner;

    expect($banner)->not->toBe('old-banner.webp');
    Storage::disk('do')->assertExists($banner);
    Storage::disk('do')->assertMissing('old-banner.webp');
});

test('a festival runner cannot touch somebody else\'s festival', function () {
    $account = festivalRunner();
    Promoter::factory()->for($account)->create();
    $theirs = Festival::factory()->create(['name' => 'Theirs Fest']);

    $this->actingAs($account)->get(route('dashboard.festivals.edit', $theirs))->assertForbidden();
    $this->actingAs($account)
        ->put(route('dashboard.festivals.update', $theirs), ['name' => 'Mine Now'])
        ->assertForbidden();
    $this->actingAs($account)->delete(route('dashboard.festivals.destroy', $theirs))->assertForbidden();

    expect($theirs->fresh()->name)->toBe('Theirs Fest');
});

test('an account whose Runs Festivals is taken away can no longer edit its festival', function () {
    $account = festivalRunner();
    $festival = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->create(['name' => 'Riverside Fest 2027']);

    $account->forceFill(['runs_festivals' => false])->save();

    $this->actingAs($account)
        ->put(route('dashboard.festivals.update', $festival), ['name' => 'Renamed'])
        ->assertForbidden();

    expect($festival->fresh()->name)->toBe('Riverside Fest 2027');
});

test('a festival stays with its promoter whatever an edit sends', function () {
    $account = festivalRunner();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();
    $other = Promoter::factory()->for($account)->create();

    $this->actingAs($account)
        ->put(route('dashboard.festivals.update', $festival), [
            'name' => $festival->name,
            'promoter_id' => $other->id,
        ])
        ->assertSessionHasNoErrors();

    expect($festival->fresh()->promoter_id)->not->toBe($other->id);
});

test('deleting a festival keeps its listings and takes its widgets', function () {
    $account = festivalRunner();
    $festival = Festival::factory()->for(Promoter::factory()->for($account))->create();
    $listings = Listing::factory()->count(6)->create([
        'promoter_id' => $festival->promoter_id,
        'festival_id' => $festival->id,
    ]);
    $widget = Widget::factory()->create(['owner_type' => Festival::class, 'owner_id' => $festival->id]);

    $this->actingAs($account)
        ->delete(route('dashboard.festivals.destroy', $festival))
        ->assertRedirect(route('dashboard.festivals.index'));

    expect(Festival::find($festival->id))->toBeNull()
        ->and(Widget::find($widget->id))->toBeNull()
        ->and(Listing::whereKey($listings->modelKeys())->whereNull('festival_id')->where('promoter_id', $festival->promoter_id)->count())->toBe(6);
});

test('the festival list is the account\'s own, and can be narrowed to one promoter', function () {
    $account = festivalRunner();
    $avon = Promoter::factory()->for($account)->create();
    $northgate = Promoter::factory()->for($account)->create();
    $avonFest = Festival::factory()->for($avon)->create(['name' => 'Avon Fest']);
    $northgateFest = Festival::factory()->for($northgate)->create(['name' => 'Northgate Fest']);
    Festival::factory()->create(['name' => 'Elsewhere Fest']);

    $this->actingAs($account)
        ->get(route('dashboard.festivals.index'))
        ->assertInertia(fn ($page) => $page
            ->has('festivals', 2)
            ->where('festivals.0.id', $avonFest->id)
            ->where('festivals.1.id', $northgateFest->id)
            ->where('promoter', null));

    $this->actingAs($account)
        ->get(route('dashboard.festivals.index', ['promoter' => $northgate->id]))
        ->assertInertia(fn ($page) => $page
            ->has('festivals', 1)
            ->where('festivals.0.id', $northgateFest->id)
            ->where('promoter.id', $northgate->id));
});

test('the new festival form starts from one of the account\'s own festivals', function () {
    $account = festivalRunner();
    Promoter::factory()->for($account)->create(['name' => 'Avon Sounds']);
    $northgate = Promoter::factory()->for($account)->create(['name' => 'Northgate Promotions']);
    $lastYear = Festival::factory()->for($northgate)->create([
        'name' => 'Riverside Fest 2027',
        'about' => 'Three days by the water.',
        'logo' => 'logo-2027.webp',
    ]);
    $theirs = Festival::factory()->create(['name' => 'Theirs Fest']);

    $this->actingAs($account)
        ->get(route('dashboard.festivals.create', ['from' => $lastYear->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('earlier', [['id' => $lastYear->id, 'name' => 'Riverside Fest 2027']])
            ->where('startingFrom.id', $lastYear->id)
            ->where('startingFrom.about', 'Three days by the water.')
            ->where('startingFrom.logo', 'logo-2027.webp')
            ->where('promoterId', $northgate->id));

    $this->actingAs($account)
        ->get(route('dashboard.festivals.create', ['from' => $theirs->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('earlier', 1)
            ->where('startingFrom', null));
});

test('next year\'s festival saves with copies of last year\'s pictures', function () {
    Storage::fake('do');
    Storage::disk('do')->put('logo-2027.webp', 'logo');
    Storage::disk('do')->put('banner-2027.webp', 'banner');

    $account = festivalRunner();
    $lastYear = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->has(Listing::factory())
        ->create(['name' => 'Riverside Fest 2027', 'logo' => 'logo-2027.webp', 'banner' => 'banner-2027.webp']);

    $this->actingAs($account)
        ->post(route('dashboard.festivals.store'), [
            'promoter_id' => $lastYear->promoter_id,
            'from' => $lastYear->id,
            'name' => 'Riverside Fest 2028',
            'about' => 'Three days by the water.',
            'brand_colour' => '#ff69b4',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.festivals.index'));

    $nextYear = Festival::query()->where('name', 'Riverside Fest 2028')->sole();

    expect($nextYear->promoter_id)->toBe($lastYear->promoter_id)
        ->and($nextYear->about)->toBe('Three days by the water.')
        ->and($nextYear->brand_colour)->toBe('#ff69b4')
        ->and($nextYear->listings()->count())->toBe(0)
        ->and($nextYear->logo)->not->toBe('logo-2027.webp')
        ->and($nextYear->banner)->not->toBe('banner-2027.webp')
        ->and(Storage::disk('do')->get($nextYear->logo))->toBe('logo')
        ->and(Storage::disk('do')->get($nextYear->banner))->toBe('banner');

    $lastYear->delete();

    Storage::disk('do')->assertMissing(['logo-2027.webp', 'banner-2027.webp']);
    Storage::disk('do')->assertExists([$nextYear->logo, $nextYear->banner]);
});

test('a picture replaced or removed on the way is not copied', function () {
    Storage::fake('do');
    Storage::disk('do')->put('logo-2027.webp', 'logo');
    Storage::disk('do')->put('banner-2027.webp', 'banner');

    $account = festivalRunner();
    $lastYear = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->create(['name' => 'Riverside Fest 2027', 'logo' => 'logo-2027.webp', 'banner' => 'banner-2027.webp']);

    $this->actingAs($account)
        ->post(route('dashboard.festivals.store'), [
            'promoter_id' => $lastYear->promoter_id,
            'from' => $lastYear->id,
            'name' => 'Riverside Fest 2028',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'remove_banner' => 'on',
        ])
        ->assertSessionHasNoErrors();

    $nextYear = Festival::query()->where('name', 'Riverside Fest 2028')->sole();

    expect($nextYear->banner)->toBeNull()
        ->and($nextYear->logo)->not->toBe('logo-2027.webp')
        ->and(Storage::disk('do')->get($nextYear->logo))->not->toBe('logo')
        ->and($lastYear->fresh()->only(['logo', 'banner']))->toBe(['logo' => 'logo-2027.webp', 'banner' => 'banner-2027.webp'])
        ->and(Storage::disk('do')->allFiles())->toEqualCanonicalizing(['logo-2027.webp', 'banner-2027.webp', $nextYear->logo]);
});

test('a festival cannot be started from somebody else\'s', function () {
    $account = festivalRunner();
    $promoter = Promoter::factory()->for($account)->create();
    $theirs = Festival::factory()->create(['name' => 'Theirs Fest']);

    $this->actingAs($account)
        ->post(route('dashboard.festivals.store'), [
            'promoter_id' => $promoter->id,
            'from' => $theirs->id,
            'name' => 'Riverside Fest 2028',
        ])
        ->assertSessionHasErrors(['from' => 'You can only start from a festival you run.']);

    expect(Festival::count())->toBe(1);
});
