<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('experience_reservations', function (Blueprint $table) {
            $table->unsignedInteger('party_size')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('experience_reservations', function (Blueprint $table) {
            $table->dropColumn('party_size');
        });
    }
};
