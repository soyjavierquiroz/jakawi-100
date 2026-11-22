<?php
namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\LandingPresentation;
use App\Models\Partner;
use App\Services\MediaReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaReferenceCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_digital_gallery_and_nested_landing_images_remain_referenced(): void
    {
        $gallery = 'digital-products/1/image/550e8400-e29b-41d4-a716-446655440000.webp';
        $editorial = 'landings/1/image/550e8400-e29b-41d4-a716-446655440001.webp';
        DigitalProduct::create(['partner_id'=>Partner::where('slug','jakawi-digital')->value('id'), 'name'=>'QA','slug'=>'qa-reference','status'=>'draft','currency'=>'BOB','gallery_paths'=>[$gallery]]);
        LandingPresentation::create(['name'=>'QA reference','subject_type'=>DigitalProduct::class,'subject_id'=>1,'slug'=>'qa-reference','status'=>'DRAFT','editorial_blocks'=>[['items'=>[['image_path'=>$editorial]]]]]);
        $service = app(MediaReferences::class);
        foreach ([$gallery, $editorial] as $key) {
            $this->assertArrayHasKey($key, $service->all());
            $this->assertTrue($service->contains($key));
        }
        $this->assertFalse($service->contains('landings/1/image/550e8400-e29b-41d4-a716-446655440002.webp'));
    }
}
