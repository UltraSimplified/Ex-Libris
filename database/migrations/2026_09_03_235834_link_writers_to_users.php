<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A writer and a user were two records for one person. This gives the
 * user everything the writer record carried, so an article can be
 * credited to the account that wrote it rather than to a name beside it.
 *
 * Nothing is dropped: writers keeps its rows and features keeps its
 * writer_id, so the old byline is still there to fall back on and to
 * check the backfill against.
 *
 * Following the convention of this schema, the new columns are plain
 * indexed keys - features.writer_id, tag_id and listing_id carry no
 * foreign key constraints either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /** Which writer record this account was made from. */
            $table->foreignId('writer_id')->nullable()->after('is_writer')->index();

            /** What writers.writer_bio held: the note that runs under a byline. */
            $table->text('bio')->nullable()->after('avatar');

            /** What writers.photo_alt_text held, for the same picture. */
            $table->text('avatar_alt_text')->nullable()->after('bio');
        });

        Schema::table('features', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('writer_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['writer_id']);
            $table->dropColumn(['writer_id', 'bio', 'avatar_alt_text']);
        });

        Schema::table('features', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
