<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rundown rows are edited inside the event form and saved with it in one
 * request — there is no per-row endpoint (context.md §4.11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('event_rundowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Session start time; the duration is implied by the next session.
            $table->time('time')->nullable();

            $table->json('title');
            $table->json('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('event_rundowns');
    }
};
