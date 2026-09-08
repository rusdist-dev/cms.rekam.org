<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('contact_messages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->text('message');

            // unread|read|archived. Hard-deleted, never soft-deleted — a
            // message is either in the inbox or it is gone (plan.md §5.4).
            $table->string('status', 16)->default('unread');
            $table->string('ip')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contact_messages');
    }
};
