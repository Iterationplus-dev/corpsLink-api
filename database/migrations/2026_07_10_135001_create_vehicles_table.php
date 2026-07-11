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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('pickup_point');
            $table->string('destination');
            $table->dateTime('departure_at');
            $table->decimal('fare', 10, 2);
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['institution_id', 'is_active', 'departure_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
