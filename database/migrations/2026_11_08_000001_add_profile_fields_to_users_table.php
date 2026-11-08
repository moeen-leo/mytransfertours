<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change(); // Google-only accounts have no password
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('phone', 32)->nullable()->after('avatar');
            $table->char('country_code', 2)->nullable()->after('phone');
            $table->string('role', 20)->default('customer')->index()->after('country_code');
            $table->boolean('marketing_opt_in')->default(false)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('marketing_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['google_id', 'avatar', 'phone', 'country_code', 'role', 'marketing_opt_in', 'last_login_at']);
        });
    }
};
