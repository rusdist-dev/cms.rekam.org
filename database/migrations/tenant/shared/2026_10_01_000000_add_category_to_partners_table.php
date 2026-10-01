<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('partners', function (Blueprint $table) {
            // Fixed set (`cms.partner_categories`), not a relational taxonomy —
            // the public API groups partners by this value (plan.md §5.2.g).
            $table->string('category')->nullable()->after('title');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('partners', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
