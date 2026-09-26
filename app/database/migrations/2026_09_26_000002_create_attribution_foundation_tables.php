<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attribution_touches', function (Blueprint $table): void {
            $table->id(); $table->uuid('anonymous_id')->nullable()->index(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referral_code')->nullable()->index(); $table->foreignId('referrer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('utm_source')->nullable(); $table->string('utm_medium')->nullable(); $table->string('utm_campaign')->nullable()->index(); $table->string('utm_content')->nullable(); $table->string('utm_term')->nullable();
            $table->string('landing_page', 2048)->nullable(); $table->timestampTz('occurred_at')->index(); $table->jsonb('metadata')->nullable(); $table->timestampsTz();
            $table->index(['user_id', 'occurred_at']); $table->index(['referrer_user_id', 'occurred_at']);
        });
        Schema::create('referral_relationships', function (Blueprint $table): void {
            $table->id(); $table->foreignId('referrer_user_id')->constrained('users')->restrictOnDelete(); $table->foreignId('referred_user_id')->constrained('users')->restrictOnDelete();
            $table->string('referral_code'); $table->foreignId('attribution_touch_id')->nullable()->constrained()->nullOnDelete(); $table->timestampTz('attributed_at'); $table->timestampTz('expires_at')->nullable(); $table->string('status')->default('active'); $table->timestampsTz();
            $table->index(['referred_user_id', 'status']); $table->index(['referrer_user_id', 'status']);
        });
        DB::statement("ALTER TABLE referral_relationships ADD CONSTRAINT referral_relationships_no_self_referral CHECK (referrer_user_id <> referred_user_id)");
        DB::statement("CREATE UNIQUE INDEX referral_relationships_one_active_per_referred ON referral_relationships (referred_user_id) WHERE status = 'active'");
        Schema::create('conversions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('user_id')->constrained()->restrictOnDelete(); $table->string('type')->index(); $table->string('product_type')->nullable(); $table->string('product_key')->nullable(); $table->unsignedBigInteger('product_id')->nullable(); $table->string('order_reference')->nullable(); $table->string('idempotency_key')->unique();
            $table->decimal('gross_amount', 12, 2); $table->decimal('eligible_amount', 12, 2); $table->string('currency', 3)->default('BOB'); $table->string('status')->default('pending'); $table->timestampTz('occurred_at')->index();
            $table->foreignId('referral_relationship_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('attribution_touch_id')->nullable()->constrained()->nullOnDelete(); $table->jsonb('attribution_snapshot')->nullable(); $table->timestampsTz();
            $table->index(['user_id', 'occurred_at']); $table->index(['type', 'status']);
        });
        Schema::create('app_settings', function (Blueprint $table): void { $table->id(); $table->string('key')->unique(); $table->jsonb('value'); $table->timestampsTz(); });
        Schema::create('audit_logs', function (Blueprint $table): void { $table->id(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->string('action'); $table->string('subject_type'); $table->unsignedBigInteger('subject_id')->nullable(); $table->jsonb('metadata')->nullable(); $table->timestampsTz(); $table->index(['subject_type', 'subject_id']); });
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); Schema::dropIfExists('app_settings'); Schema::dropIfExists('conversions'); Schema::dropIfExists('referral_relationships'); Schema::dropIfExists('attribution_touches'); }
};
