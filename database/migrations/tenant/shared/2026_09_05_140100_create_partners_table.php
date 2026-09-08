<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('partners', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // "Keterangan" — e.g. "Mitra Strategis", "Donor" (plan.md §5.2.g).
            $table->json('title')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('url')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('partners');
    }
};
