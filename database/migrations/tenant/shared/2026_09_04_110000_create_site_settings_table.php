<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant key/value settings — identity, socials, SEO, and every taxonomy
 * list (team levels, categories, programs).
 *
 * This table is what lets the two companies differ without a schema change:
 * options live here as data, so adding a program is an editor's job, not a
 * deploy (context.md §5.12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 64);

            // JSON for everything: a scalar, a translatable map, or an ordered
            // list of options all live in the same column.
            $table->json('value')->nullable();

            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('site_settings');
    }
};
