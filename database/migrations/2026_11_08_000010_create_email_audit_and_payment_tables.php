<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // supplier_new_booking, customer_confirmation, ...
            $table->string('name');
            $table->string('subject');
            $table->longText('body');                     // Markdown with {{placeholders}}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('trigger', 10)->default('automatic'); // automatic, manual
            $table->string('recipient');
            $table->json('cc')->nullable();
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->string('status', 10)->default('queued')->index(); // queued, sent, failed, opened
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_message_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = system
            $table->string('type', 40);
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('reason')->nullable();
            $table->string('stripe_refund_id')->nullable()->unique();
            $table->string('status', 12)->default('pending'); // pending, succeeded, failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();         // idempotency: each Stripe event handled once
            $table->string('type')->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('booking_activities');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
    }
};
