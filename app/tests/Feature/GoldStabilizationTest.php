<?php

namespace Tests\Feature;

use App\Discovery\DiscoveryContext;
use App\Discovery\DiscoveryService;
use App\Models\Benefit;
use App\Models\City;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GoldStabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_rejects_malformed_locations_without_mutating_a_benefit(): void
    {
        $partner = Partner::factory()->create();
        $benefit = Benefit::factory()->forPartner($partner)->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['broken', [['id' => 1]], [null]] as $locations) {
            $this->putJson('/admin/benefits/'.$benefit->slug, [
                ...$benefit->toArray(), 'title' => 'Must not persist',
                'location_scope' => 'selected', 'location_ids' => $locations,
            ])->assertUnprocessable();
            $this->assertSame($benefit->title, $benefit->fresh()->title);
        }
    }

    public function test_signed_redemption_pin_endpoint_limits_attempts(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create();
        $user->partners()->attach($partner, ['role' => 'manager']);
        $this->actingAs($user);
        $url = URL::temporarySignedRoute('partner.redemptions.scan.confirm', now()->addMinutes(10), [
            'partner' => $partner->slug, 'redemption_public_id' => 'missing-redemption',
        ]);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post($url, ['pin' => '000000'])->assertNotFound();
        }
        $this->post($url, ['pin' => '000000'])->assertStatus(429);
    }

    public function test_explorer_landing_queries_are_bounded_and_preserve_viewer_scope(): void
    {
        $city = City::where('slug', 'cochabamba')->sole();
        $partner = Partner::factory()->published()->create();
        Location::factory()->published()->withPartner($partner)->create(['city_id' => $city->id]);
        $benefits = Benefit::factory()->count(8)->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);
        foreach ($benefits as $benefit) {
            foreach (['GUESTS', 'NONE'] as $scope) {
                $benefit->landingPresentations()->create([
                    'name' => $scope, 'slug' => strtolower($scope).'-'.$benefit->id,
                    'status' => 'PUBLISHED', 'default_scope' => $scope,
                ]);
            }
            $benefit->landingPresentations()->create(['name' => 'Draft', 'slug' => 'draft-'.$benefit->id, 'status' => 'DRAFT', 'default_scope' => 'NONE']);
        }
        foreach ([null, User::factory()->create()] as $viewer) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $items = app(DiscoveryService::class)->explore(new DiscoveryContext($city, $viewer));
            $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "landing_presentations"'));
            DB::disableQueryLog();
            $this->assertCount(8, $items);
            $this->assertCount(1, $queries, 'Landing queries must be batched per domain.');
            foreach ($items as $item) {
                $this->assertSame(($viewer ? route('benefits.show', $benefits->find($item->sourceId)) : route('landing-presentations.show', 'guests-'.$item->sourceId)), $item->destinationUrl);
            }
        }
    }
}
