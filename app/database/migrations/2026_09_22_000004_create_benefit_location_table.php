<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_location', function (Blueprint $table) {
            $table->foreignId('benefit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->unique(['benefit_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_location');
    }
};
