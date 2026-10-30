<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The role columns say what they are: is_promoter reads as a question
     * with an answer, where promoter sat on the model beside a promoters
     * relation meaning something else entirely.
     *
     * A rename rather than a correction to the migration that added them:
     * that one has already run, so editing it would leave the columns
     * behind wherever it had.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'venue_mgr' => 'is_venue_mgr',
        'promoter' => 'is_promoter',
        'writer' => 'is_writer',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::RENAMES as $from => $to) {
                $table->renameColumn($from, $to);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::RENAMES as $from => $to) {
                $table->renameColumn($to, $from);
            }
        });
    }
};
