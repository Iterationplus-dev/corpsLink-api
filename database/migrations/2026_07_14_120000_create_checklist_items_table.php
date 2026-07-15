<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
