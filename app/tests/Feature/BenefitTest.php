<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Location;
use App\Models\Partner;
use App\Support\PartnerLocationRules;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Tests\TestCase;

class BenefitTest extends TestCase
{
    use RefreshDatabase;

    public function test_benefit_requires_partner(): void
    {
        $this->expectException(QueryException::class);
        Benefit::query()->create(['slug' => 'missing-partner', 'title' => 'Missing partner', 'status' => 'draft']);
    }

    public function test_benefit_slug_is_unique(): void
    {
        $partner = Partner::factory()->create();
        Benefit::factory()->forPartner($partner)->create(['slug' => 'unique-benefit']);

        $this->expectException(QueryException::class);
        Benefit::factory()->forPartner($partner)->create(['slug' => 'unique-benefit']);
    }

    public function test_benefit_persists_decimal_casts_featured_and_nullable_limit(): void
    {
        $benefit = Benefit::factory()->featured()->unlimited()->create([
            'estimated_savings' => '12.50', 'starts_at' => now(), 'ends_at' => now()->addDay(),
            'applies_to_all_locations' => true,
        ])->fresh();

        $this->assertSame('12.50', $benefit->estimated_savings);
        $this->assertTrue($benefit->featured);
        $this->assertTrue($benefit->applies_to_all_locations);
        $this->assertNull($benefit->redemption_limit_per_member);
        $this->assertInstanceOf(CarbonInterface::class, $benefit->starts_at);
        $this->assertInstanceOf(CarbonInterface::class, $benefit->ends_at);
        $this->assertSame(1, Benefit::factory()->create()->redemption_limit_per_member);
    }

    public function test_benefit_rules_cover_configured_values_and_dates(): void
    {
        $valid = Validator::make([
            'slug' => 'valid-benefit', 'title' => 'Valid benefit', 'category' => 'food',
            'benefit_type' => 'percentage', 'estimated_savings' => '12.50',
            'redemption_limit_per_member' => 2, 'status' => 'published',
            'starts_at' => '2026-09-22 10:00:00', 'ends_at' => '2026-09-22 11:00:00',
            'applies_to_all_locations' => false,
        ], PartnerLocationRules::benefit());
        $invalid = Validator::make([
            'slug' => 'Not valid', 'title' => '', 'category' => 'unknown', 'benefit_type' => 'unknown',
            'estimated_savings' => '1.234', 'redemption_limit_per_member' => 0, 'status' => 'active',
            'starts_at' => '2026-09-22 11:00:00', 'ends_at' => '2026-09-22 10:00:00',
            'applies_to_all_locations' => 'maybe',
        ], PartnerLocationRules::benefit());

        $this->assertFalse($valid->fails());
        $this->assertTrue($invalid->fails());
    }

    public function test_published_scope_excludes_drafts(): void
    {
        $published = Benefit::factory()->published()->create();
        $draft = Benefit::factory()->draft()->create();

        $this->assertSame([$published->id], Benefit::published()->pluck('id')->all());
        $this->assertTrue($published->isPublished());
        $this->assertFalse($draft->isPublished());
    }

    public function test_availability_requires_published_partner_benefit_and_valid_dates(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $publishedPartner = Partner::factory()->published()->create();
        $draftPartner = Partner::factory()->create();
        $pausedPartner = Partner::factory()->create(['status' => 'paused']);
        $available = Benefit::factory()->published()->forPartner($publishedPartner)->create();
        $startsOnly = Benefit::factory()->published()->forPartner($publishedPartner)->create(['starts_at' => now()->subMinute()]);
        $endsOnly = Benefit::factory()->published()->forPartner($publishedPartner)->create(['ends_at' => now()->addMinute()]);
        $notAvailable = collect([
            Benefit::factory()->published()->forPartner($draftPartner)->create(),
            Benefit::factory()->published()->forPartner($pausedPartner)->create(),
            Benefit::factory()->draft()->forPartner($publishedPartner)->create(),
            Benefit::factory()->future()->forPartner($publishedPartner)->create(),
            Benefit::factory()->expired()->forPartner($publishedPartner)->create(),
        ]);

        $this->assertSame([$available->id, $startsOnly->id, $endsOnly->id], Benefit::available()->pluck('id')->all());
        $this->assertTrue($available->isAvailable());
        $this->assertTrue($startsOnly->isAvailable());
        $this->assertTrue($endsOnly->isAvailable());
        $notAvailable->each(fn (Benefit $benefit) => $this->assertFalse($benefit->isAvailable()));
        Carbon::setTestNow();
    }

    public function test_all_locations_are_dynamic_and_only_published_locations_are_returned(): void
    {
        $partner = Partner::factory()->published()->create();
        $a = Location::factory()->published()->withPartner($partner)->create();
        $b = Location::factory()->published()->withPartner($partner)->create();
        Location::factory()->withPartner($partner)->create();
        $benefit = Benefit::factory()->published()->forPartner($partner)->create(['applies_to_all_locations' => true]);

        $this->assertSame([$a->id, $b->id], $benefit->availableLocations()->pluck('id')->all());
        $d = Location::factory()->published()->withPartner($partner)->create();
        $this->assertSame([$a->id, $b->id, $d->id], $benefit->availableLocations()->pluck('id')->all());
    }

    public function test_selected_locations_are_published_same_partner_locations_only(): void
    {
        $partner = Partner::factory()->create();
        $a = Location::factory()->published()->withPartner($partner)->create();
        $b = Location::factory()->published()->withPartner($partner)->create();
        $draft = Location::factory()->withPartner($partner)->create();
        $benefit = Benefit::factory()->forPartner($partner)->create();
        $benefit->syncLocations([$a, $b, $draft->id]);

        $this->assertSame([$a->id, $b->id], $benefit->availableLocations()->pluck('id')->all());
        $this->expectException(InvalidArgumentException::class);
        $benefit->syncLocations([Location::factory()->published()->withPartner(Partner::factory()->create())->create()]);
    }

    public function test_independent_locations_are_rejected_and_switching_to_all_clears_pivots(): void
    {
        $partner = Partner::factory()->create();
        $a = Location::factory()->published()->withPartner($partner)->create();
        $b = Location::factory()->published()->withPartner($partner)->create();
        $benefit = Benefit::factory()->forPartner($partner)->create();
        $benefit->syncLocations([$a, $b]);
        $benefit->applyToAllLocations();

        $this->assertTrue($benefit->applies_to_all_locations);
        $this->assertDatabaseCount('benefit_location', 0);
        $this->assertSame([$a->id, $b->id], $benefit->availableLocations()->pluck('id')->all());
        $benefit->update(['applies_to_all_locations' => false]);
        $this->assertCount(0, $benefit->availableLocations()->get());

        $this->expectException(InvalidArgumentException::class);
        $benefit->syncLocations([Location::factory()->withoutPartner()->create()]);
    }
}
