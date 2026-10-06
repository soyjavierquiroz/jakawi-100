<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('program_type');
            $table->string('status')->default('SUBMITTED');
            $table->string('channel_url')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('attribution_touch_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('attribution_snapshot')->nullable();
            $table->timestampsTz();
            $table->index(['program_type', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX program_applications_one_open ON program_applications (user_id, program_type) WHERE status IN ('SUBMITTED', 'CONTACTED', 'QUALIFIED')");
    }

    public function down(): void
    {
        Schema::dropIfExists('program_applications');
    }
};
