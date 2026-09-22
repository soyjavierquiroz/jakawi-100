<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_partner', function (Blueprint $table) {
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->integer('sort_order')->default(0);
            $table->unique(['experience_id', 'partner_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_partner');
    }
};
