<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A writer was a name, a photograph and a note, kept in a table of its
 * own and credited beside an article. All three live on the account now,
 * where the person can keep them themselves, so the table and the two
 * columns that pointed at it go.
 *
 * link_writers_to_users copied everything across and credited every
 * feature to an account first. down() rebuilds the shape but not the
 * rows: the writers were dumped to storage/app/backups before this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        // features.writer_id predates this work and was never indexed;
        // users.writer_id was added alongside one and takes it with it.
        Schema::table('features', function (Blueprint $table): void {
            $table->dropColumn('writer_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['writer_id']);
            $table->dropColumn('writer_id');
        });

        Schema::dropIfExists('writers');
    }

    public function down(): void
    {
        Schema::create('writers', function (Blueprint $table): void {
            $table->id();
            $table->text('writer');
            $table->text('writer_bio')->nullable();
            $table->string('writer_photo')->nullable();
            $table->text('photo_alt_text')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('writer_id')->nullable()->after('is_writer')->index();
        });

        Schema::table('features', function (Blueprint $table): void {
            $table->foreignId('writer_id')->nullable()->after('listing_id');
        });
    }
};
