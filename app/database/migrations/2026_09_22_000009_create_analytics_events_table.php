<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visitor_id')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('experience_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('redemption_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');

            $table->index('event_name');
            $table->index('occurred_at');
            $table->index(['user_id', 'occurred_at']);
            $table->index(['visitor_id', 'occurred_at']);
            $table->index('partner_id');
            $table->index('location_id');
            $table->index('benefit_id');
            $table->index('experience_id');
            $table->index('redemption_id');
            $table->index(['event_name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
