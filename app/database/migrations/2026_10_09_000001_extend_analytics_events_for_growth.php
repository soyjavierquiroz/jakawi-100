<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->uuid('event_id')->nullable()->unique();
            $table->foreignId('attribution_touch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('landing_presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('landing_slug')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('campaign_key')->nullable();
            foreach (['source', 'medium', 'campaign', 'content', 'term'] as $key) $table->string('utm_'.$key)->nullable();
            $table->string('source_route')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unique(['event_name', 'source_type', 'source_id'], 'analytics_growth_outcome_unique');
            $table->index(['landing_presentation_id', 'occurred_at']);
            $table->index(['campaign_key', 'occurred_at']);
            $table->index('attribution_touch_id');
        });
        DB::table('analytics_events')->select('id')->orderBy('id')->chunkById(500, function ($events) {
            foreach ($events as $event) DB::table('analytics_events')->where('id', $event->id)->update(['event_id' => (string) Str::uuid()]);
        });
        Schema::table('analytics_events', fn (Blueprint $table) => $table->uuid('event_id')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropForeign(['attribution_touch_id']);
            $table->dropForeign(['landing_presentation_id']);
            $table->dropUnique('analytics_growth_outcome_unique');
            $table->dropIndex(['landing_presentation_id', 'occurred_at']);
            $table->dropIndex(['campaign_key', 'occurred_at']);
            $table->dropIndex(['attribution_touch_id']);
            $table->dropUnique(['event_id']);
            $table->dropColumn(['event_id', 'attribution_touch_id', 'landing_presentation_id', 'landing_slug', 'subject_type', 'subject_id', 'campaign_key', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'source_route', 'source_type', 'source_id']);
        });
    }
};
