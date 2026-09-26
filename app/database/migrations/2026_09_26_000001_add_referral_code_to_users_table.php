<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('referral_code')->nullable()->after('email');
            $table->string('referral_code_normalized')->nullable()->unique()->after('referral_code');
        });
    }
    public function down(): void { Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['referral_code_normalized'])->dropColumn(['referral_code', 'referral_code_normalized'])); }
};
