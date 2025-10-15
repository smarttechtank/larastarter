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
            $table->string('google_id')->nullable()->unique();
            $table->string('github_id')->nullable()->unique();
            $table->text('google_token')->nullable();
            $table->text('github_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->text('github_refresh_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'github_id',
                'google_token',
                'github_token',
                'google_refresh_token',
                'github_refresh_token'
            ]);
        });
    }
};


