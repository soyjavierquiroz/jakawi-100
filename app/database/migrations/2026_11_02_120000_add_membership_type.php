<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('type')->default('standard')->index();
        });
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_type_check CHECK (type IN ('standard', 'pioneer'))");
    }

    public function down(): void
    {
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
