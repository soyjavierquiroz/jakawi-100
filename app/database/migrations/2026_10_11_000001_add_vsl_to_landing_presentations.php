<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('landing_presentations', function (Blueprint $table) {
            $table->json('vsl')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('landing_presentations', fn (Blueprint $table) => $table->dropColumn('vsl'));
    }
};
