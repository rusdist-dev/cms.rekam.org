<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units are a rekam-only module (plan.md §5.2.e), so the table exists only in
 * rekam's database — not created-and-left-empty in the other company
 * (context.md §5.5).
 *
 * The module itself (model, API, pages) lands in Fase 4b behind the `units`
 * feature flag; the table is here to prove the per-tenant migration mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('description')->nullable();
            $table->string('url')->nullable();
            $table->string('domain')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('units');
    }
};
