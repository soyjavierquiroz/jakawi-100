<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refuse ambiguous historical identities; never merge or rewrite them.
        if (DB::selectOne('SELECT 1 FROM users GROUP BY lower(btrim(email)) HAVING count(*) > 1 LIMIT 1')) {
            throw new RuntimeException('Identity migration stopped: normalized email conflict requires resolution.');
        }
        DB::statement('CREATE UNIQUE INDEX users_identity_normalized_email_unique ON users (lower(btrim(email)))');
        Schema::create('identity_subjects', function (Blueprint $table): void {
            $table->uuid('global_subject_id')->primary();
            $table->timestampTz('issued_at')->useCurrent();
        });
        // PostgreSQL evaluates the volatile UUID default once per existing row.
        // No other user column, password, role or membership is rewritten.
        DB::statement('ALTER TABLE users ADD COLUMN global_subject_id uuid NOT NULL DEFAULT gen_random_uuid()');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_global_subject_id_unique UNIQUE (global_subject_id)');
        DB::statement('INSERT INTO identity_subjects (global_subject_id) SELECT global_subject_id FROM users');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_global_subject_fk FOREIGN KEY (global_subject_id) REFERENCES identity_subjects(global_subject_id)');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION jakawi_issue_global_subject() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF NEW.global_subject_id IS DISTINCT FROM OLD.global_subject_id THEN
            RAISE EXCEPTION 'global_subject_id is immutable' USING ERRCODE = '23514';
        END IF;
    ELSE
        INSERT INTO identity_subjects (global_subject_id) VALUES (NEW.global_subject_id);
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER users_global_subject_guard BEFORE INSERT OR UPDATE OF global_subject_id ON users
FOR EACH ROW EXECUTE FUNCTION jakawi_issue_global_subject();
CREATE OR REPLACE FUNCTION jakawi_preserve_global_subject() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Issued global subjects cannot be changed or reused' USING ERRCODE = '23514';
END;
$$;
CREATE TRIGGER identity_subjects_permanent BEFORE UPDATE OR DELETE ON identity_subjects
FOR EACH ROW EXECUTE FUNCTION jakawi_preserve_global_subject();
SQL);
        Schema::create('identity_handoffs', function (Blueprint $table): void {
            $table->uuid('handoff_id')->primary();
            $table->string('source_app', 16)->default('club');
            $table->uuid('club_user_id')->index();
            $table->char('payload_hash', 64);
            $table->string('status', 32);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('evidence')->nullable(); // encrypted assertion and verification metadata
            $table->jsonb('result');
            $table->timestampTz('expires_at');
            $table->timestampsTz();
        });
        Schema::create('identity_links', function (Blueprint $table): void {
            $table->id();
            $table->string('source_app', 16)->default('club');
            $table->uuid('club_user_id');
            $table->uuid('global_subject_id');
            $table->foreign('global_subject_id')->references('global_subject_id')->on('identity_subjects');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16);
            $table->string('method', 48);
            $table->uuid('handoff_id');
            $table->foreign('handoff_id')->references('handoff_id')->on('identity_handoffs');
            $table->timestampTz('authorized_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->unique(['source_app', 'club_user_id']);
            $table->unique(['source_app', 'global_subject_id']);
        });
        Schema::create('identity_request_nonces', function (Blueprint $table): void {
            $table->string('key_id', 64);
            $table->uuid('nonce');
            $table->timestampTz('received_at')->useCurrent()->index();
            $table->primary(['key_id', 'nonce']);
        });
        Schema::create('identity_link_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('handoff_id')->nullable()->index();
            $table->uuid('club_user_id')->index();
            $table->string('event', 48);
            $table->timestampTz('occurred_at')->useCurrent();
        });
    }

    public function down(): void
    {
        throw new LogicException('Issued global identities are permanent. Roll back application images without deleting identity data.');
    }
};
