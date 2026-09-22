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
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('benefit_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('merchant_name');
            $table->string('benefit_title');
            $table->string('status')->index();
            $table->decimal('savings_amount', 10, 2)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'benefit_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['benefit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
