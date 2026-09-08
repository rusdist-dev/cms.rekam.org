<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('team_members', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // A single plain slug, not translatable — a person's name has no
            // ID/EN version to key off (context.md §6.3 does not apply here).
            $table->string('slug')->unique();

            // Translatable columns hold both locales in one row (plan.md §2.3).
            $table->json('position');
            $table->json('bio')->nullable();

            $table->string('photo_path')->nullable();

            // A `team_levels` taxonomy slug from site_settings, never an FK —
            // renaming a level in settings must not rewrite content rows
            // (context.md §5.12).
            $table->string('group');

            $table->string('email')->nullable();
            $table->json('socials')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('team_members');
    }
};
