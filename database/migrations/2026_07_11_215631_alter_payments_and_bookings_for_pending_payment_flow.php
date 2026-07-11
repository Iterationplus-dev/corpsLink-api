<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // A Payment now exists before a gateway is chosen — created
            // alongside a pending_payment Booking, gateway is set later at
            // POST /payments/{id}/initialize.
            $table->string('gateway')->nullable()->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Bookings are now created in a pending_payment state before
            // payment — an abandoned attempt must not permanently block
            // the seat for everyone else, so multiple (historical) rows
            // per seat are allowed; only one should ever end up Confirmed
            // (enforced in application code, not the DB).
            $table->dropUnique(['seat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unique('seat_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway')->nullable(false)->change();
        });
    }
};
