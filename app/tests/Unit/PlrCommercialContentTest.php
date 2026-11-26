<?php
namespace Tests\Unit;

use App\Services\PlrCommercialContent;
use Tests\TestCase;

class PlrCommercialContentTest extends TestCase
{
    public function test_reviewed_catalog_contains_unique_plain_text_and_uses_the_current_name(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('content/plr-commercial-content.es.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(112, $catalog);
        $this->assertCount(112, array_unique($catalog));
        $this->assertTrue(collect($catalog)->every(fn ($text) => strip_tags($text) === $text && str_contains($text, '{name}') && substr_count($text, "\n\n") === 2));
        $text = app(PlrCommercialContent::class)->generate(['vendor_id'=>'almoco_fit_es','name'=>'Mi almuerzo']);
        $this->assertStringContainsString('Mi almuerzo', $text);
        $this->assertStringContainsString('pollo con verduras', $text);
    }

    public function test_future_copy_uses_learning_topics_and_excludes_unsupported_source_claims(): void
    {
        $text = app(PlrCommercialContent::class)->generate(['name'=>'Aprende a cocinar','category'=>'cocina-recetas','short_description'=>'Adelgaza 8 kg en 7 días garantizados','description'=>'Cura todas las enfermedades','content_topics'=>['Técnicas de corte','Sopas de verduras','Quemar grasa al instante'],'activity_topics'=>['Ensalada de quinoa']]);
        $this->assertStringContainsString('Técnicas de corte', $text);
        $this->assertStringContainsString('Ensalada de quinoa', $text);
        $this->assertStringNotContainsString('Adelgaza', $text);
        $this->assertStringNotContainsString('Cura', $text);
        $this->assertStringNotContainsString('Quemar grasa', $text);
        $this->assertSame('Texto del propietario', app(PlrCommercialContent::class)->generate(['name'=>'App','commercial_content'=>'Texto del propietario']));
    }
}
