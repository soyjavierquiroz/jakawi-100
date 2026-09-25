<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experience_reservations', function (Blueprint $table) {
            $table->string('check_in_code', 6)->nullable()->unique()->after('public_id');
            $table->timestamp('checked_in_at')->nullable()->after('cancelled_at');
            $table->foreignId('checked_in_by_user_id')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            $table->index(['partner_id', 'check_in_code']);
        });
    }

    public function down(): void
    {
        Schema::table('experience_reservations', function (Blueprint $table) {
            $table->dropIndex(['partner_id', 'check_in_code']);
            $table->dropConstrainedForeignId('checked_in_by_user_id');
            $table->dropColumn(['check_in_code', 'checked_in_at']);
        });
    }
};
