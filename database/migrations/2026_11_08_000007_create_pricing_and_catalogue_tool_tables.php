<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('product_type', 20);           // transfer, tour
            $table->string('name');                       // Child seat
            $table->string('description')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2);
            $table->char('currency', 3)->default('MYR');
            $table->unsignedTinyInteger('max_quantity')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->morphs('bookable');                   // Tour, Attraction or TransferRoute
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['bookable_type', 'bookable_id', 'date']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Weekend uplift, CNY peak, Night surcharge
            $table->string('product_type', 20)->nullable(); // null = every product type
            $table->nullableMorphs('bookable');           // null = every product of that type
            $table->string('rule_type', 20);              // weekday, date_range, time_range
            $table->json('weekdays')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->string('adjustment_type', 10);        // percent, fixed
            $table->decimal('adjustment_value', 12, 2);   // can be negative for discounts
            $table->string('applies_to', 10)->default('selling'); // selling, cost, both
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_type', 'is_active']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 10);                   // percent, fixed
            $table->decimal('value', 12, 2);
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('blackout_dates');
        Schema::dropIfExists('extras');
    }
};
