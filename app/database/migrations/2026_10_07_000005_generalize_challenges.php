<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The first release has no production rows. Refuse a destructive reshape if that changes.
        foreach (['social_challenges', 'social_challenge_participations', 'social_challenge_reward_grants'] as $table) {
            if (DB::table($table)->exists()) throw new RuntimeException("Cannot generalize populated {$table} automatically.");
        }
        if (DB::table('reward_transactions')->whereNotNull('social_challenge_reward_grant_id')->exists()) {
            throw new RuntimeException('Cannot generalize challenges with linked reward transactions.');
        }

        Schema::rename('social_challenges', 'challenges');
        Schema::rename('social_challenge_participations', 'challenge_participations');
        Schema::rename('social_challenge_reward_grants', 'challenge_reward_grants');
        DB::statement('ALTER TABLE challenge_participations RENAME COLUMN social_challenge_id TO challenge_id');
        DB::statement('ALTER TABLE challenge_reward_grants RENAME COLUMN social_challenge_id TO challenge_id');
        DB::statement('ALTER TABLE challenge_participations DROP CONSTRAINT social_participations_url_unique');
        DB::statement('DROP INDEX social_participations_identity_unique');

        Schema::table('challenges', function (Blueprint $table) {
            $table->string('evidence_type')->default('SOCIAL_POST');
            $table->string('qualification_type')->default('VALID_EVIDENCE');
            $table->string('qualification_metric')->nullable();
            $table->unsignedBigInteger('qualification_target')->nullable();
            $table->string('selection_type')->default('ALL_QUALIFIED');
            $table->string('selection_metric')->nullable();
            $table->unsignedInteger('winner_limit')->nullable();
            $table->string('review_mode')->default('AUTOMATIC');
            $table->string('participation_eligibility')->default('ALL_USERS');
            $table->string('reward_eligibility')->default('ALL_USERS');
            $table->unsignedInteger('max_entries_per_user')->nullable();
        });
        Schema::table('challenges', fn (Blueprint $table) => $table->dropColumn(['qualification_mode', 'metric', 'target', 'winner_count']));
        DB::statement('ALTER TABLE challenges ALTER COLUMN evaluation_mode SET DEFAULT \'CONTINUOUS\'');

        Schema::create('challenge_social_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained('challenge_participations')->cascadeOnDelete();
            $table->text('social_url'); $table->char('normalized_url_hash', 64); $table->string('external_reference')->unique();
            $table->string('sharecontest_id')->nullable(); $table->string('sharecontest_request_id')->nullable(); $table->text('canonical_url')->nullable();
            $table->string('platform')->nullable(); $table->string('social_external_id')->nullable(); $table->string('author')->nullable(); $table->string('username')->nullable();
            $table->text('caption')->nullable(); $table->timestampTz('published_at')->nullable(); $table->string('published_at_precision')->nullable();
            $table->unsignedBigInteger('views')->nullable(); $table->unsignedBigInteger('likes')->nullable(); $table->unsignedBigInteger('comments')->nullable();
            $table->string('inspection_status')->default('pending'); $table->string('data_quality')->default('unknown');
            $table->string('validation_status')->default('not_requested'); $table->string('moderation_status')->nullable();
            $table->timestampTz('checked_at')->nullable(); $table->string('integration_error_code')->nullable(); $table->jsonb('sharecontest_payload')->nullable();
            $table->unsignedBigInteger('final_metric_value')->nullable(); $table->timestampTz('final_checked_at')->nullable();
            $table->timestampTz('last_refresh_requested_at')->nullable(); $table->unsignedInteger('refresh_count_today')->default(0);
            $table->date('refresh_count_date')->nullable(); $table->boolean('refresh_pending')->default(false); $table->timestamps();
            $table->unique(['challenge_id', 'normalized_url_hash']);
            $table->index(['participation_id', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX challenge_social_entries_identity_unique ON challenge_social_entries (challenge_id, platform, social_external_id) WHERE platform IS NOT NULL AND social_external_id IS NOT NULL');

        Schema::table('challenge_participations', function (Blueprint $table) {
            $table->dropColumn(['social_url', 'normalized_url_hash', 'external_reference', 'sharecontest_id', 'sharecontest_request_id', 'canonical_url',
                'platform', 'social_external_id', 'author', 'username', 'caption', 'published_at', 'published_at_precision', 'views', 'likes',
                'comments', 'inspection_status', 'data_quality', 'validation_status', 'moderation_status', 'checked_at', 'integration_error_code',
                'sharecontest_payload', 'final_metric_value', 'final_checked_at', 'last_refresh_requested_at', 'refresh_count_today',
                'refresh_count_date', 'refresh_pending']);
            $table->foreignId('qualified_entry_id')->nullable()->constrained('challenge_social_entries')->nullOnDelete();
            $table->timestampTz('qualified_at')->nullable();
            $table->foreignId('selected_entry_id')->nullable()->constrained('challenge_social_entries')->nullOnDelete();
            $table->string('selection_status')->default('pending');
            $table->timestampTz('selected_at')->nullable();
            $table->string('review_status')->nullable();
        });
        Schema::table('challenge_reward_grants', fn (Blueprint $table) => $table->foreignId('winning_entry_id')->nullable()->constrained('challenge_social_entries')->nullOnDelete());
    }

    public function down(): void
    {
        throw new RuntimeException('Challenge generalization is intentionally irreversible; restore a backup to roll back.');
    }
};
