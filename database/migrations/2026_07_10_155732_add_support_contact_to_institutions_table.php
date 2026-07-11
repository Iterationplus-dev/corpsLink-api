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
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('support_phone')->nullable()->after('logo_path');
            $table->string('support_email')->nullable()->after('support_phone');
            $table->string('support_hours')->nullable()->after('support_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['support_phone', 'support_email', 'support_hours']);
        });
    }
};
