<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('landing_presentations', fn (Blueprint $t) => $t->jsonb('editorial_blocks')->nullable());
        Schema::table('digital_products', function (Blueprint $t) {
            $t->string('category')->nullable();
            $t->jsonb('gallery_paths')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('digital_products', fn (Blueprint $t) => $t->dropColumn(['category', 'gallery_paths']));
        Schema::table('landing_presentations', fn (Blueprint $t) => $t->dropColumn('editorial_blocks'));
    }
};
