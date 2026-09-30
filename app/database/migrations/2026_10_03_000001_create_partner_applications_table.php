<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visitor_id')->nullable();
            $table->string('business_name');
            $table->string('business_name_normalized');
            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_email_normalized')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_phone_normalized')->nullable();
            $table->string('category')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('SUBMITTED');
            $table->foreignId('attribution_touch_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('attribution_snapshot')->nullable();
            $table->timestampsTz();

            $table->index(['city_id', 'status']);
            $table->index(['city_id', 'business_name_normalized']);
            $table->index(['city_id', 'contact_email_normalized']);
            $table->index(['city_id', 'contact_phone_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
