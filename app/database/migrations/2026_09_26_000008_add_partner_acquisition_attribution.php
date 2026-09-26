<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            $table->string('referral_code')->nullable()->unique()->after('slug');
            $table->string('referral_code_normalized')->nullable()->unique()->after('referral_code');
        });
        Schema::table('attribution_touches', function (Blueprint $table): void {
            $table->foreignId('acquisition_partner_id')->nullable()->after('referrer_user_id')->constrained('partners')->nullOnDelete();
            $table->index(['acquisition_partner_id', 'occurred_at']);
        });
        Schema::table('referral_relationships', function (Blueprint $table): void {
            $table->foreignId('referrer_user_id')->nullable()->change();
            $table->foreignId('acquisition_partner_id')->nullable()->after('referrer_user_id')->constrained('partners')->nullOnDelete();
            $table->index(['acquisition_partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('referral_relationships', function (Blueprint $table): void { $table->dropConstrainedForeignId('acquisition_partner_id'); });
        Schema::table('attribution_touches', function (Blueprint $table): void { $table->dropConstrainedForeignId('acquisition_partner_id'); });
        Schema::table('partners', function (Blueprint $table): void { $table->dropUnique(['referral_code']); $table->dropUnique(['referral_code_normalized']); $table->dropColumn(['referral_code', 'referral_code_normalized']); });
    }
};
