<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cities')->upsert([
            ['name' => 'Cochabamba', 'slug' => 'cochabamba', 'country_code' => 'BO', 'region' => 'Cochabamba', 'status' => 'ACTIVE', 'priority' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'La Paz', 'slug' => 'la-paz', 'country_code' => 'BO', 'region' => 'La Paz', 'status' => 'UNLOCKING', 'priority' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Santa Cruz', 'slug' => 'santa-cruz', 'country_code' => 'BO', 'region' => 'Santa Cruz', 'status' => 'UNLOCKING', 'priority' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sucre', 'slug' => 'sucre', 'country_code' => 'BO', 'region' => 'Chuquisaca', 'status' => 'COMING_SOON', 'priority' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tarija', 'slug' => 'tarija', 'country_code' => 'BO', 'region' => 'Tarija', 'status' => 'COMING_SOON', 'priority' => 10, 'created_at' => now(), 'updated_at' => now()],
        ], ['slug'], ['name', 'country_code', 'region', 'status', 'priority', 'updated_at']);

        Schema::table('locations', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->index('city_id');
        });

        $cochabambaId = DB::table('cities')->where('slug', 'cochabamba')->value('id');

        DB::table('locations')
            ->whereRaw("lower(btrim(city)) = 'cochabamba'")
            ->update(['city_id' => $cochabambaId]);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
