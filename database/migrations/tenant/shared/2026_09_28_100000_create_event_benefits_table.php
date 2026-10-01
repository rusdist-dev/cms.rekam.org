<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benefit rows are edited inside the event form and saved with it in one
 * request — same rule as rundown rows (context.md §4.11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('event_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->json('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('event_benefits');
    }
};
