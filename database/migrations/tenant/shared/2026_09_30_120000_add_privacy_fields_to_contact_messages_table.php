<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('contact_messages', function (Blueprint $table) {
            $table->text('address')->nullable()->after('message');
            $table->boolean('is_private')->nullable()->default(false)->after('address');
        });

        Schema::connection('tenant')->table('contact_messages', function (Blueprint $table) {
            $table->string('subject')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('contact_messages', function (Blueprint $table) {
            $table->string('subject')->nullable(false)->change();
        });

        Schema::connection('tenant')->table('contact_messages', function (Blueprint $table) {
            $table->dropColumn(['address', 'is_private']);
        });
    }
};
