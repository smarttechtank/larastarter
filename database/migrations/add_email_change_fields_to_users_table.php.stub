<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->string('email_change_token', 60)->nullable()->after('pending_email');
            $table->timestamp('email_change_requested_at')->nullable()->after('email_change_token');

            // Add index for faster lookups
            $table->index(['email_change_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_change_token']);
            $table->dropColumn(['pending_email', 'email_change_token', 'email_change_requested_at']);
        });
    }
};
