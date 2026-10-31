<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A factual observation boundary, never a backfill of historical purchases.
        DB::unprepared(<<<'SQL'
LOCK TABLE users, memberships, membership_bank_payments, membership_purchases, identity_links IN SHARE ROW EXCLUSIVE MODE;
CREATE TABLE membership_evidence_boundary (id integer PRIMARY KEY CHECK (id = 1), observed_from timestamptz NOT NULL);
INSERT INTO membership_evidence_boundary VALUES (1, clock_timestamp());
CREATE TABLE membership_evidence_journal (
    id bigserial PRIMARY KEY,
    entity text NOT NULL,
    row_id bigint NOT NULL,
    operation text NOT NULL,
    observed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    fact jsonb NOT NULL
);
CREATE INDEX membership_evidence_entity_row ON membership_evidence_journal(entity, row_id, id);
CREATE INDEX membership_evidence_fact ON membership_evidence_journal USING gin(fact);
CREATE OR REPLACE FUNCTION jakawi_membership_evidence_fact(entity text, data jsonb) RETURNS jsonb LANGUAGE sql IMMUTABLE AS $$
 SELECT coalesce(jsonb_object_agg(key, value), '{}'::jsonb) FROM jsonb_each(data)
 WHERE key = ANY(CASE entity
 WHEN 'users' THEN ARRAY['id','global_subject_id','created_at']
 WHEN 'memberships' THEN ARRAY['id','user_id','status','starts_at','ends_at','amount_paid','payment_method','payment_reference','created_at']
 WHEN 'membership_bank_payments' THEN ARRAY['id','reference','origin','club_reference','guest_purchase_id','club_user_uuid','user_id','custody_user_id','identity_status','status','amount','membership_id','activated_at','created_at']
 WHEN 'membership_purchases' THEN ARRAY['id','reference','beneficiary_user_id','status','amount','membership_id','paid_at','processed_at','refunded_at','created_at']
 WHEN 'identity_links' THEN ARRAY['id','source_app','club_user_id','global_subject_id','user_id','status','method','authorized_at','revoked_at']
 ELSE ARRAY[]::text[] END);
$$;
CREATE OR REPLACE FUNCTION jakawi_capture_membership_evidence() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE data jsonb; previous jsonb;
BEGIN
 data := jakawi_membership_evidence_fact(TG_TABLE_NAME, CASE WHEN TG_OP = 'DELETE' THEN to_jsonb(OLD) ELSE to_jsonb(NEW) END);
 IF TG_OP = 'UPDATE' THEN
    previous := jakawi_membership_evidence_fact(TG_TABLE_NAME, to_jsonb(OLD));
    IF previous = data THEN RETURN NEW; END IF;
 END IF;
 INSERT INTO membership_evidence_journal(entity,row_id,operation,fact)
 VALUES (TG_TABLE_NAME,(data->>'id')::bigint,TG_OP,data);
 IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION jakawi_preserve_membership_evidence() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 RAISE EXCEPTION 'Membership evidence is append-only' USING ERRCODE = '23514';
END;
$$;
CREATE TRIGGER membership_evidence_permanent BEFORE UPDATE OR DELETE OR TRUNCATE ON membership_evidence_journal
FOR EACH STATEMENT EXECUTE FUNCTION jakawi_preserve_membership_evidence();
CREATE TRIGGER membership_evidence_boundary_permanent BEFORE INSERT OR UPDATE OR DELETE OR TRUNCATE ON membership_evidence_boundary
FOR EACH STATEMENT EXECUTE FUNCTION jakawi_preserve_membership_evidence();
SQL);
        foreach (['users', 'memberships', 'membership_bank_payments', 'membership_purchases', 'identity_links'] as $table) {
            DB::statement("INSERT INTO membership_evidence_journal(entity,row_id,operation,fact) SELECT ?,id,'BASELINE',jakawi_membership_evidence_fact(?,to_jsonb(t)) FROM {$table} t", [$table, $table]);
            DB::unprepared("CREATE TRIGGER {$table}_membership_evidence AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION jakawi_capture_membership_evidence()");
            DB::unprepared("CREATE TRIGGER {$table}_evidence_no_truncate BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION jakawi_preserve_membership_evidence()");
        }
    }

    public function down(): void
    {
        throw new LogicException('Evidence is permanent. Roll back application images without deleting factual history.');
    }
};
