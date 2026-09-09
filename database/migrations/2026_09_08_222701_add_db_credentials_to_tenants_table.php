<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some hosts lock every MySQL database to its own dedicated user, with no way
 * to grant one shared user access to more than one database — the shared
 * DB_TENANT_USERNAME/PASSWORD in .env (config/database.php's `tenant`
 * connection) cannot cover that. Both columns are nullable: a tenant with
 * neither set keeps using the shared .env credentials exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('db_username')->nullable()->after('db_name');
            $table->text('db_password')->nullable()->after('db_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['db_username', 'db_password']);
        });
    }
};
