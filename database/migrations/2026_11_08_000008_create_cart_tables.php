<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->char('currency', 3)->default('MYR');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->string('product_type', 20);           // transfer, tour, attraction
            $table->morphs('bookable');
            $table->date('service_date');
            $table->time('service_time')->nullable();
            $table->json('payload');                      // everything the customer selected
            $table->decimal('quoted_cost', 12, 2);
            $table->decimal('quoted_price', 12, 2);
            $table->timestamp('quoted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
