<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('program_type')->index();
            $table->string('status')->default('active')->index();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'program_type', 'status']);
        });

        Schema::create('membership_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('beneficiary_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_channel')->index();
            $table->string('status')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BOB');
            $table->unsignedInteger('duration_days');
            $table->string('product_key');
            $table->string('manual_reference')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversion_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('refund_reason')->nullable();
            $table->timestampsTz();
            $table->index(['recorded_by_user_id', 'created_at']);
            $table->index(['collected_by_user_id', 'created_at']);
        });

        Schema::create('reward_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('participant_type')->nullable()->index();
            $table->string('event')->index();
            $table->string('product_key')->nullable()->index();
            $table->string('reward_type');
            $table->string('calculation_type');
            $table->decimal('value', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('maximum_rewards')->nullable();
            $table->unsignedInteger('maximum_per_user')->nullable();
            $table->timestampsTz();
        });

        Schema::create('reward_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('beneficiary_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('conversion_id')->constrained()->restrictOnDelete();
            $table->foreignId('reward_rule_id')->constrained()->restrictOnDelete();
            $table->string('reward_type');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->string('status')->index();
            $table->timestampTz('available_at')->nullable();
            $table->timestampsTz();
            $table->unique(['conversion_id', 'reward_rule_id']);
        });

        DB::statement("ALTER TABLE membership_purchases ADD CONSTRAINT membership_purchases_confirmed_has_manual_reference CHECK (payment_channel <> 'manual_cash' OR status <> 'confirmed' OR manual_reference IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_transactions');
        Schema::dropIfExists('reward_rules');
        Schema::dropIfExists('membership_purchases');
        Schema::dropIfExists('program_enrollments');
    }
};
