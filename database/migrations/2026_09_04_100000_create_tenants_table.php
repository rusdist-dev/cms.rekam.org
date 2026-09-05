<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant registry — the single source of truth for which companies exist
 * and which database each one lives in (context.md §5.2). No other place in the
 * codebase may name a tenant database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Resolved into config('database.connections.tenant.database') at
            // runtime by ResolveTenant.
            $table->string('db_name')->unique();

            // Public website this tenant's content is published to; also used to
            // build the CORS allow-list for the public API.
            $table->string('domain')->nullable();

            // Hashed like a password: the plaintext key is shown once on
            // creation or rotation and never stored.
            $table->string('api_key')->nullable();
            $table->timestamp('api_key_generated_at')->nullable();

            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
