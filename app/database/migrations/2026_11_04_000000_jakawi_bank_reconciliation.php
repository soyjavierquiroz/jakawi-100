<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('jakawi_bank_reconciliations', function(Blueprint $t) {
   $t->foreignId('movement_id')->primary()->constrained('membership_bank_movements');
   $t->string('status')->default('unidentified')->index();
   $t->foreignId('payment_id')->nullable()->constrained('membership_bank_payments');
   $t->string('reason'); $t->jsonb('evidence'); $t->timestamps();
  });
 }
 public function down(): void { throw new RuntimeException('Preserve reconciliation audit; roll back images only.'); }
};
