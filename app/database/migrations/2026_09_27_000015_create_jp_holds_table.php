<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jp_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unlock_participation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status');
            $table->string('reason')->default('unlock_commitment');
            $table->timestampTz('held_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('forfeited_at')->nullable();
            $table->timestampsTz();
            $table->unique('unlock_participation_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('jp_holds'); }
};
