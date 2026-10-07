<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attribution_touches', function (Blueprint $table): void {
            $table->string('campaign_key')->nullable()->index();
            $table->string('fbclid')->nullable();
            $table->string('ttclid')->nullable();
            $table->string('gclid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attribution_touches', function (Blueprint $table): void {
            $table->dropColumn(['campaign_key', 'fbclid', 'ttclid', 'gclid']);
        });
    }
};
