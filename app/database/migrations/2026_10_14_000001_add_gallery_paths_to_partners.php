<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', fn (Blueprint $table) => $table->json('gallery_paths')->nullable());
    }

    public function down(): void
    {
        Schema::table('partners', fn (Blueprint $table) => $table->dropColumn('gallery_paths'));
    }
};
