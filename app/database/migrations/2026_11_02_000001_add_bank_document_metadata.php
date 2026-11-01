<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['bank_statement_previews', 'membership_bank_imports'] as $table) {
            Schema::table($table, function (Blueprint $t) { $t->jsonb('document_metadata')->nullable(); });
        }
    }
    public function down(): void { throw new RuntimeException('Permanent financial history: roll back images only.'); }
};
