<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->restrictOnDelete();
            $t->string('slug')->unique();
            $t->string('name');
            $t->text('short_description')->nullable();
            $t->text('description')->nullable();
            $t->text('commercial_content')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('status')->default('draft')->index();
            $t->decimal('public_price', 12, 2)->nullable();
            $t->decimal('member_price', 12, 2)->nullable();
            $t->string('currency', 3)->default('BOB');
            $t->string('delivery_type')->default('pdf');
            $t->string('file_path')->nullable();
            $t->string('file_sha256', 64)->nullable();
            $t->text('support_information')->nullable();
            $t->text('access_terms')->nullable();
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE digital_products ADD CONSTRAINT digital_product_values CHECK (status IN ('draft','published') AND delivery_type = 'pdf' AND currency IN ('BOB','USD') AND (public_price IS NULL OR public_price > 0) AND (member_price IS NULL OR (member_price > 0 AND member_price <= public_price)))");
        $partner = DB::table('partners')->where('slug', 'jakawi-digital')->first();
        if ($partner && $partner->name !== 'JAKAWI Digital') {
            throw new RuntimeException('El slug jakawi-digital pertenece a otro Partner; resolver antes de migrar.');
        }
        if (! $partner && DB::table('partners')->whereRaw('lower(trim(name)) = ?', ['jakawi digital'])->exists()) {
            throw new RuntimeException('Ya existe JAKAWI Digital con otro slug; no se creará un duplicado.');
        }
        if (! $partner) {
            DB::table('partners')->insert(['slug' => 'jakawi-digital', 'name' => 'JAKAWI Digital', 'entity_type' => 'organization', 'partner_type' => 'brand', 'status' => 'published', 'featured' => false, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_products');
        // The real Partner is retained, including any later relationships.
    }
};
