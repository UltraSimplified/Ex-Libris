<?php

use App\Models\Festival;
use App\Models\Promoter;
use App\Models\User;

/*
 * An account that runs festivals makes one, finds it in its list and
 * changes it, all from Backstage on a phone. Deleting is asked about first;
 * what deleting does is DashboardFestivalTest's.
 */
test('a festival is made and changed from Backstage', function () {
    $account = User::factory()->runsFestivals()->create();
    Promoter::factory()->for($account)->create(['name' => 'Northgate Promotions']);
    $this->actingAs($account);

    $page = visit('/dashboard/festivals/create')
        ->resize(390, 900)
        ->assertSee('New festival')
        ->assertNotPresent('select[name="promoter_id"]')
        ->fill('name', 'Riverside Fest 2027')
        ->fill('about', 'Three days by the water.')
        ->press('Create festival')
        ->assertSee('Festival created.')
        ->assertSee('Riverside Fest 2027')
        ->assertSee('Northgate Promotions')
        ->assertNoJavaScriptErrors();

    $festival = Festival::sole();

    expect($festival->about)->toBe('Three days by the water.');

    $page->click('Riverside Fest 2027')
        ->assertSee('Edit festival')
        ->assertSee('A festival stays with the promoter it was made for.')
        ->fill('name', 'Riverside Fest 2028')
        ->press('Save changes')
        ->assertSee('Festival updated.')
        ->assertSee('Riverside Fest 2028')
        ->assertNoJavaScriptErrors();

    expect($festival->fresh()->name)->toBe('Riverside Fest 2028');
});

test('an account with two promoters chooses which puts the festival on', function () {
    $account = User::factory()->runsFestivals()->create();
    Promoter::factory()->for($account)->create(['name' => 'Avon Sounds']);
    $northgate = Promoter::factory()->for($account)->create(['name' => 'Northgate Promotions']);
    $this->actingAs($account);

    visit('/dashboard/festivals/create?promoter='.$northgate->id)
        ->assertSee("This can't be changed once the festival is made.")
        ->assertSelected('promoter_id', (string) $northgate->id)
        ->fill('name', 'Riverside Fest 2027')
        ->press('Create festival')
        ->assertSee('Festival created.')
        ->assertNoJavaScriptErrors();

    expect(Festival::sole()->promoter_id)->toBe($northgate->id);
});

test('deleting a festival asks first', function () {
    $account = User::factory()->runsFestivals()->create();
    $festival = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->create(['name' => 'Riverside Fest 2027']);
    $this->actingAs($account);

    visit('/dashboard/festivals/'.$festival->id.'/edit')
        ->assertSee('Delete this festival')
        ->assertSee('Its gig widgets are deleted and stop working on other websites.')
        ->press('Delete festival')
        ->assertNoJavaScriptErrors();

    expect(Festival::query()->whereKey($festival->id)->exists())->toBeTrue();
});

test('next year\'s festival starts from last year\'s', function () {
    $account = User::factory()->runsFestivals()->create();
    $lastYear = Festival::factory()
        ->for(Promoter::factory()->for($account))
        ->create(['name' => 'Riverside Fest 2027', 'about' => 'Three days by the water.']);
    $this->actingAs($account);

    visit('/dashboard/festivals/create')
        ->resize(390, 900)
        ->assertValue('about', '')
        ->select('#start_from', (string) $lastYear->id)
        ->assertValue('about', 'Three days by the water.')
        ->assertValue('name', '')
        ->fill('name', 'Riverside Fest 2028')
        ->press('Create festival')
        ->assertSee('Festival created.')
        ->assertNoJavaScriptErrors();

    expect(Festival::query()->where('name', 'Riverside Fest 2028')->sole()->about)
        ->toBe('Three days by the water.');
});
