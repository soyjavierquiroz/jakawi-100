<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_superadmin')->default(false);
        });
        Schema::create('support_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason', 1000);
            $table->string('session_hash', 64);
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at')->index();
            // Effective end is known even when the browser never makes another request.
            $table->timestampTz('ended_at');
            $table->string('end_reason')->default('expired');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_sessions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_superadmin'));
    }
};
