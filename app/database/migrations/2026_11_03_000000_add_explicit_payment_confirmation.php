<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_bank_payments', function (Blueprint $table) {
            // Historical notices and receipt uploads are not explicit confirmations.
            $table->timestampTz('payment_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('membership_bank_payments', fn (Blueprint $table) => $table->dropColumn('payment_confirmed_at'));
    }
};
