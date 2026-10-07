<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operational_adjustments', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->unique();
        });

        Schema::table('reward_transactions', function (Blueprint $table): void {
            $table->foreignId('operational_adjustment_id')->nullable()->unique()->constrained('operational_adjustments')->restrictOnDelete();
            $table->foreignId('reversal_of_reward_transaction_id')->nullable()->unique()->constrained('reward_transactions')->restrictOnDelete();
            $table->foreignId('forfeited_jp_hold_id')->nullable()->unique()->constrained('jp_holds')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reward_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operational_adjustment_id');
            $table->dropConstrainedForeignId('reversal_of_reward_transaction_id');
            $table->dropConstrainedForeignId('forfeited_jp_hold_id');
        });
        Schema::table('operational_adjustments', fn (Blueprint $table) => $table->dropColumn('idempotency_key'));
    }
};
