<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('events', function (Blueprint $table) {
            $table->id();

            $table->json('title');
            $table->json('slug');
            $table->json('description')->nullable();
            $table->json('location')->nullable();
            $table->json('fee_note')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();

            // Slug from site_settings (event.categories), options per tenant.
            $table->string('category', 64)->nullable();

            $table->timestamp('start_at')->nullable();
            // Empty end_at means a single-day event (plan.md §5.2.c).
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_all_day')->default(false);

            // Null or 0 reads as free; fee_note carries wording like
            // "gratis untuk mahasiswa".
            $table->decimal('fee', 12, 2)->nullable();

            // Informational only — this CMS has no registration module, so no
            // seats are ever counted (plan.md §5.2.c).
            $table->unsignedInteger('quota')->nullable();
            $table->string('registration_url')->nullable();

            $table->string('cover_path')->nullable();
            $table->string('status', 16)->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('events');
    }
};
