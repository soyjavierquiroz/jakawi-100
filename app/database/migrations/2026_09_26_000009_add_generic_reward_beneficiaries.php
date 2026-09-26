<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_rules', function (Blueprint $table): void {
            $table->string('beneficiary_type')->nullable()->after('beneficiary_user_id');
            $table->unsignedBigInteger('beneficiary_id')->nullable()->after('beneficiary_type');
            $table->index(['beneficiary_type', 'beneficiary_id']);
        });
        Schema::table('reward_transactions', function (Blueprint $table): void {
            $table->string('beneficiary_type')->nullable()->after('beneficiary_user_id');
            $table->unsignedBigInteger('beneficiary_id')->nullable()->after('beneficiary_type');
            $table->index(['beneficiary_type', 'beneficiary_id']);
        });
        DB::statement('ALTER TABLE reward_transactions ALTER COLUMN beneficiary_user_id DROP NOT NULL');
        Schema::table('reward_payouts', function (Blueprint $table): void {
            $table->string('beneficiary_type')->nullable()->after('beneficiary_user_id');
            $table->unsignedBigInteger('beneficiary_id')->nullable()->after('beneficiary_type');
            $table->index(['beneficiary_type', 'beneficiary_id']);
        });
        DB::statement('ALTER TABLE reward_payouts ALTER COLUMN beneficiary_user_id DROP NOT NULL');
        DB::statement("UPDATE reward_rules SET beneficiary_type = 'USER', beneficiary_id = beneficiary_user_id WHERE beneficiary_user_id IS NOT NULL");
        DB::statement("UPDATE reward_transactions SET beneficiary_type = 'USER', beneficiary_id = beneficiary_user_id WHERE beneficiary_user_id IS NOT NULL");
        DB::statement("UPDATE reward_payouts SET beneficiary_type = 'USER', beneficiary_id = beneficiary_user_id WHERE beneficiary_user_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('reward_payouts', function (Blueprint $table): void { $table->dropIndex(['beneficiary_type', 'beneficiary_id']); $table->dropColumn(['beneficiary_type', 'beneficiary_id']); });
        Schema::table('reward_transactions', function (Blueprint $table): void { $table->dropIndex(['beneficiary_type', 'beneficiary_id']); $table->dropColumn(['beneficiary_type', 'beneficiary_id']); });
        Schema::table('reward_rules', function (Blueprint $table): void { $table->dropIndex(['beneficiary_type', 'beneficiary_id']); $table->dropColumn(['beneficiary_type', 'beneficiary_id']); });
    }
};
