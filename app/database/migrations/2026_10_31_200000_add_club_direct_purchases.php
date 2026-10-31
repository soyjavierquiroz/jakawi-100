<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_bank_payments', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->change();
            $t->string('origin', 8)->default('COM');
            $t->uuid('club_reference')->nullable()->unique();
            $t->uuid('club_user_uuid')->nullable()->index();
            $t->uuid('guest_purchase_id')->nullable();
            $t->string('buyer_name')->nullable();
            $t->string('buyer_email')->nullable();
            $t->string('buyer_phone', 40)->nullable();
            $t->string('intent_hash', 64)->nullable();
            $t->string('claim_hash', 64)->nullable();
            $t->string('identity_status')->nullable();
            $t->foreignId('custody_user_id')->nullable()->constrained('users');
        });
        Schema::create('club_purchase_nonces', function (Blueprint $t) {
            $t->id();
            $t->uuid('nonce')->unique();
            $t->timestamp('received_at');
        });
    }

    public function down(): void
    {
        throw new LogicException('Purchase ownership and custody are permanent. Roll back application images without deleting purchase data.');
    }
};
