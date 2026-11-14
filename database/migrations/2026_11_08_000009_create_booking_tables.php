<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();    // MTT-10482
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lead_first_name');
            $table->string('lead_last_name');
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('currency', 3)->default('MYR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('cost_total', 12, 2);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->string('status', 24)->default('pending_payment')->index();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->char('stripe_charge_currency', 3)->nullable(); // customer's currency if Adaptive Pricing used
            $table->decimal('stripe_charge_amount', 12, 2)->nullable();
            $table->decimal('stripe_fee', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('locale', 8)->default('en');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 24)->unique();    // MTT-10482-T1
            $table->string('product_type', 20)->index();  // transfer, tour, attraction
            $table->morphs('bookable');                   // TransferRoute, Tour, Attraction
            $table->string('title');                      // snapshot
            $table->date('service_date')->index();
            $table->time('service_time')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name');              // snapshot
            $table->string('supplier_email');             // snapshot
            $table->decimal('cost_total', 12, 2);
            $table->decimal('selling_total', 12, 2);
            $table->string('status', 24)->default('pending')->index();
            $table->string('supplier_token_hash', 64)->nullable()->unique(); // sha256 of the one-click token
            $table->timestamp('supplier_sent_at')->nullable();
            $table->timestamp('supplier_confirmed_at')->nullable();
            $table->timestamp('supplier_rejected_at')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->text('supplier_notes')->nullable();
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('cancellation_deadline')->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('eticket_path')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_item_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('pickup_location_name');
            $table->string('dropoff_location_name');
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_type_name');
            $table->string('flight_number', 12)->nullable();
            $table->dateTime('flight_arrival_at')->nullable();
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->unsignedTinyInteger('bags')->default(0);
            $table->string('sign_name')->nullable();
            $table->text('driver_notes')->nullable();
            $table->boolean('is_return_leg')->default(false);
            $table->foreignId('paired_item_id')->nullable()->constrained('booking_items')->nullOnDelete();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 32)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('booking_item_tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('option', 10);                 // sic, private
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_type_name')->nullable();
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->time('start_time')->nullable();
            $table->string('pickup_hotel')->nullable();
            $table->string('pickup_address')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_item_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attraction_ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_type_name');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
        });

        Schema::create('booking_item_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_item_extras');
        Schema::dropIfExists('booking_item_tickets');
        Schema::dropIfExists('booking_item_tours');
        Schema::dropIfExists('booking_item_transfers');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
