<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('attribution_touch_id')->nullable()->constrained('attribution_touches')->nullOnDelete();
            $table->string('journey_type')->nullable();
            $table->string('journey_action')->nullable();
            $table->unsignedBigInteger('journey_resource_id')->nullable();
            $table->jsonb('journey_context')->nullable();
            $table->foreignId('membership_purchase_id')->nullable()->unique()->constrained('membership_purchases')->nullOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'requested_at']);
            $table->index(['user_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX membership_purchase_requests_one_requested_per_user ON membership_purchase_requests (user_id) WHERE status = 'REQUESTED'");
        DB::statement("ALTER TABLE membership_purchase_requests ADD CONSTRAINT membership_purchase_requests_status_check CHECK (status IN ('REQUESTED', 'COMPLETED', 'CANCELLED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_purchase_requests');
    }
};
