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
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('registration_token')->unique();

            // Step 1 — personal details
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Step 2 — school information
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_up_number')->nullable();
            $table->string('state_code')->nullable();
            $table->string('batch')->nullable();
            $table->string('stream')->nullable();

            // Step 3 — next of kin
            $table->string('nok_full_name')->nullable();
            $table->string('nok_relationship')->nullable();
            $table->string('nok_phone')->nullable();
            $table->string('nok_alternate_phone')->nullable();
            $table->string('nok_address')->nullable();
            $table->boolean('nok_apply_all')->default(true);

            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
