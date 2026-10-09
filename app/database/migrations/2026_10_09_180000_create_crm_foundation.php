<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->string('provider');
            $t->uuid('event_id');
            $t->string('operation');
            $t->string('source_type');
            $t->unsignedBigInteger('source_id');
            $t->string('status')->default('PENDING');
            $t->text('payload');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestampTz('next_attempt_at')->nullable();
            $t->timestampTz('last_attempt_at')->nullable();
            $t->timestampTz('sent_at')->nullable();
            $t->unsignedSmallInteger('last_http_status')->nullable();
            $t->string('last_error_code', 64)->nullable();
            $t->timestampsTz();
            $t->unique(['provider', 'event_id']);
            $t->index(['status', 'next_attempt_at']);
        });
        Schema::create('crm_contact_links', function (Blueprint $t): void {
            $t->id();
            $t->string('provider');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('provider_contact_id');
            $t->string('last_known_email')->nullable();
            $t->timestampTz('linked_at');
            $t->timestampTz('last_synced_at')->nullable();
            $t->timestampsTz();
            $t->unique(['provider', 'user_id']);
            $t->unique(['provider', 'provider_contact_id']);
        });
        foreach (['users', 'partner_applications'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->boolean('marketing_opt_in')->nullable();
                $t->timestampTz('marketing_opt_in_at')->nullable();
                $t->string('marketing_opt_in_source')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'partner_applications'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['marketing_opt_in', 'marketing_opt_in_at', 'marketing_opt_in_source']));
        }
        Schema::dropIfExists('crm_contact_links');
        Schema::dropIfExists('crm_deliveries');
    }
};
