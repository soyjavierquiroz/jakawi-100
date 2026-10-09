<?php

use App\Enums\AcquisitionProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The default backfills every historical row without inferring authorization.
        foreach (['attribution_touches', 'analytics_events'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('acquisition_provider')->default(AcquisitionProvider::NONE->value));
            $values = implode(', ', array_map(fn (AcquisitionProvider $provider) => "'{$provider->value}'", AcquisitionProvider::cases()));
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_acquisition_provider_check CHECK (acquisition_provider IN ({$values}))");
        }
        Schema::table('analytics_events', fn (Blueprint $table) => $table->index(['acquisition_provider', 'occurred_at'], 'analytics_provider_occurred_at_index'));
    }

    public function down(): void
    {
        Schema::table('analytics_events', fn (Blueprint $table) => $table->dropIndex('analytics_provider_occurred_at_index'));
        foreach (['analytics_events', 'attribution_touches'] as $name) {
            DB::statement("ALTER TABLE {$name} DROP CONSTRAINT {$name}_acquisition_provider_check");
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('acquisition_provider'));
        }
    }
};
