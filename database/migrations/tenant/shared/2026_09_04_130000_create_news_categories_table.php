<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * News categories are relational rather than a site_settings list, because the
 * compro needs them as filters and menu entries with stable ids and per-locale
 * slugs (plan.md §5.3.a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('news_categories');
    }
};
