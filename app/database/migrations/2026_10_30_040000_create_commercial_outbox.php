<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sidecar only: no changes to the domain, identity or referral tables.
        Schema::create('commercial_conversion_origins', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('redemption_id')->unique();
            $table->jsonb('attribution');
            $table->timestampTz('captured_at');
        });
        Schema::create('commercial_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 128)->unique();
            $table->text('raw_body');
            $table->string('status', 16)->default('PENDING');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestampsTz();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        // Operational rollback keeps provenance and receipts. Never drop populated tables.
        throw new LogicException('Retain commercial provenance and outbox; roll back code only.');
    }
};
