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
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->timestamp('expiry_warning_sent_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->dropColumn('expiry_warning_sent_at');
        });
    }
};
