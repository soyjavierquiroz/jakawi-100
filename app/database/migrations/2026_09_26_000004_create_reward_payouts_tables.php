<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('beneficiary_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->index();
            $table->timestampTz('requested_at')->index();
            $table->decimal('requested_amount', 12, 2);
            $table->string('currency', 3);
            $table->timestampTz('paid_at')->nullable()->index();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();
            $table->index(['beneficiary_user_id', 'status']);
        });

        Schema::create('reward_payout_reward_transaction', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reward_payout_id')->constrained()->restrictOnDelete();
            $table->foreignId('reward_transaction_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['reward_payout_id', 'reward_transaction_id']);
            $table->index('reward_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_payout_reward_transaction');
        Schema::dropIfExists('reward_payouts');
    }
};
