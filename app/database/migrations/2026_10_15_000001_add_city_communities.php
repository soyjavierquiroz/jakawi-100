<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->boolean('community_enabled')->default(false);
            $table->string('community_name')->nullable();
            $table->string('community_invite_url')->nullable();
        });
        Schema::create('community_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('not_invited');
            $table->unsignedSmallInteger('exposure_count')->default(0);
            $table->timestampTz('last_invited_at')->nullable();
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampTz('last_snoozed_at')->nullable();
            $table->string('last_session_hash', 64)->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_interactions');
        Schema::table('cities', fn (Blueprint $table) => $table->dropColumn(['community_enabled', 'community_name', 'community_invite_url']));
    }
};
