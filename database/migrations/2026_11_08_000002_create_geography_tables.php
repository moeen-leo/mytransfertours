<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();          // MY
            $table->string('name');                       // Malaysia
            $table->char('currency', 3)->default('MYR');
            $table->string('phone_code', 8)->nullable();  // +60
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone', 64)->default('Asia/Kuala_Lumpur');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('name');                       // Kuala Lumpur International Airport
            $table->string('short_name')->nullable();     // KLIA
            $table->string('type', 20)->index();          // airport, city_area, hotel_zone, port, station, attraction, other
            $table->char('iata_code', 3)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('requires_flight_number')->default(false);
            $table->boolean('requires_address')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['city_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
