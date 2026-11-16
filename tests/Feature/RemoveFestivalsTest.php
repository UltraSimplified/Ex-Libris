<?php

use App\Models\Listing;
use App\Models\Promoter;
use App\Models\Widget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the festivals migration left on staging and on live before it was
 * taken out of the code: the table, a listing's place in a festival, and the
 * permission on an account.
 */
function festivalsAsTheyWereLeft(): void
{
    Schema::create('festivals', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('promoter_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });

    Schema::table('listings', function (Blueprint $table): void {
        $table->foreignId('festival_id')->nullable()->constrained()->nullOnDelete();
        $table->boolean('show_festival_mark')->default(true);
    });

    Schema::table('users', function (Blueprint $table): void {
        $table->boolean('runs_festivals')->default(false);
    });
}

test('what festivals left behind is cleared away, and nothing else', function () {
    festivalsAsTheyWereLeft();

    $promoter = Promoter::factory()->create();
    $festivalId = DB::table('festivals')->insertGetId(['promoter_id' => $promoter->id, 'name' => 'Riverside Fest 2027']);
    $listing = Listing::factory()->create(['title' => 'Riverside Opening Night', 'promoter_id' => $promoter->id]);
    DB::table('listings')->where('id', $listing->id)->update(['festival_id' => $festivalId]);

    $promotersWidget = Widget::factory()->forPromoter($promoter)->create();
    $festivalsWidget = Widget::factory()->create();
    DB::table('widgets')->where('id', $festivalsWidget->id)->update(['owner_type' => 'App\Models\Festival', 'owner_id' => $festivalId]);

    (require database_path('migrations/2026_11_16_173456_remove_festivals.php'))->up();

    expect(Schema::hasTable('festivals'))->toBeFalse()
        ->and(Schema::hasColumn('listings', 'festival_id'))->toBeFalse()
        ->and(Schema::hasColumn('listings', 'show_festival_mark'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'runs_festivals'))->toBeFalse()
        ->and($listing->fresh()->title)->toBe('Riverside Opening Night')
        ->and($promoter->fresh())->not->toBeNull()
        ->and(Widget::query()->pluck('id')->all())->toBe([$promotersWidget->id]);
});
