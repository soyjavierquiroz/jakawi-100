<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Partner;
use App\Support\PartnerLocationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_can_have_three_locations_and_locations_can_be_independent(): void
    {
        $partner = Partner::factory()->create();
        Location::factory()->count(3)->withPartner($partner)->create();
        $independent = Location::factory()->withoutPartner()->create();

        $this->assertCount(3, $partner->locations);
        $this->assertNull($independent->partner_id);
        $this->assertNull($independent->partner);
    }

    public function test_location_contacts_do_not_inherit_partner_contacts(): void
    {
        $partner = Partner::factory()->create(['whatsapp' => '+59171111111', 'phone' => '+59172222222']);
        $location = Location::factory()->withPartner($partner)->create(['whatsapp' => null, 'phone' => null]);

        $this->assertNull($location->whatsapp);
        $this->assertNull($location->phone);
        $this->assertSame('+59171111111', $location->partner->whatsapp);
    }

    public function test_location_persists_address_geo_precision_and_opening_hours(): void
    {
        $hours = ['monday' => [['09:00', '13:00'], ['14:00', '20:00']], 'sunday' => []];
        $location = Location::factory()->create([
            'address' => 'Calle 10 #123', 'zone' => 'Zona Sur', 'city' => 'La Paz',
            'latitude' => '-16.5001234', 'longitude' => '-68.1509876', 'opening_hours' => $hours,
        ])->fresh();

        $this->assertSame('Zona Sur', $location->zone);
        $this->assertSame('-16.5001234', $location->latitude);
        $this->assertSame('-68.1509876', $location->longitude);
        $this->assertSame($hours, $location->opening_hours);
    }

    public function test_location_publication_and_primary_cast_work(): void
    {
        $published = Location::factory()->published()->create(['is_primary' => true]);
        Location::factory()->create(['status' => 'draft']);

        $this->assertSame([$published->id], Location::published()->pluck('id')->all());
        $this->assertTrue($published->isPublished());
        $this->assertTrue($published->is_primary);
    }

    public function test_location_pin_is_hashed_checked_and_hidden(): void
    {
        $location = Location::factory()->create();
        $location->setRedemptionPin('123456');
        $location->save();
        $location->refresh();

        $this->assertTrue($location->hasRedemptionPin());
        $this->assertNotSame('123456', $location->redemption_pin_hash);
        $this->assertTrue($location->checkRedemptionPin('123456'));
        $this->assertFalse($location->checkRedemptionPin('654321'));
        $this->assertArrayNotHasKey('redemption_pin_hash', $location->toArray());
    }

    public function test_location_rules_validate_types_coordinates_and_pin(): void
    {
        $valid = Validator::make([
            'slug' => 'valid-location', 'location_type' => 'venue', 'status' => 'published',
            'latitude' => '-90', 'longitude' => '180', 'redemption_pin' => '123456',
        ], PartnerLocationRules::location());
        $invalid = Validator::make([
            'slug' => 'Not valid', 'location_type' => 'space', 'status' => 'active',
            'latitude' => '90.1', 'longitude' => '-180.1', 'redemption_pin' => '12345a',
        ], PartnerLocationRules::location());

        $this->assertFalse($valid->fails());
        $this->assertTrue($invalid->fails());
        $this->expectException(\InvalidArgumentException::class);
        Location::factory()->make()->setRedemptionPin('12345a');
    }
}
