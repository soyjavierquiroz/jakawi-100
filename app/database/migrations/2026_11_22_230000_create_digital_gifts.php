<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('digital_products', function (Blueprint $t) {
            $t->boolean('welcome_gift_enabled')->default(false);
            $t->boolean('promotional_gift_enabled')->default(false);
            $t->string('gift_pdf_sha256', 64)->nullable();
        });
        Schema::table('digital_access_grants', function (Blueprint $t) {
            $t->unsignedBigInteger('digital_order_id')->nullable()->change();
            $t->string('gift_file_path')->nullable();
            $t->string('gift_file_sha256', 64)->nullable();
            $t->string('gift_product_slug')->nullable();
            $t->string('gift_product_name')->nullable();
        });
        Schema::create('digital_gifts', function (Blueprint $t) {
            $t->id();
            $t->string('kind');
            $t->string('status')->default('pending');
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('membership_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('digital_product_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('digital_access_grant_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->uuid('issuance_key')->nullable()->unique();
            $t->string('token_hash', 64)->nullable()->unique();
            $t->timestampTz('expires_at')->nullable();
            $t->timestampTz('granted_at');
            $t->timestampTz('claimed_at')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'kind', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX digital_one_welcome_gift ON digital_gifts(user_id) WHERE kind = 'welcome'");
        DB::statement("ALTER TABLE digital_gifts ADD CONSTRAINT digital_gift_values CHECK (kind IN ('welcome','promotional') AND status IN ('pending','claimed') AND (kind <> 'welcome' OR (user_id IS NOT NULL AND membership_id IS NOT NULL)) AND (kind <> 'promotional' OR (digital_product_id IS NOT NULL AND token_hash IS NOT NULL AND expires_at IS NOT NULL)) AND ((status='pending' AND claimed_at IS NULL AND digital_access_grant_id IS NULL) OR (status='claimed' AND claimed_at IS NOT NULL AND user_id IS NOT NULL AND digital_product_id IS NOT NULL AND digital_access_grant_id IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX digital_one_gift_access ON digital_access_grants(user_id,digital_product_id) WHERE source = 'gift' AND status = 'active'");
        DB::statement("ALTER TABLE digital_access_grants ADD CONSTRAINT digital_access_source CHECK ((source = 'purchase' AND digital_order_id IS NOT NULL) OR (source = 'gift' AND digital_order_id IS NULL AND gift_file_path IS NOT NULL AND gift_file_sha256 IS NOT NULL AND gift_product_slug IS NOT NULL AND gift_product_name IS NOT NULL))");
    }

    public function down(): void
    {
        // Never discard claimed rights or restore NOT NULL over gift grants.
        if (DB::table('digital_gifts')->exists() || DB::table('digital_access_grants')->where('source', 'gift')->exists()) {
            throw new RuntimeException('Existen derechos de regalo. Conservar el esquema aditivo al restaurar el runtime.');
        }
        Schema::drop('digital_gifts');
        DB::statement('ALTER TABLE digital_access_grants DROP CONSTRAINT digital_access_source');
        DB::statement('DROP INDEX digital_one_gift_access');
        Schema::table('digital_access_grants', function (Blueprint $t) {
            $t->dropColumn(['gift_file_path','gift_file_sha256','gift_product_slug','gift_product_name']);
            $t->unsignedBigInteger('digital_order_id')->nullable(false)->change();
        });
        Schema::table('digital_products', fn (Blueprint $t) => $t->dropColumn(['welcome_gift_enabled','promotional_gift_enabled','gift_pdf_sha256']));
    }
};
