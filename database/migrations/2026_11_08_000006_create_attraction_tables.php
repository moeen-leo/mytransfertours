<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();
            $table->json('good_to_know')->nullable();
            $table->json('closed_weekdays')->nullable();  // ISO weekday numbers, [1] = Monday
            $table->boolean('is_refundable')->default(false);
            $table->unsignedSmallInteger('cancellation_hours')->default(0);
            $table->string('delivery_method', 20)->default('instant_voucher'); // instant_voucher, supplier_eticket
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city_id', 'is_active']);
        });

        Schema::create('attraction_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attraction_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // Adult, Child, Malaysian (MyKad)
            $table->string('age_label')->nullable();      // 13+
            $table->boolean('requires_id')->default(false);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->char('currency', 3)->default('MYR');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attraction_ticket_types');
        Schema::dropIfExists('attractions');
    }
};
