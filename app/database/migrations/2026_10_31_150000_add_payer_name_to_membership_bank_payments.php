<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_bank_payments', fn (Blueprint $t) => $t->string('payer_name')->nullable());
    }

    public function down(): void
    {
        Schema::table('membership_bank_payments', fn (Blueprint $t) => $t->dropColumn('payer_name'));
    }
};
