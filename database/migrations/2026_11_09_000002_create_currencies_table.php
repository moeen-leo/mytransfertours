<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Display / charge currencies. Catalogue prices are entered in the base currency (MYR);
        // customers see and pay converted prices: price_in_currency = base_price * rate * (1 + buffer%).
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();               // MYR, USD, EUR, GBP
            $table->string('name');
            $table->string('symbol', 8);                      // RM, $, €, £
            $table->decimal('rate', 14, 6)->default(1);       // 1 MYR = rate x this currency
            $table->decimal('buffer_percent', 5, 2)->default(0); // covers FX swings / card fees
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamp('rate_updated_at')->nullable();
            $table->timestamps();
        });

        // Approximate starting rates (Nov 2026). The admin must review them in Pricing > Currencies.
        $now = now();
        DB::table('currencies')->insert([
            ['code' => 'MYR', 'name' => 'Malaysian ringgit', 'symbol' => 'RM', 'rate' => 1, 'buffer_percent' => 0, 'is_base' => true, 'is_active' => true, 'sort' => 1, 'rate_updated_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name' => 'US dollar', 'symbol' => '$', 'rate' => 0.2300, 'buffer_percent' => 2, 'is_base' => false, 'is_active' => true, 'sort' => 2, 'rate_updated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate' => 0.2100, 'buffer_percent' => 2, 'is_base' => false, 'is_active' => true, 'sort' => 3, 'rate_updated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'GBP', 'name' => 'British pound', 'symbol' => '£', 'rate' => 0.1800, 'buffer_percent' => 2, 'is_base' => false, 'is_active' => true, 'sort' => 4, 'rate_updated_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->char('preferred_currency', 3)->default('MYR')->after('country_code');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Totals on the booking are in `currency`; this is the MYR -> currency rate used,
            // so margins can always be reported back in MYR.
            $table->decimal('exchange_rate', 14, 6)->default(1)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('exchange_rate'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferred_currency'));
        Schema::dropIfExists('currencies');
    }
};
