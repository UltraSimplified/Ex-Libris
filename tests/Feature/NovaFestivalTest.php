<?php

use App\Models\Category;
use App\Models\Festival;
use App\Models\Listing;
use App\Models\Promoter;
use App\Models\User;
use App\Rules\FestivalOfPromoter;

/**
 * The Office keeps every festival, whether or not the member of staff runs
 * festivals in their own Backstage, and holds a listing's festival to its
 * promoter as Backstage does.
 */
function festivalOfficeStaff(): User
{
    return User::factory()->create(['email' => 'robin@ultrasimplified.com']);
}

test('staff create a festival in the Office for a promoter with no account', function () {
    $promoter = Promoter::factory()->create(['user_id' => null]);

    $this->actingAs(festivalOfficeStaff())
        ->postJson('/nova-api/festivals', [
            'name' => 'Riverside Fest 2027',
            'promoter' => $promoter->id,
        ])
        ->assertCreated();

    expect(Festival::sole())
        ->name->toBe('Riverside Fest 2027')
        ->promoter_id->toBe($promoter->id);
});

test('a festival\'s promoter cannot be changed in the Office', function () {
    $festival = Festival::factory()->create();
    $original = $festival->promoter_id;

    $this->actingAs(festivalOfficeStaff())
        ->putJson('/nova-api/festivals/'.$festival->id.'?editing=true&editMode=update', [
            'name' => $festival->name,
            'promoter' => Promoter::factory()->create()->id,
        ])
        ->assertOk();

    expect($festival->fresh()->promoter_id)->toBe($original);
});

test('staff save a listing in one of its promoter\'s festivals from the Office', function () {
    $festival = Festival::factory()->create();

    $this->actingAs(festivalOfficeStaff())
        ->postJson('/nova-api/listings', [
            'title' => 'Riverside Opening Night',
            'category' => Category::factory()->create()->id,
            'body' => 'The first night.',
            'start_date' => '2027-06-04',
            'promoter' => $festival->promoter_id,
            'festival' => $festival->id,
            'show_festival_mark' => false,
        ])
        ->assertSuccessful();

    expect(Listing::sole())
        ->festival_id->toBe($festival->id)
        ->show_festival_mark->toBeFalse();
});

test('the Office refuses a listing in another promoter\'s festival', function () {
    $festival = Festival::factory()->create();

    $this->actingAs(festivalOfficeStaff())
        ->postJson('/nova-api/listings', [
            'title' => 'Riverside Opening Night',
            'category' => Category::factory()->create()->id,
            'body' => 'The first night.',
            'start_date' => '2027-06-04',
            'promoter' => Promoter::factory()->create()->id,
            'festival' => $festival->id,
        ])
        ->assertJsonValidationErrors(['festival' => FestivalOfPromoter::ANOTHER_PROMOTERS]);

    expect(Listing::count())->toBe(0);
});

test('the Office offers a listing only its promoter\'s festivals', function () {
    $festival = Festival::factory()->create(['name' => 'Riverside Fest 2027']);
    Festival::factory()->create(['name' => 'Elsewhere Fest']);

    $offered = $this->actingAs(festivalOfficeStaff())
        ->getJson('/nova-api/listings/associatable/festival?'.http_build_query([
            'promoter' => $festival->promoter_id,
            'field' => 'festival',
            'component' => 'belongs-to-field.festival',
            'editing' => 'true',
            'editMode' => 'create',
        ]))
        ->assertOk()
        ->json('resources');

    expect(collect($offered)->pluck('display')->all())->toBe(['Riverside Fest 2027']);
});

test('staff are not offered Nova\'s Replicate, which would share a festival\'s pictures', function () {
    $festival = Festival::factory()->create();

    $this->actingAs(festivalOfficeStaff())
        ->getJson('/nova-api/festivals/'.$festival->id)
        ->assertOk()
        ->assertJsonPath('resource.authorizedToReplicate', false)
        ->assertJsonPath('resource.authorizedToUpdate', true);
});
