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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->after('name');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->foreignId('institution_id')->nullable()->after('avatar_path')
                ->constrained()->nullOnDelete();
            $table->string('call_up_number')->nullable()->after('institution_id');
            $table->string('state_code')->nullable()->after('call_up_number');
            $table->string('batch')->nullable()->after('state_code');
            $table->string('stream')->nullable()->after('batch');
            $table->boolean('two_factor_enabled')->default(false)->after('stream');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
            $table->json('notification_preferences')->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->unique(['institution_id', 'call_up_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['institution_id', 'call_up_number']);
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn([
                'phone', 'avatar_path', 'call_up_number', 'state_code',
                'batch', 'stream', 'two_factor_enabled', 'last_login_at',
                'notification_preferences',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
