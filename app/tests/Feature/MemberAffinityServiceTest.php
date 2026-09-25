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
use App\Services\MemberAffinityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberAffinityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_interest_remains_stronger_than_capped_behavior(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['interests' => ['food']]);
        $this->redemption($user, 'wellness');
        $this->redemption($user, 'wellness');

        $scores = app(MemberAffinityService::class)->combinedCategoryAffinity($user);

        $this->assertSame(100, $scores['food']);
        $this->assertSame(80, $scores['wellness']);
    }

    public function test_confirmed_redemptions_are_capped_and_old_activity_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->redemption($user, 'wellness');
        $this->redemption($user, 'wellness');
        $this->redemption($user, 'wellness');
        $this->redemption($user, 'food', now()->subDays(91));

        $scores = app(MemberAffinityService::class)->behavioralCategoryAffinity($user);

        $this->assertSame(80, $scores['wellness']);
        $this->assertArrayNotHasKey('food', $scores);
    }

    public function test_checkins_are_strongest_and_not_double_counted_as_confirmed_reservations(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, 'entertainment', checkedInAt: now()->subDay(), respondedAt: now()->subDays(2));
        $this->reservation($user, 'wellness', checkedInAt: now()->subDays(91), respondedAt: now()->subDays(91));

        $scores = app(MemberAffinityService::class)->behavioralCategoryAffinity($user);

        $this->assertSame(50, $scores['entertainment']);
        $this->assertArrayNotHasKey('wellness', $scores);
    }

    public function test_confirmed_reservations_and_recent_views_have_their_own_caps(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, 'wellness', checkedInAt: null, respondedAt: now()->subDay());
        $this->reservation($user, 'wellness', checkedInAt: null, respondedAt: now()->subDays(2));
        $benefit = $this->benefit('cafe');
        foreach (range(1, 5) as $ignored) {
            AnalyticsEvent::create(['event_name' => 'benefit_view', 'user_id' => $user->id, 'benefit_id' => $benefit->id, 'occurred_at' => now()->subDay()]);
        }
        AnalyticsEvent::create(['event_name' => 'benefit_view', 'user_id' => $user->id, 'benefit_id' => $benefit->id, 'occurred_at' => now()->subDays(31)]);

        $scores = app(MemberAffinityService::class)->behavioralCategoryAffinity($user);

        $this->assertSame(50, $scores['wellness']);
        $this->assertSame(20, $scores['cafe']);
    }

    public function test_behavior_only_member_gets_ranked_home_without_creating_a_profile(): void
    {
        $user = User::factory()->create();
        $this->redemption($user, 'wellness');
        $this->benefit('food', true);
        $this->benefit('wellness');

        $this->actingAs($user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', true)
            ->where('personalizationSubtitle', 'Según tu actividad.')
            ->where('featuredBenefits.0.category', 'wellness'));

        $this->assertNull($user->fresh()->profile);
    }

    public function test_partner_only_users_do_not_receive_consumer_behavioral_affinity(): void
    {
        $user = User::factory()->create();
        $user->partners()->attach(Partner::factory()->create(), ['role' => 'manager']);
        $benefit = $this->benefit('wellness');
        AnalyticsEvent::create(['event_name' => 'benefit_view', 'user_id' => $user->id, 'benefit_id' => $benefit->id, 'occurred_at' => now()->subDay()]);

        $this->assertSame([], app(MemberAffinityService::class)->combinedCategoryAffinity($user));
    }

    private function redemption(User $user, string $category, mixed $confirmedAt = null): Redemption
    {
        $benefit = $this->benefit($category);
        $membership = Membership::create(['user_id' => $user->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'amount_paid' => 0]);

        return Redemption::create([
            'public_id' => (string) Str::ulid(), 'code' => str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => $user->id, 'membership_id' => $membership->id, 'partner_id' => $benefit->partner_id,
            'benefit_id' => $benefit->id, 'partner_name' => $benefit->partner->name, 'location_name' => 'QA',
            'benefit_title' => $benefit->title, 'status' => Redemption::STATUS_CONFIRMED, 'savings_amount' => 1,
            'expires_at' => now()->addDay(), 'confirmed_at' => $confirmedAt ?? now()->subDay(),
        ]);
    }

    private function reservation(User $user, string $category, mixed $checkedInAt, mixed $respondedAt): ExperienceReservation
    {
        $partner = Partner::factory()->published()->create();
        $experience = Experience::factory()->published()->create(['category' => $category]);
        $session = ExperienceSession::factory()->for($experience)->create(['reservation_partner_id' => $partner->id]);

        return ExperienceReservation::create([
            'user_id' => $user->id, 'experience_id' => $experience->id, 'experience_session_id' => $session->id,
            'partner_id' => $partner->id, 'status' => ExperienceReservation::STATUS_CONFIRMED, 'party_size' => 1,
            'checked_in_at' => $checkedInAt, 'responded_at' => $respondedAt,
        ]);
    }

    private function benefit(string $category, bool $featured = false): Benefit
    {
        return Benefit::factory()->published()->for(Partner::factory()->published())->create(['category' => $category, 'featured' => $featured]);
    }
}
