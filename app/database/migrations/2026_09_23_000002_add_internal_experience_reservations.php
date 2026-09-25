<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experience_sessions', function (Blueprint $table) {
            $table->foreignId('reservation_partner_id')->nullable()->after('location_id')->constrained('partners')->nullOnDelete();
            $table->index('reservation_partner_id');
        });
        Schema::create('experience_reservations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_session_id')->constrained('experience_sessions')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'experience_session_id', 'status']);
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_reservations');
        Schema::table('experience_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_partner_id');
        });
    }
};
