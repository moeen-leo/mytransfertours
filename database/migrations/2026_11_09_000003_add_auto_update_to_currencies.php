<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            // Daily rate refresh from the European Central Bank (via Frankfurter). Turn off to freeze a rate.
            $table->boolean('auto_update')->default(true)->after('buffer_percent');
            $table->string('rate_source', 40)->nullable()->after('rate_updated_at');
        });

        DB::table('currencies')->where('is_base', true)->update(['auto_update' => false, 'rate_source' => 'base']);

        // Recommended safety buffer for foreign currencies: 3 %.
        DB::table('currencies')->where('is_base', false)->where('buffer_percent', 2)->update(['buffer_percent' => 3]);
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['auto_update', 'rate_source']);
        });
    }
};
