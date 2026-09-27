<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('unlock_participations', function (Blueprint $table): void {
            $table->timestampTz('confirmation_requested_at')->nullable()->after('committed_at');
            $table->timestampTz('confirmed_at')->nullable()->after('confirmation_requested_at');
            $table->timestampTz('fulfilled_at')->nullable()->after('confirmed_at');
            $table->timestampTz('expired_at')->nullable()->after('fulfilled_at');
            $table->timestampTz('no_show_at')->nullable()->after('expired_at');
            $table->foreignId('fulfilled_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('fulfillment_source')->nullable();
            $table->text('fulfillment_note')->nullable();
            $table->foreignId('fulfillment_redemption_id')->nullable()->constrained('redemptions')->nullOnDelete();
            $table->index(['status', 'confirmed_at']);
        });
        Schema::table('reward_transactions', function (Blueprint $table): void {
            $table->foreignId('unlock_participation_id')->nullable()->after('reward_rule_id')->constrained()->nullOnDelete();
            $table->string('source')->nullable()->after('status');
            $table->unique('unlock_participation_id');
        });
        DB::statement('ALTER TABLE reward_transactions ALTER COLUMN conversion_id DROP NOT NULL');
        DB::statement('ALTER TABLE reward_transactions ALTER COLUMN reward_rule_id DROP NOT NULL');
    }
    public function down(): void
    {
        Schema::table('reward_transactions', function (Blueprint $table): void { $table->dropUnique(['unlock_participation_id']); $table->dropConstrainedForeignId('unlock_participation_id'); $table->dropColumn('source'); });
        Schema::table('unlock_participations', function (Blueprint $table): void { $table->dropIndex(['status', 'confirmed_at']); $table->dropConstrainedForeignId('fulfilled_by_user_id'); $table->dropConstrainedForeignId('fulfillment_redemption_id'); $table->dropColumn(['confirmation_requested_at','confirmed_at','fulfilled_at','expired_at','no_show_at','fulfillment_source','fulfillment_note']); });
    }
};
