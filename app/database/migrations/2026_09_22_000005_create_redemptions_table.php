<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 6)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner_name');
            $table->string('location_name');
            $table->string('benefit_title');
            $table->string('status')->index();
            $table->decimal('savings_amount', 10, 2)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('membership_id');
            $table->index('benefit_id');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
