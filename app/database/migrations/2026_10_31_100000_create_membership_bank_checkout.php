<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_bank_imports', function (Blueprint $t) {
            $t->id();
            $t->string('sha256', 64)->unique();
            $t->string('account_key', 100);
            $t->string('path');
            $t->foreignId('admin_id')->constrained('users');
            $t->timestamps();
        });
        Schema::create('membership_bank_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_id')->constrained('membership_bank_imports');
            $t->string('fingerprint', 64)->unique();
            $t->date('paid_date');
            $t->time('paid_time')->nullable();
            $t->string('reference')->nullable()->index();
            $t->text('description')->nullable();
            $t->decimal('amount', 12, 2);
            $t->timestamps();
        });
        Schema::create('membership_bank_payments', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->foreignId('user_id')->constrained('users');
            $t->string('status')->default('awaiting_payment')->index();
            $t->decimal('amount', 12, 2)->default(100);
            $t->uuid('checkout_event_id')->unique();
            $t->timestamp('checkout_at');
            $t->string('receipt_path')->nullable();
            $t->string('receipt_mime')->nullable();
            $t->string('declared_reference')->nullable()->index();
            $t->date('paid_date')->nullable();
            $t->time('paid_time')->nullable();
            $t->string('origin_bank')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('bank_movement_id')->nullable()->unique()->constrained('membership_bank_movements');
            $t->foreignId('membership_id')->nullable()->unique()->constrained('memberships');
            $t->timestamp('activated_at')->nullable();
            $t->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX membership_bank_one_open ON membership_bank_payments(user_id) WHERE status IN ('awaiting_payment','under_review','rejected')");
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_bank_payments');
        Schema::dropIfExists('membership_bank_movements');
        Schema::dropIfExists('membership_bank_imports');
    }
};
