<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('category')->nullable();
            $table->string('experience_type')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('regular_price', 10, 2)->nullable();
            $table->decimal('member_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('BOB');
            $table->string('reservation_method')->nullable();
            $table->string('reservation_url')->nullable();
            $table->string('reservation_whatsapp')->nullable();
            $table->string('reservation_phone')->nullable();
            $table->string('status')->index();
            $table->boolean('featured')->default(false);
            $table->string('image_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
