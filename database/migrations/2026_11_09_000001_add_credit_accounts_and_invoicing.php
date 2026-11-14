<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Billing details printed on invoices
            $table->string('company_name')->nullable()->after('name');
            $table->text('billing_address')->nullable()->after('country_code');
            $table->string('tax_number', 50)->nullable()->after('billing_address');

            // Credit account: selected customers may confirm bookings without paying by card
            $table->boolean('credit_enabled')->default(false)->after('role');
            $table->decimal('credit_limit', 12, 2)->nullable()->after('credit_enabled');
            $table->unsignedSmallInteger('credit_terms_days')->default(30)->after('credit_limit');
            $table->text('admin_notes')->nullable()->after('credit_terms_days');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('card')->index()->after('status');   // card, credit
            $table->string('payment_status', 20)->default('unpaid')->index()->after('payment_method'); // unpaid, paid, partially_refunded, refunded
            $table->date('payment_due_at')->nullable()->after('payment_status');
            $table->timestamp('settled_at')->nullable()->after('paid_at');
            $table->string('settlement_reference')->nullable()->after('settled_at');
            $table->foreignId('settled_by')->nullable()->after('settlement_reference')->constrained('users')->nullOnDelete();

            $table->string('invoice_number', 30)->nullable()->unique()->after('reference');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');

            // Snapshot of the billing details at the time of booking
            $table->string('billing_company')->nullable()->after('country_code');
            $table->text('billing_address')->nullable()->after('billing_company');
            $table->string('billing_tax_number', 50)->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropUnique(['invoice_number']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn([
                'payment_method', 'payment_status', 'payment_due_at', 'settled_at', 'settlement_reference',
                'invoice_number', 'invoiced_at', 'billing_company', 'billing_address', 'billing_tax_number',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'billing_address', 'tax_number', 'credit_enabled', 'credit_limit', 'credit_terms_days', 'admin_notes']);
        });
    }
};
