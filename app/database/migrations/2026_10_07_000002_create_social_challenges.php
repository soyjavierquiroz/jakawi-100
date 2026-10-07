<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('benefits', fn (Blueprint $table) => $table->string('access_mode')->default('public')->index());
        Schema::create('social_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); $table->string('slug')->unique(); $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestampTz('starts_at')->nullable(); $table->timestampTz('ends_at')->nullable();
            $table->jsonb('allowed_platforms')->default('[]'); $table->jsonb('required_hashtags')->default('[]'); $table->jsonb('required_mentions')->default('[]');
            $table->timestampTz('published_from')->nullable(); $table->timestampTz('published_until')->nullable();
            $table->string('qualification_mode'); $table->string('evaluation_mode'); $table->string('metric')->nullable();
            $table->unsignedBigInteger('target')->nullable(); $table->unsignedInteger('winner_count')->nullable();
            $table->string('reward_type'); $table->foreignId('benefit_id')->nullable()->constrained()->nullOnDelete();
            $table->text('manual_prize_description')->nullable(); $table->timestamps();
        });
        Schema::create('social_challenge_participations', function (Blueprint $table) {
            $table->id(); $table->foreignId('social_challenge_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('social_url'); $table->char('normalized_url_hash', 64); $table->string('external_reference')->unique();
            $table->string('sharecontest_id')->nullable(); $table->string('sharecontest_request_id')->nullable(); $table->text('canonical_url')->nullable();
            $table->string('platform')->nullable(); $table->string('social_external_id')->nullable(); $table->string('author')->nullable(); $table->string('username')->nullable();
            $table->text('caption')->nullable(); $table->timestampTz('published_at')->nullable(); $table->string('published_at_precision')->nullable();
            $table->unsignedBigInteger('views')->nullable(); $table->unsignedBigInteger('likes')->nullable(); $table->unsignedBigInteger('comments')->nullable();
            $table->string('inspection_status')->default('pending'); $table->string('data_quality')->default('unknown');
            $table->string('validation_status')->default('not_requested'); $table->string('qualification_status')->default('pending');
            $table->string('moderation_status')->nullable(); $table->timestampTz('checked_at')->nullable(); $table->string('integration_error_code')->nullable();
            $table->jsonb('sharecontest_payload')->nullable(); $table->unsignedBigInteger('final_metric_value')->nullable(); $table->timestampTz('final_checked_at')->nullable();
            $table->timestampTz('last_refresh_requested_at')->nullable(); $table->unsignedInteger('refresh_count_today')->default(0); $table->date('refresh_count_date')->nullable();
            $table->boolean('refresh_pending')->default(false); $table->timestamps();
            $table->unique(['social_challenge_id', 'user_id']); $table->unique(['social_challenge_id', 'normalized_url_hash'], 'social_participations_url_unique');
        });
        DB::statement('CREATE UNIQUE INDEX social_participations_identity_unique ON social_challenge_participations (social_challenge_id, platform, social_external_id) WHERE social_external_id IS NOT NULL AND platform IS NOT NULL');
        Schema::create('social_challenge_reward_grants', function (Blueprint $table) {
            $table->id(); $table->foreignId('social_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->unique()->constrained('social_challenge_participations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('reward_type');
            $table->foreignId('benefit_id')->nullable()->constrained()->nullOnDelete(); $table->string('status')->default('granted');
            $table->timestampTz('granted_at'); $table->timestampTz('fulfilled_at')->nullable(); $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->jsonb('snapshot'); $table->timestamps();
        });
        Schema::table('redemptions', function (Blueprint $table) {
            $table->foreignId('membership_id')->nullable()->change();
            $table->foreignId('social_challenge_reward_grant_id')->nullable()->constrained('social_challenge_reward_grants')->restrictOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX redemptions_confirmed_social_grant_unique ON redemptions (social_challenge_reward_grant_id) WHERE status = 'confirmed' AND social_challenge_reward_grant_id IS NOT NULL");
    }
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS redemptions_confirmed_social_grant_unique');
        Schema::table('redemptions', function (Blueprint $table) { $table->dropConstrainedForeignId('social_challenge_reward_grant_id'); $table->foreignId('membership_id')->nullable(false)->change(); });
        Schema::dropIfExists('social_challenge_reward_grants'); Schema::dropIfExists('social_challenge_participations'); Schema::dropIfExists('social_challenges');
        Schema::table('benefits', fn (Blueprint $table) => $table->dropColumn('access_mode'));
    }
};
