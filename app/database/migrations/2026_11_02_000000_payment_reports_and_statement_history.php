<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::table('membership_bank_payments', function(Blueprint $t) { $t->timestamp('reported_at')->nullable(); });
        DB::statement("DROP INDEX IF EXISTS membership_bank_one_open");
        DB::statement("CREATE UNIQUE INDEX membership_bank_one_open ON membership_bank_payments(user_id) WHERE status IN ('awaiting_payment','PAYMENT_REPORTED','under_review','rejected')");
        Schema::table('membership_bank_imports', function(Blueprint $t) {
            $t->string('bank')->nullable(); $t->string('original_name')->nullable();
            $t->date('period_start')->nullable(); $t->date('period_end')->nullable();
            $t->string('processing_status')->default('legacy'); $t->jsonb('incidents')->nullable();
            $t->unsignedInteger('extracted_count')->default(0); $t->unsignedInteger('duplicate_count')->default(0);
        });
        Schema::table('membership_bank_movements', function(Blueprint $t) {
            $t->string('account_key',100)->nullable(); $t->decimal('debit',12,2)->default(0);
            $t->decimal('credit',12,2)->nullable(); $t->decimal('balance',16,2)->nullable();
            $t->text('original_row')->nullable(); $t->boolean('reconciliation_hold')->default(false);
        });
        Schema::create('bank_statement_previews', function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('admin_id')->constrained('users');
            $t->string('sha256',64); $t->string('path'); $t->string('original_name');
            $t->string('account_key',100); $t->string('bank'); $t->date('period_start'); $t->date('period_end');
            $t->jsonb('rows'); $t->jsonb('incidents'); $t->timestamps();
        });
        Schema::create('bank_statement_entries', function(Blueprint $t) {
            $t->id(); $t->foreignId('import_id')->constrained('membership_bank_imports');
            $t->foreignId('movement_id')->nullable()->constrained('membership_bank_movements');
            $t->unsignedInteger('line'); $t->string('disposition'); $t->text('original_row'); $t->jsonb('data');
            $t->unique(['import_id','line']);
        });
        DB::unprepared("CREATE OR REPLACE FUNCTION preserve_bank_statement_history() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Bank statement history is permanent'; END; \$\$;
            CREATE TRIGGER preserve_bank_imports BEFORE DELETE OR TRUNCATE ON membership_bank_imports FOR EACH STATEMENT EXECUTE FUNCTION preserve_bank_statement_history();
            CREATE TRIGGER preserve_bank_entries BEFORE UPDATE OR DELETE OR TRUNCATE ON bank_statement_entries FOR EACH STATEMENT EXECUTE FUNCTION preserve_bank_statement_history();");
    }
    public function down(): void { throw new RuntimeException('Permanent financial history: roll back images only.'); }
};
