<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unlocks', function (Blueprint $table) {
            $table->id();
            $table->string('title'); $table->string('slug')->unique();
            $table->text('short_description')->nullable(); $table->text('description')->nullable(); $table->text('terms')->nullable(); $table->string('hero_path')->nullable();
            $table->string('origin')->default('JAKAWI'); $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('BENEFIT'); $table->unsignedInteger('minimum_commitments'); $table->unsignedInteger('maximum_capacity')->nullable();
            $table->timestampTz('starts_at')->nullable(); $table->timestampTz('commitment_deadline')->nullable(); $table->timestampTz('confirmation_deadline')->nullable(); $table->timestampTz('fulfillment_starts_at')->nullable(); $table->timestampTz('fulfillment_ends_at')->nullable(); $table->timestampTz('cancellation_deadline')->nullable();
            $table->boolean('free_user_eligible')->default(true); $table->boolean('member_eligible')->default(true); $table->text('free_user_offer')->nullable(); $table->text('member_offer')->nullable();
            $table->unsignedInteger('jp_deposit')->default(0); $table->unsignedInteger('jp_completion_bonus')->default(0);
            $table->foreignId('linked_benefit_id')->nullable()->constrained('benefits')->nullOnDelete(); $table->foreignId('linked_experience_id')->nullable()->constrained('experiences')->nullOnDelete();
            $table->boolean('featured')->default(false); $table->boolean('secret_mode')->default(false); $table->boolean('hide_partner_until_unlock')->default(false); $table->boolean('hide_exact_offer_until_unlock')->default(false); $table->boolean('hide_location_until_unlock')->default(false);
            $table->string('status')->default('DRAFT'); $table->timestampTz('goal_reached_at')->nullable(); $table->timestampTz('status_changed_at')->nullable(); $table->timestampsTz();
            $table->index(['status', 'starts_at']); $table->index('commitment_deadline');
        });
        Schema::create('unlock_location', function (Blueprint $table) { $table->foreignId('unlock_id')->constrained()->cascadeOnDelete(); $table->foreignId('location_id')->constrained()->cascadeOnDelete(); $table->primary(['unlock_id', 'location_id']); });
        Schema::create('unlock_participations', function (Blueprint $table) { $table->id(); $table->foreignId('unlock_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('status'); $table->timestampTz('interested_at')->nullable(); $table->timestampTz('committed_at')->nullable(); $table->timestampTz('cancelled_at')->nullable(); $table->timestampsTz(); $table->unique(['unlock_id', 'user_id']); $table->index(['unlock_id', 'status']); });
        Schema::create('unlock_status_history', function (Blueprint $table) { $table->id(); $table->foreignId('unlock_id')->constrained()->cascadeOnDelete(); $table->string('from_status')->nullable(); $table->string('to_status'); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->text('reason')->nullable(); $table->timestampTz('created_at')->useCurrent(); $table->index(['unlock_id', 'created_at']); });
    }
    public function down(): void { Schema::dropIfExists('unlock_status_history'); Schema::dropIfExists('unlock_participations'); Schema::dropIfExists('unlock_location'); Schema::dropIfExists('unlocks'); }
};
