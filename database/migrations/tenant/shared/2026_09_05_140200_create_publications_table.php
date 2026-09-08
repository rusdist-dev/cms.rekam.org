<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('publications', function (Blueprint $table) {
            $table->id();

            $table->json('title');
            $table->json('description')->nullable();

            // A `publication_categories` taxonomy slug from site_settings,
            // never an FK (context.md §5.12).
            $table->string('category');

            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('cover_path')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('publications');
    }
};
