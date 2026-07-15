<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nothing previously enforced this at the DB level — a stray
     * Institution::factory() call (nested inside UserFactory's default
     * institution_id) silently created thousands of duplicate rows sharing
     * an abbreviation with a real institution. Seeders already treat
     * abbreviation as the de-facto unique business key; this just makes the
     * database agree.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->unique('abbreviation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropUnique(['abbreviation']);
        });
    }
};
