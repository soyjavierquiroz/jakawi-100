<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('landing_presentations', function (Blueprint $table) {
            $table->string('acquisition_headline', 180)->nullable();
            $table->text('acquisition_supporting_copy')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('landing_presentations', fn (Blueprint $table) => $table->dropColumn(['acquisition_headline', 'acquisition_supporting_copy']));
    }
};
