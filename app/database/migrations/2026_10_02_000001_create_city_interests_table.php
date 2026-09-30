<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visitor_id')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('identity_hash')->nullable();
            $table->foreignId('attribution_touch_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('attribution_snapshot')->nullable();
            $table->timestampsTz();

            $table->index('city_id');
            $table->index(['city_id', 'visitor_id']);
        });

        DB::statement('CREATE UNIQUE INDEX city_interests_city_user_unique ON city_interests (city_id, user_id) WHERE user_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX city_interests_city_visitor_unique ON city_interests (city_id, visitor_id) WHERE visitor_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('city_interests');
    }
};
