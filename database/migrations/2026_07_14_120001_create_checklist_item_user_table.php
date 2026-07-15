<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_item_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_item_user');
    }
};
