<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->string('marketing_hook')->nullable();
            $table->string('marketing_headline')->nullable();
            $table->text('marketing_subheadline')->nullable();
            $table->string('marketing_reward_copy')->nullable();
            $table->string('hero_alt')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('challenges', fn (Blueprint $table) => $table->dropColumn(['marketing_hook','marketing_headline','marketing_subheadline','marketing_reward_copy','hero_alt']));
    }
};
