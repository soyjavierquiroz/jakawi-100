<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Support\ExperienceRules;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_experience_persists_editorial_fields_and_defaults(): void
    {
        $experience = Experience::factory()->featured()->create(['regular_price' => '50.00', 'member_price' => '50.00', 'duration_minutes' => null])->fresh();
        $this->assertSame('BOB', $experience->currency);
        $this->assertSame('50.00', $experience->regular_price);
        $this->assertSame('50.00', $experience->member_price);
        $this->assertTrue($experience->featured);
        $this->assertNull($experience->duration_minutes);
    }

    public function test_slug_is_unique_and_publication_scope_excludes_drafts(): void
    {
        $published = Experience::factory()->published()->create(['slug' => 'unique-experience']);
        $draft = Experience::factory()->draft()->create();
        $this->assertSame([$published->id], Experience::published()->pluck('id')->all());
        $this->assertTrue($published->isPublished());
        $this->assertFalse($draft->isPublished());
        $this->expectException(QueryException::class);
        Experience::factory()->create(['slug' => 'unique-experience']);
    }

    public function test_experience_rules_cover_types_prices_and_reservation_destinations(): void
    {
        $valid = Validator::make(['slug' => 'valid-experience', 'title' => 'Valid', 'category' => 'experiences', 'experience_type' => 'tour', 'duration_minutes' => 45, 'regular_price' => '0.00', 'member_price' => '10.50', 'currency' => 'BOB', 'reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170000000', 'status' => 'published'], ExperienceRules::experience());
        $invalid = Validator::make(['slug' => 'Invalid slug', 'title' => '', 'category' => 'invalid', 'experience_type' => 'invalid', 'duration_minutes' => 0, 'regular_price' => '1.234', 'member_price' => '-1', 'currency' => 'bo', 'reservation_method' => 'url', 'status' => 'active'], ExperienceRules::experience());
        $this->assertFalse($valid->fails());
        $this->assertTrue($invalid->fails());
        $this->assertTrue(Validator::make(['slug' => 'none', 'title' => 'None', 'currency' => 'BOB', 'reservation_method' => 'none', 'status' => 'draft'], ExperienceRules::experience())->passes());
        $this->assertTrue(Validator::make(['slug' => 'phone', 'title' => 'Phone', 'currency' => 'BOB', 'reservation_method' => 'phone', 'reservation_phone' => '+59170000000', 'status' => 'draft'], ExperienceRules::experience())->passes());
        $this->assertTrue(Validator::make(['slug' => 'url', 'title' => 'URL', 'currency' => 'BOB', 'reservation_method' => 'url', 'reservation_url' => 'https://example.test', 'status' => 'draft'], ExperienceRules::experience())->passes());
        $this->assertTrue(Validator::make(['slug' => 'external', 'title' => 'External', 'currency' => 'BOB', 'reservation_method' => 'external', 'reservation_url' => 'https://example.test/reserve', 'status' => 'draft'], ExperienceRules::experience())->passes());
        $this->assertTrue(Validator::make(['slug' => 'missing-whatsapp', 'title' => 'Missing WhatsApp', 'currency' => 'BOB', 'reservation_method' => 'whatsapp', 'status' => 'draft'], ExperienceRules::experience())->fails());
    }

    public function test_multi_partner_roles_allow_zero_partners_and_multiple_roles_per_partner(): void
    {
        $experience = Experience::factory()->create();
        $a = Partner::factory()->create(); $b = Partner::factory()->create(); $c = Partner::factory()->create();
        $this->assertCount(0, $experience->partners);
        $experience->syncPartnersWithRoles([
            ['partner_id' => $a->id, 'role' => 'organizer'], ['partner_id' => $a->id, 'role' => 'provider'],
            ['partner_id' => $b->id, 'role' => 'host'], ['partner_id' => $c->id, 'role' => 'venue', 'sort_order' => 2],
        ]);
        $this->assertSame(4, $experience->partners()->count());
        $this->assertSame(['organizer', 'provider'], $a->experiences()->whereKey($experience)->pluck('experience_partner.role')->all());
        $this->expectException(InvalidArgumentException::class);
        $experience->syncPartnersWithRoles([['partner_id' => $a->id, 'role' => 'invalid']]);
    }

    public function test_session_rules_and_upcoming_logic(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $experience = Experience::factory()->published()->create();
        $future = ExperienceSession::factory()->for($experience)->upcoming()->create(['ends_at' => now()->addDays(2), 'capacity' => 20]);
        $past = ExperienceSession::factory()->for($experience)->past()->create();
        $cancelled = ExperienceSession::factory()->for($experience)->cancelled()->create(['starts_at' => now()->addDay()]);
        $this->assertTrue($future->isUpcoming()); $this->assertFalse($past->isUpcoming()); $this->assertFalse($cancelled->isUpcoming());
        $this->assertSame([$future->id], $experience->upcomingSessions()->pluck('id')->all());
        $this->assertSame([$experience->id], Experience::upcoming()->pluck('id')->all());
        $valid = Validator::make(['experience_id' => $experience->id, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'capacity' => null, 'status' => 'scheduled'], ExperienceRules::session());
        $positiveCapacity = Validator::make(['experience_id' => $experience->id, 'starts_at' => now(), 'capacity' => 20, 'status' => 'scheduled'], ExperienceRules::session());
        $invalid = Validator::make(['experience_id' => $experience->id, 'starts_at' => now(), 'ends_at' => now(), 'capacity' => 0, 'status' => 'invalid'], ExperienceRules::session());
        $this->assertTrue($valid->passes()); $this->assertTrue($invalid->fails());
        $this->assertTrue($positiveCapacity->passes());
        Carbon::setTestNow();
    }

    public function test_upcoming_experiences_require_published_status_and_order_sessions(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        $published = Experience::factory()->published()->create();
        $late = ExperienceSession::factory()->for($published)->create(['starts_at' => now()->addDays(2)]);
        $early = ExperienceSession::factory()->for($published)->create(['starts_at' => now()->addDay()]);
        ExperienceSession::factory()->for($published)->create(['starts_at' => now()->subDay()]);
        ExperienceSession::factory()->for($published)->cancelled()->create(['starts_at' => now()->addDay()]);
        $draft = Experience::factory()->draft()->create(); ExperienceSession::factory()->for($draft)->upcoming()->create();
        $this->assertSame([$early->id, $late->id], $published->upcomingSessions()->pluck('id')->all());
        $this->assertSame([$published->id], Experience::upcoming()->pluck('id')->all());
        Carbon::setTestNow();
    }

    public function test_session_location_may_be_partner_owned_independent_or_unrelated_or_null(): void
    {
        $experience = Experience::factory()->create();
        $partner = Partner::factory()->create(); $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        $owned = Location::factory()->withPartner($partner)->create();
        $independent = Location::factory()->withoutPartner()->create();
        $unrelated = Location::factory()->withPartner(Partner::factory()->create())->create();
        $this->assertNotNull(ExperienceSession::factory()->for($experience)->withLocation($owned)->create()->location);
        $this->assertNotNull(ExperienceSession::factory()->for($experience)->withLocation($independent)->create()->location);
        $this->assertNotNull(ExperienceSession::factory()->for($experience)->withLocation($unrelated)->create()->location);
        $this->assertNull(ExperienceSession::factory()->for($experience)->withoutLocation()->create()->location);
    }
}
