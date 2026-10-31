<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_acquisition_origins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->jsonb('attribution');
            $table->timestampTz('fixed_at');
        });
        foreach (['membership_bank_payments'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignUuid('acquisition_origin_id')->nullable()->constrained('membership_acquisition_origins')->restrictOnDelete();
            });
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_membership_acquisition_origin() RETURNS trigger AS $$
BEGIN
 IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Membership acquisition history cannot be deleted'; END IF;
 IF NEW.id IS DISTINCT FROM OLD.id OR NEW.attribution IS DISTINCT FROM OLD.attribution OR NEW.fixed_at IS DISTINCT FROM OLD.fixed_at
    OR (OLD.user_id IS NOT NULL AND NEW.user_id IS DISTINCT FROM OLD.user_id) THEN
  RAISE EXCEPTION 'Membership acquisition origin is immutable';
 END IF;
 RETURN NEW;
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER membership_acquisition_origin_immutable BEFORE UPDATE OR DELETE ON membership_acquisition_origins
FOR EACH ROW EXECUTE FUNCTION protect_membership_acquisition_origin();
CREATE OR REPLACE FUNCTION protect_purchase_acquisition_origin() RETURNS trigger AS $$
BEGIN
 IF OLD.acquisition_origin_id IS NOT NULL AND NEW.acquisition_origin_id IS DISTINCT FROM OLD.acquisition_origin_id THEN
  RAISE EXCEPTION 'Purchase acquisition origin is immutable';
 END IF;
 RETURN NEW;
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER bank_purchase_acquisition_immutable BEFORE UPDATE ON membership_bank_payments
FOR EACH ROW EXECUTE FUNCTION protect_purchase_acquisition_origin();
SQL);
    }

    public function down(): void
    {
        foreach (['membership_bank_payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('acquisition_origin_id'));
        }
        Schema::dropIfExists('membership_acquisition_origins');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_membership_acquisition_origin() CASCADE; DROP FUNCTION IF EXISTS protect_purchase_acquisition_origin() CASCADE;');
    }
};
