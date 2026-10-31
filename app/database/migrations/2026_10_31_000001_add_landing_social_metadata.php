<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_presentations', function (Blueprint $table) {
            $table->string('seo_title', 180)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('social_image_path', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landing_presentations', fn (Blueprint $table) => $table->dropColumn(['seo_title', 'seo_description', 'social_image_path']));
    }
};
