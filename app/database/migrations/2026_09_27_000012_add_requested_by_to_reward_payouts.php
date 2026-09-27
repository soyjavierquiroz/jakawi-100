<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_payouts', function (Blueprint $table): void {
            $table->foreignId('requested_by_user_id')->nullable()->after('beneficiary_id')->constrained('users')->nullOnDelete();
            $table->index(['beneficiary_type', 'beneficiary_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('reward_payouts', function (Blueprint $table): void {
            $table->dropIndex(['beneficiary_type', 'beneficiary_id', 'status']);
            $table->dropConstrainedForeignId('requested_by_user_id');
        });
    }
};
