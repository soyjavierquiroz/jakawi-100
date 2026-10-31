<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_bank_payments', function (Blueprint $t) {
            $t->string('receipt_disk')->nullable();
            $t->string('receipt_sha256', 64)->nullable();
            $t->uuid('extraction_version')->nullable();
            $t->string('extraction_status')->nullable();
            $t->jsonb('extraction_json')->nullable();
            $t->string('extraction_error')->nullable();
            $t->unsignedInteger('extraction_attempts')->default(0);
            $t->timestamp('extraction_claimed_at')->nullable();
            $t->timestamp('extraction_processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('membership_bank_payments', fn (Blueprint $t) => $t->dropColumn(['receipt_disk', 'receipt_sha256', 'extraction_version', 'extraction_status', 'extraction_json', 'extraction_error', 'extraction_attempts', 'extraction_claimed_at', 'extraction_processed_at']));
    }
};
