<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('landing_presentations', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('DRAFT');
            $table->boolean('is_default')->default(false);
            $table->string('campaign_key')->nullable();
            $table->string('eyebrow')->nullable();
            $table->string('headline')->nullable();
            $table->text('subheadline')->nullable();
            $table->string('hero_path')->nullable();
            $table->string('hero_alt')->nullable();
            $table->string('reward_display_override')->nullable();
            $table->string('final_cta_headline')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_presentations_status_check CHECK (status IN ('DRAFT', 'PUBLISHED', 'ARCHIVED'))");
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_presentations_default_published_check CHECK (NOT is_default OR status = 'PUBLISHED')");
        DB::statement('CREATE UNIQUE INDEX landing_presentations_one_default ON landing_presentations (subject_type, subject_id) WHERE is_default = true');
    }

    public function down(): void { Schema::dropIfExists('landing_presentations'); }
};
