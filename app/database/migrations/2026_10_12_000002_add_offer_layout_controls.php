<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_presentations', function (Blueprint $table) {
            $table->string('hero_layout', 20)->nullable();
            $table->string('density', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landing_presentations', fn (Blueprint $table) => $table->dropColumn(['hero_layout', 'density']));
    }
};
