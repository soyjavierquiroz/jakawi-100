<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attribution_touches', function (Blueprint $table): void {
            $table->foreignId('unlock_id')->nullable()->after('acquisition_partner_id')->constrained()->nullOnDelete();
            $table->index(['unlock_id', 'referrer_user_id']);
        });
        Schema::table('unlock_participations', function (Blueprint $table): void {
            $table->foreignId('referral_attribution_touch_id')->nullable()->after('user_id')->constrained('attribution_touches')->nullOnDelete();
            $table->index('referral_attribution_touch_id');
        });
    }

    public function down(): void
    {
        Schema::table('unlock_participations', function (Blueprint $table): void {
            $table->dropForeign(['referral_attribution_touch_id']);
            $table->dropIndex(['referral_attribution_touch_id']);
            $table->dropColumn('referral_attribution_touch_id');
        });
        Schema::table('attribution_touches', function (Blueprint $table): void {
            $table->dropForeign(['unlock_id']);
            $table->dropIndex(['unlock_id', 'referrer_user_id']);
            $table->dropColumn('unlock_id');
        });
    }
};
