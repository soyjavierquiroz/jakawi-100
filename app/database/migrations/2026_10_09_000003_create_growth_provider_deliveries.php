<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_provider_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_event_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('channel')->default('SERVER');
            $table->string('status')->default('PENDING');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestampsTz();
            $table->unique(['analytics_event_id', 'provider', 'channel'], 'growth_provider_delivery_unique');
            $table->index(['status', 'next_attempt_at'], 'growth_provider_delivery_due_index');
            $table->index(['provider', 'created_at'], 'growth_provider_delivery_provider_index');
        });
        DB::statement("ALTER TABLE growth_provider_deliveries ADD CONSTRAINT growth_provider_delivery_provider_check CHECK (provider IN ('META', 'TIKTOK', 'GOOGLE'))");
        DB::statement("ALTER TABLE growth_provider_deliveries ADD CONSTRAINT growth_provider_delivery_channel_check CHECK (channel = 'SERVER')");
        DB::statement("ALTER TABLE growth_provider_deliveries ADD CONSTRAINT growth_provider_delivery_status_check CHECK (status IN ('PENDING', 'PROCESSING', 'SENT', 'RETRY', 'DEAD'))");
        DB::statement('ALTER TABLE growth_provider_deliveries ADD CONSTRAINT growth_provider_delivery_attempts_check CHECK (attempts >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_provider_deliveries');
    }
};
