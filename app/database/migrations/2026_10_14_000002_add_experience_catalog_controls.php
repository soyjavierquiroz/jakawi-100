<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->string('catalog_visibility')->default('hidden');
            $table->boolean('operations_confirmed')->default(false);
        });
        // Keep existing non-draft catalog behavior, while new records are private by default.
        DB::table('experiences')->where('status', '!=', 'draft')->update(['catalog_visibility' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('experiences', fn (Blueprint $table) => $table->dropColumn(['catalog_visibility', 'operations_confirmed']));
    }
};
