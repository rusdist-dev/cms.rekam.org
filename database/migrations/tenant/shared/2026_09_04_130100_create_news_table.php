<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('news', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->nullable()
                ->constrained('news_categories')
                // Deleting a category must not take its articles with it.
                ->nullOnDelete();

            // Translatable columns hold both locales in one row (plan.md §2.3):
            // {"id": "...", "en": "..."}. ID is required, EN optional.
            $table->json('title');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();

            $table->string('cover_path')->nullable();

            // Array of taxonomy slugs, never labels — renaming a programme in
            // settings must not rewrite content rows (context.md §5.12).
            $table->json('related_programs')->nullable();

            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('author_name')->nullable();
            $table->unsignedBigInteger('views')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // The public API's hot path: published articles newest first.
            $table->index(['status', 'published_at']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('news');
    }
};
