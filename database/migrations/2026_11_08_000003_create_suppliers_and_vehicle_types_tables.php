<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email');                      // booking emails go here
            $table->json('cc_emails')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->text('bank_details')->nullable();     // cast as encrypted in the model
            $table->string('payment_terms')->nullable();  // e.g. "Monthly, 30 days"
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Standard sedan
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('example_models')->nullable(); // Toyota Vios or similar
            $table->unsignedTinyInteger('max_pax');
            $table->unsignedTinyInteger('max_bags');
            $table->string('image')->nullable();
            $table->json('features')->nullable();         // ["Meet & greet", "Wi-Fi"]
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('suppliers');
    }
};
