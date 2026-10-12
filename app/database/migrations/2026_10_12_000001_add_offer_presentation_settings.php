<?php

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_presentations', function (Blueprint $table) {
            $table->string('template_key', 32)->nullable();
            $table->string('marketing_theme', 32)->nullable();
            $table->string('media_mode', 8)->nullable();
            $table->string('headline_emphasis', 180)->nullable();
            $table->text('final_cta_description')->nullable();
            // Intent for draft/duplicate publication; default_scope remains the active navigation scope.
            $table->string('publish_scope', 16)->default('NONE');
            // Compatibility boundary: existing discovery treatments survive until editorial copy is edited.
            $table->unsignedSmallInteger('editorial_version')->default(0);
        });
        foreach ([Challenge::class, Experience::class, Benefit::class] as $type) {
            DB::table('landing_presentations')->where('subject_type', (new $type)->getMorphClass())->update([
                'template_key' => 'OFFER_CONVERSION',
                'marketing_theme' => $type === Challenge::class ? 'DARK_CONVERSION' : 'LIGHT_CONVERSION',
                'media_mode' => DB::raw("CASE WHEN vsl IS NOT NULL THEN 'VSL' ELSE 'IMAGE' END"),
            ]);
        }
        DB::statement('UPDATE landing_presentations SET publish_scope = default_scope');
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_template_check CHECK (template_key IS NULL OR template_key = 'OFFER_CONVERSION')");
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_theme_check CHECK (marketing_theme IS NULL OR marketing_theme IN ('DARK_CONVERSION', 'LIGHT_CONVERSION'))");
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_media_check CHECK (media_mode IS NULL OR media_mode IN ('IMAGE', 'VSL', 'NONE'))");
        DB::statement("ALTER TABLE landing_presentations ADD CONSTRAINT landing_publish_scope_check CHECK (publish_scope IN ('NONE', 'GUESTS', 'ALL'))");
        DB::statement('ALTER TABLE landing_presentations ADD CONSTRAINT landing_editorial_version_check CHECK (editorial_version IN (0, 1))');
    }

    public function down(): void
    {
        foreach (['landing_template_check', 'landing_theme_check', 'landing_media_check', 'landing_publish_scope_check', 'landing_editorial_version_check'] as $constraint) {
            DB::statement("ALTER TABLE landing_presentations DROP CONSTRAINT {$constraint}");
        }
        Schema::table('landing_presentations', fn (Blueprint $table) => $table->dropColumn(['template_key', 'marketing_theme', 'media_mode', 'headline_emphasis', 'final_cta_description', 'publish_scope', 'editorial_version']));
    }
};
