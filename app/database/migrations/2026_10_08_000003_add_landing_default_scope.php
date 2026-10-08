<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE landing_presentations ADD COLUMN default_scope varchar(16) NOT NULL DEFAULT 'NONE'");
        DB::statement("UPDATE landing_presentations SET default_scope = 'ALL' WHERE is_default = true");
        DB::statement('DROP INDEX landing_presentations_one_default');
        DB::statement('ALTER TABLE landing_presentations DROP CONSTRAINT landing_presentations_default_published_check');
        DB::statement('ALTER TABLE landing_presentations DROP COLUMN is_default');
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_presentations_scope_check CHECK (default_scope IN ('NONE', 'GUESTS', 'ALL'))");
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_presentations_default_published_check CHECK (default_scope = 'NONE' OR status = 'PUBLISHED')");
        DB::statement("CREATE UNIQUE INDEX landing_presentations_one_default ON landing_presentations (subject_type, subject_id) WHERE status = 'PUBLISHED' AND default_scope <> 'NONE'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX landing_presentations_one_default');
        DB::statement('ALTER TABLE landing_presentations DROP CONSTRAINT landing_presentations_default_published_check');
        DB::statement('ALTER TABLE landing_presentations DROP CONSTRAINT landing_presentations_scope_check');
        DB::statement('ALTER TABLE landing_presentations ADD COLUMN is_default boolean NOT NULL DEFAULT false');
        DB::statement("UPDATE landing_presentations SET is_default = true WHERE default_scope <> 'NONE'");
        DB::statement('ALTER TABLE landing_presentations DROP COLUMN default_scope');
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_presentations_default_published_check CHECK (NOT is_default OR status = 'PUBLISHED')");
        DB::statement('CREATE UNIQUE INDEX landing_presentations_one_default ON landing_presentations (subject_type, subject_id) WHERE is_default = true');
    }
};
