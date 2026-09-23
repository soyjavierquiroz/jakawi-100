<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff'])->default('staff');
            $table->timestamps();

            $table->primary(['user_id', 'partner_id']);
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_user');
    }
};
