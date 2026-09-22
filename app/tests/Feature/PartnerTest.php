<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Partner;
use App\Support\PartnerLocationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_organization_and_individual_partners(): void
    {
        $organization = Partner::factory()->create(['entity_type' => 'organization']);
        $individual = Partner::factory()->create(['entity_type' => 'individual', 'partner_type' => 'professional']);

        $this->assertDatabaseHas('partners', ['id' => $organization->id, 'entity_type' => 'organization']);
        $this->assertDatabaseHas('partners', ['id' => $individual->id, 'entity_type' => 'individual']);
    }

    public function test_partner_rules_accept_configured_types_and_reject_unknown_ones(): void
    {
        foreach (config('jakawi.partner_types') as $partnerType) {
            $this->assertFalse(Validator::make([
                'slug' => 'valid-partner', 'entity_type' => 'organization', 'partner_type' => $partnerType,
                'category' => 'food', 'status' => 'draft',
            ], PartnerLocationRules::partner())->fails());
        }

        $validator = Validator::make([
            'slug' => 'valid-partner', 'entity_type' => 'company', 'partner_type' => 'unknown',
            'category' => 'unknown', 'status' => 'active',
        ], PartnerLocationRules::partner());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->hasAny(['entity_type', 'partner_type', 'category', 'status']));
    }

    public function test_published_scope_excludes_drafts(): void
    {
        $published = Partner::factory()->published()->create();
        Partner::factory()->create(['status' => 'draft']);

        $this->assertSame([$published->id], Partner::published()->pluck('id')->all());
        $this->assertTrue($published->isPublished());
    }

    public function test_featured_cast_and_internal_fields_persist_correctly(): void
    {
        $partner = Partner::factory()->create([
            'featured' => true, 'legal_name' => 'Legal Partner S.A.', 'tax_id' => 'TAX-123',
            'contact_name' => 'Internal Contact', 'contact_phone' => '+59170000000',
            'contact_email' => 'contact@example.test', 'internal_notes' => 'Private operating note',
        ])->fresh();

        $this->assertTrue($partner->featured);
        $this->assertSame('Legal Partner S.A.', $partner->legal_name);
        $this->assertSame('Private operating note', $partner->internal_notes);
    }

    public function test_partner_has_many_locations(): void
    {
        $partner = Partner::factory()->create();
        Location::factory()->count(3)->withPartner($partner)->create();

        $this->assertCount(3, $partner->locations);
    }
}
