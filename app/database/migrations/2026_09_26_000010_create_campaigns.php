<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('draft')->index();
            $table->text('description')->nullable();
            $table->string('event')->default('membership_purchased');
            $table->string('product_key')->nullable();
            $table->timestampTz('start_at')->nullable()->index();
            $table->timestampTz('end_at')->nullable()->index();
            $table->timestampsTz();
        });
        Schema::create('campaign_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('participant_type');
            $table->unique(['campaign_id', 'participant_type']);
        });
        Schema::table('reward_rules', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['campaign_id', 'status']);
        });
        Schema::table('conversions', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->nullable()->after('attribution_touch_id')->constrained()->nullOnDelete();
            $table->index(['campaign_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table): void { $table->dropIndex(['campaign_id', 'occurred_at']); $table->dropConstrainedForeignId('campaign_id'); });
        Schema::table('reward_rules', function (Blueprint $table): void { $table->dropIndex(['campaign_id', 'status']); $table->dropConstrainedForeignId('campaign_id'); });
        Schema::dropIfExists('campaign_participants');
        Schema::dropIfExists('campaigns');
    }
};
