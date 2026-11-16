<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A festival: a promoter's events across one or more venues, under a
     * name and branding of its own. It carries the same details a promoter
     * does, never borrowed from its promoter, and belongs to that promoter
     * for good.
     *
     * A listing joins one through festival_id, and loses it rather than
     * going with it when the festival is deleted. Whether the festival's
     * mark is drawn on the listing's cover is the listing's own choice,
     * on unless somebody turns it off.
     *
     * Runs Festivals is an account's permission to manage its promoters'
     * festivals from Backstage, granted in the Office like the other roles,
     * but never assumed for staff.
     */
    public function up(): void
    {
        Schema::create('festivals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promoter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->text('about')->nullable();
            $table->string('brand_colour')->nullable();
            $table->string('accent_colour1')->nullable();
            $table->string('accent_colour2')->nullable();
            $table->string('brand_background_colour')->nullable();
            $table->timestamps();
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->foreignId('festival_id')
                ->nullable()
                ->after('promoter_id')
                ->constrained()
                ->nullOnDelete();
            $table->boolean('show_festival_mark')->default(true)->after('festival_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('runs_festivals')->default(false)->after('is_publisher');
        });
    }

    /**
     * Rolling back loses every festival and every listing's place in one.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('runs_festivals');
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->dropForeign(['festival_id']);
            $table->dropColumn(['festival_id', 'show_festival_mark']);
        });

        Schema::dropIfExists('festivals');
    }
};
