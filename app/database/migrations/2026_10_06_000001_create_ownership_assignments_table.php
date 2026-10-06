<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ownership_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('assigned_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
            $table->index(['target_type', 'target_id', 'assigned_at']);
        });

        DB::statement("ALTER TABLE ownership_assignments ADD CONSTRAINT ownership_assignments_target_type_check CHECK (target_type IN ('USER', 'PARTNER_APPLICATION', 'PROGRAM_APPLICATION'))");
        DB::statement('CREATE UNIQUE INDEX ownership_assignments_one_active_per_target ON ownership_assignments (target_type, target_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_assignments');
    }
};
