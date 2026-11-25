<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('digital_products', fn (Blueprint $t) => $t->string('app_key', 80)->nullable());
        DB::statement('ALTER TABLE digital_products DROP CONSTRAINT digital_product_values');
        DB::statement("ALTER TABLE digital_products ADD CONSTRAINT digital_product_values CHECK (status IN ('draft','published') AND delivery_type IN ('pdf','hosted_app') AND (delivery_type <> 'hosted_app' OR app_key IS NOT NULL) AND currency IN ('BOB','USD') AND (public_price IS NULL OR public_price > 0) AND (member_price IS NULL OR (member_price > 0 AND member_price <= public_price)))");
        DB::statement('ALTER TABLE digital_orders ALTER COLUMN file_path DROP NOT NULL, ALTER COLUMN file_sha256 DROP NOT NULL');
        DB::statement('ALTER TABLE digital_access_grants DROP CONSTRAINT digital_access_source');
        DB::statement("ALTER TABLE digital_access_grants ADD CONSTRAINT digital_access_source CHECK ((source = 'purchase' AND digital_order_id IS NOT NULL) OR (source = 'gift' AND digital_order_id IS NULL AND (delivery_type = 'hosted_app' OR (gift_file_path IS NOT NULL AND gift_file_sha256 IS NOT NULL)) AND gift_product_slug IS NOT NULL AND gift_product_name IS NOT NULL))");
    }
    public function down(): void { throw new RuntimeException('Forward-only: restore delivery configuration, preserve purchased access and archived files.'); }
};
