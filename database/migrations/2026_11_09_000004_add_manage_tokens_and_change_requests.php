<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Private "manage my booking" link for customers (works for guests too).
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('manage_token', 64)->nullable()->unique()->after('reference');
        });
        foreach (DB::table('bookings')->whereNull('manage_token')->pluck('id') as $id) {
            DB::table('bookings')->where('id', $id)->update(['manage_token' => Str::random(48)]);
        }

        // Customer asks to move a date/time; staff approve or decline in the admin.
        Schema::create('booking_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->nullable()->constrained()->nullOnDelete();
            $table->date('requested_date')->nullable();
            $table->time('requested_time')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 12)->default('pending')->index(); // pending, approved, declined
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_change_requests');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['manage_token']);
            $table->dropColumn('manage_token');
        });
    }
};
