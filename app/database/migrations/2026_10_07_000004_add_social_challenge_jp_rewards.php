<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_challenges', fn (Blueprint $table) => $table->unsignedInteger('reward_jp_amount')->nullable());
        Schema::table('social_challenge_reward_grants', fn (Blueprint $table) => $table->unsignedInteger('jp_amount')->nullable());
        Schema::table('reward_transactions', function (Blueprint $table): void {
            $table->foreignId('social_challenge_reward_grant_id')->nullable()->unique()->constrained('social_challenge_reward_grants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE social_challenges ADD CONSTRAINT social_challenges_jp_reward_check CHECK ((reward_type = 'JP' AND reward_jp_amount IS NOT NULL AND reward_jp_amount > 0) OR (reward_type IN ('BENEFIT', 'MANUAL_PRIZE') AND reward_jp_amount IS NULL))");
        DB::statement("ALTER TABLE social_challenge_reward_grants ADD CONSTRAINT social_challenge_grants_jp_reward_check CHECK ((reward_type = 'JP' AND jp_amount IS NOT NULL AND jp_amount > 0) OR (reward_type IN ('BENEFIT', 'MANUAL_PRIZE') AND jp_amount IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE social_challenge_reward_grants DROP CONSTRAINT social_challenge_grants_jp_reward_check');
        DB::statement('ALTER TABLE social_challenges DROP CONSTRAINT social_challenges_jp_reward_check');
        Schema::table('reward_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('social_challenge_reward_grant_id'));
        Schema::table('social_challenge_reward_grants', fn (Blueprint $table) => $table->dropColumn('jp_amount'));
        Schema::table('social_challenges', fn (Blueprint $table) => $table->dropColumn('reward_jp_amount'));
    }
};
