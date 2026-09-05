<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded files for this tenant. Paths are always under
 * media/{tenant_slug}/... so one company's files can never be served from
 * another's URL space (context.md §5.9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('alt')->nullable();

            // Central users table lives on another connection, so this is a
            // plain id — a foreign key across databases is not possible, and
            // cross-database joins are forbidden anyway (context.md §5.4).
            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index('mime');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('media');
    }
};
