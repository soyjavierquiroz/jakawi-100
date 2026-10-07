<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('challenges', function (Blueprint $table) {
            $table->string('review_status')->default('APPROVED')->index();
            $table->text('review_comment')->nullable();
            $table->text('instructions')->nullable();
            $table->string('hero_path')->nullable();
            $table->string('ranking_visibility')->default('NONE');
            $table->unsignedInteger('ranking_refresh_interval_minutes')->default(30);
        });
    }
    public function down(): void {
        Schema::table('challenges', fn (Blueprint $table) => $table->dropColumn(['review_status','review_comment','instructions','hero_path','ranking_visibility','ranking_refresh_interval_minutes']));
    }
};
