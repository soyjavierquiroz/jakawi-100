<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('redemption_pin_hash')->nullable()->after('cover_path');
        });

        Schema::table('benefits', function (Blueprint $table) {
            $table->unsignedInteger('redemption_limit_per_member')->nullable()->default(1)->after('estimated_savings');
        });
    }

    public function down(): void
    {
        Schema::table('benefits', function (Blueprint $table) {
            $table->dropColumn('redemption_limit_per_member');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('redemption_pin_hash');
        });
    }
};
