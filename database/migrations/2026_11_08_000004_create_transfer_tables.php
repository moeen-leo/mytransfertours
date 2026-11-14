<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('distance_km', 7, 1)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_bidirectional')->default(true); // same price both ways
            $table->unsignedSmallInteger('free_waiting_minutes')->default(60);
            $table->unsignedSmallInteger('cancellation_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_location_id', 'to_location_id']);
        });

        Schema::create('transfer_route_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete(); // overrides route supplier
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->char('currency', 3)->default('MYR');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['transfer_route_id', 'vehicle_type_id', 'is_active'], 'trp_route_vehicle_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_route_prices');
        Schema::dropIfExists('transfer_routes');
    }
};
