<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestones are a perikanan-only module (plan.md §5.3.d), so the table exists
 * only in perikanan's database (context.md §5.5).
 *
 * The module itself lands in Fase 4b behind the `milestones` feature flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('milestones', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body')->nullable();
            $table->string('cover_path')->nullable();

            // Drives the timeline ordering on the compro (plan.md §5.5 q5).
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['year', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('milestones');
    }
};
