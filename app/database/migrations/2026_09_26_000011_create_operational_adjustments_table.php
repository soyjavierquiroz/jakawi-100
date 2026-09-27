<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operational_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type')->index();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('beneficiary_type')->nullable()->index();
            $table->unsignedBigInteger('beneficiary_id')->nullable()->index();
            $table->string('unit', 8)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('reason');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampsTz();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_adjustments');
    }
};
