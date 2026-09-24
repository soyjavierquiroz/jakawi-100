<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['benefits', 'experiences'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('review_status')->default('draft')->index();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
            DB::table($name)->where('status', 'published')->update(['review_status' => 'approved']);
        }
    }

    public function down(): void
    {
        foreach (['benefits', 'experiences'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by_user_id');
                $table->dropConstrainedForeignId('submitted_by_user_id');
                $table->dropConstrainedForeignId('reviewed_by_user_id');
                $table->dropColumn(['review_status', 'submitted_at', 'reviewed_at', 'review_notes']);
            });
        }
    }
};
