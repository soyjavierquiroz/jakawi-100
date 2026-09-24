<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceReservation;
use App\Models\ExperienceSession;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Redemption;
use App\Models\User;
use App\Services\PartnerKpiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartnerKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_are_scoped_and_follow_value_interaction_definitions(): void
    {
        $now = CarbonImmutable::parse('2026-09-24 12:00:00');
        $a = Partner::factory()->create();
        $b = Partner::factory()->create();
        $benefit = Benefit::factory()->forPartner($a)->create(['title' => 'Promo A']);
        $otherBenefit = Benefit::factory()->forPartner($b)->create(['title' => 'Promo B']);
        $experience = Experience::factory()->create(['title' => 'Experiencia A']);
        $experience->partners()->attach($a, ['role' => 'host', 'sort_order' => 0]);
        $otherExperience = Experience::factory()->create(['title' => 'Experiencia B']);
        $otherExperience->partners()->attach($b, ['role' => 'host', 'sort_order' => 0]);
        $past = ExperienceSession::factory()->for($experience)->create(['starts_at' => $now->subDays(2)]);
        $future = ExperienceSession::factory()->for($experience)->create(['starts_at' => $now->addDay()]);
        $member = User::factory()->create();
        $returning = User::factory()->create();
        $other = User::factory()->create();

        $this->redemption($a, $benefit, $member, $now->subDay(), 20);
        $this->redemption($a, $benefit, $returning, $now->subDay(), 30);
        $this->redemption($a, $benefit, $returning, $now->subDays(40), 10);
        $this->redemption($a, $benefit, $other, $now->subDay(), 99, Redemption::STATUS_PENDING);
        $this->redemption($b, $otherBenefit, $other, $now->subDay(), 500);
        $this->reservation($a, $experience, $past, $member, $now->subDay(), 3, true);
        $this->reservation($a, $experience, $future, $other, $now->subDay(), 4, false);
        $this->reservation($b, $otherExperience, ExperienceSession::factory()->for($otherExperience)->create(['starts_at' => $now->subDay()]), $other, $now->subDay(), 8, true);
        AnalyticsEvent::create(['event_name' => 'benefit_view', 'partner_id' => $a->id, 'benefit_id' => $benefit->id, 'occurred_at' => $now->subDay()]);
        AnalyticsEvent::create(['event_name' => 'experience_view', 'experience_id' => $experience->id, 'occurred_at' => $now->subDay()]);
        AnalyticsEvent::create(['event_name' => 'experience_view', 'experience_id' => $otherExperience->id, 'occurred_at' => $now->subDay()]);

        $kpis = app(PartnerKpiService::class)->forPartner($a, 30, $now);
        $this->assertSame(2, $kpis['value']['members_served']);
        $this->assertSame(1, $kpis['value']['new_members']);
        $this->assertSame(1, $kpis['value']['returning_members']);
        $this->assertSame(50.0, $kpis['value']['return_rate']);
        $this->assertSame(2, $kpis['benefits']['confirmed']);
        $this->assertSame(2, $kpis['benefits']['unique_members']);
        $this->assertSame(50.0, $kpis['benefits']['savings']);
        $this->assertSame(3, $kpis['experiences']['people']);
        $this->assertSame(1, $kpis['experiences']['checkins']);
        $this->assertSame(100.0, $kpis['experiences']['attendance_rate']);
        $this->assertSame('Promo A', $kpis['topBenefits'][0]->title);
        $this->assertSame('Experiencia A', $kpis['topExperiences'][0]->title);
        $this->assertSame(1, $kpis['discovery']['experience_views']);
    }

    public function test_staff_and_unrelated_partner_cannot_open_performance(): void
    {
        $partner = Partner::factory()->create();
        $staff = User::factory()->create();
        $staff->partners()->attach($partner, ['role' => 'staff']);
        $other = Partner::factory()->create();
        $manager = User::factory()->create();
        $manager->partners()->attach($partner, ['role' => 'manager']);
        $this->actingAs($staff)->get('/partner/'.$partner->slug.'/rendimiento')->assertForbidden();
        $this->actingAs($manager)->get('/partner/'.$other->slug.'/rendimiento')->assertForbidden();
    }

    private function redemption(Partner $partner, Benefit $benefit, User $user, CarbonImmutable $at, int $savings, string $status = Redemption::STATUS_CONFIRMED): void
    {
        $membership = Membership::query()->create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => $at, 'ends_at' => $at->addYear(), 'amount_paid' => 0]);
        Redemption::query()->create(['public_id' => (string) Str::ulid(), 'code' => str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT), 'user_id' => $user->id, 'membership_id' => $membership->id, 'partner_id' => $partner->id, 'benefit_id' => $benefit->id, 'partner_name' => $partner->name, 'location_name' => 'QA', 'benefit_title' => $benefit->title, 'status' => $status, 'savings_amount' => $savings, 'expires_at' => $at->addDay(), 'confirmed_at' => $status === Redemption::STATUS_CONFIRMED ? $at : null]);
    }

    private function reservation(Partner $partner, Experience $experience, ExperienceSession $session, User $user, CarbonImmutable $at, int $party, bool $checkedIn): void
    {
        $reservation = ExperienceReservation::query()->create(['user_id' => $user->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id, 'partner_id' => $partner->id, 'status' => 'confirmed', 'party_size' => $party, 'checked_in_at' => $checkedIn ? $at : null]);
        $reservation->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
