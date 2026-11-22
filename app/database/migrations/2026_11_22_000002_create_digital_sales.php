<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('digital_product_id')->constrained()->restrictOnDelete();
            $t->foreignId('partner_id')->constrained()->restrictOnDelete();
            $t->string('product_name');
            $t->string('product_slug');
            $t->decimal('public_price', 12, 2);
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3);
            $t->boolean('member_at_purchase');
            $t->foreignId('membership_id')->nullable()->constrained()->restrictOnDelete();
            $t->text('access_terms')->nullable();
            $t->text('support_information')->nullable();
            $t->string('file_path');
            $t->string('file_sha256', 64);
            $t->jsonb('collection_snapshot');
            $t->string('status')->default('pending')->index();
            $t->timestampTz('expires_at');
            $t->timestampTz('confirmed_at')->nullable();
            $t->timestampTz('refunded_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX digital_one_pending_order ON digital_orders(user_id,digital_product_id) WHERE status = 'pending'");
        DB::statement("ALTER TABLE digital_orders ADD CONSTRAINT digital_order_values CHECK (status IN ('pending','confirmed','expired','refunded') AND amount > 0 AND amount <= public_price AND currency IN ('BOB','USD'))");
        Schema::create('digital_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('digital_order_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('under_review')->index();
            $t->string('receipt_path');
            $t->string('receipt_sha256', 64)->unique();
            $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('reviewed_at')->nullable();
            $t->string('bank_account_key', 64)->nullable();
            $t->string('bank_reference')->nullable();
            $t->date('bank_date')->nullable();
            $t->decimal('received_amount', 12, 2)->nullable();
            $t->string('currency', 3)->nullable();
            $t->string('statement_path')->nullable();
            $t->string('statement_sha256', 64)->nullable();
            $t->string('refund_reference')->nullable();
            $t->decimal('refund_amount', 12, 2)->nullable();
            $t->foreignId('refunded_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['bank_account_key', 'bank_reference']);
        });
        DB::statement("CREATE UNIQUE INDEX digital_one_review ON digital_payments(digital_order_id) WHERE status = 'under_review'");
        DB::statement("CREATE UNIQUE INDEX digital_one_confirmed ON digital_payments(digital_order_id) WHERE status IN ('confirmed','refunded')");
        DB::statement("ALTER TABLE digital_payments ADD CONSTRAINT digital_payment_values CHECK (status IN ('under_review','rejected','confirmed','refunded'))");
        Schema::create('digital_access_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('digital_order_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('digital_product_id')->constrained()->restrictOnDelete();
            $t->string('source')->default('purchase');
            $t->string('status')->default('active');
            $t->string('delivery_type')->default('pdf');
            $t->string('delivery_status')->default('pending');
            $t->string('delivery_error')->nullable();
            $t->unsignedInteger('delivery_attempts')->default(0);
            $t->timestampTz('granted_at')->nullable();
            $t->timestampTz('delivered_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_access_grants');
        Schema::dropIfExists('digital_payments');
        Schema::dropIfExists('digital_orders');
    }
};
