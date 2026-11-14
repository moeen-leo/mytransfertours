<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();
            $table->json('highlights')->nullable();
            $table->json('itinerary')->nullable();        // [{"time":"08:30","text":"Hotel pick-up"}]
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('has_sic')->default(true);     // seat-in-coach, priced per person
            $table->boolean('has_private')->default(true); // private, priced per vehicle
            $table->json('sic_departure_times')->nullable();
            $table->json('private_start_times')->nullable();
            $table->unsignedTinyInteger('child_age_from')->default(3);
            $table->unsignedTinyInteger('child_age_to')->default(11);
            $table->unsignedTinyInteger('min_pax')->default(1);
            $table->unsignedSmallInteger('cancellation_hours')->default(24);
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city_id', 'is_active']);
        });

        Schema::create('tour_sic_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('pax_type', 10);               // adult, child
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->char('currency', 3)->default('MYR');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'pax_type']);
        });

        Schema::create('tour_private_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->char('currency', 3)->default('MYR');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'vehicle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_private_prices');
        Schema::dropIfExists('tour_sic_prices');
        Schema::dropIfExists('tours');
    }
};
