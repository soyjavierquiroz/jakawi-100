<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomePersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_keeps_the_editorial_home_order(): void
    {
        $featured = $this->benefit('food', true, 10);
        $other = $this->benefit('cafe', false, 0);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', false)
            ->where('featuredBenefits.0.id', $featured->id)
            ->where('featuredBenefits.1.id', $other->id));
    }

    public function test_consumer_without_interests_keeps_the_editorial_home_order(): void
    {
        $user = User::factory()->create();
        $featured = $this->benefit('food', true, 10);
        $other = $this->benefit('cafe', false, 0);

        $this->actingAs($user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', false)
            ->where('featuredBenefits.0.id', $featured->id)
            ->where('featuredBenefits.1.id', $other->id));
    }

    public function test_cafe_and_food_interests_prioritize_matching_benefits(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['interests' => ['cafe', 'food']]);
        $featuredOther = $this->benefit('shopping', true, 0);
        $cafe = $this->benefit('cafe', false, 10);
        $food = $this->benefit('food', false, 20);

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', true)
            ->where('featuredBenefits.0.id', $cafe->id)
            ->where('featuredBenefits.1.id', $food->id)
            ->where('featuredBenefits.2.id', $featuredOther->id));
    }

    public function test_experiences_interest_personalizes_upcoming_experiences_and_keeps_the_nearest_editorial_tie_breaker(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['interests' => ['experiences']]);
        $later = $this->experience('shopping', false, 0, now()->addDays(3));
        $sooner = $this->experience('wellness', false, 0, now()->addDay());

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', true)
            ->where('featuredExperiences.0.id', $sooner->id)
            ->where('featuredExperiences.1.id', $later->id));
    }

    public function test_unavailable_benefits_and_non_upcoming_experiences_never_surface_as_matches(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['interests' => ['cafe']]);
        $available = $this->benefit('food');
        $expired = Benefit::factory()->published()->for(Partner::factory()->published())->create(['category' => 'cafe', 'ends_at' => now()->subDay()]);
        $upcoming = $this->experience('food');
        $past = Experience::factory()->published()->create(['category' => 'cafe']);
        ExperienceSession::factory()->past()->for($past)->create();

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('featuredBenefits.0.id', $available->id)
            ->where('featuredBenefits', fn ($benefits) => $benefits->doesntContain('id', $expired->id))
            ->where('featuredExperiences.0.id', $upcoming->id)
            ->where('featuredExperiences', fn ($experiences) => $experiences->doesntContain('id', $past->id)));
    }

    public function test_partner_only_user_gets_generic_home_even_if_a_profile_exists(): void
    {
        $user = User::factory()->create();
        $user->partners()->attach(Partner::factory()->create(), ['role' => 'manager']);
        $user->profile()->create(['interests' => ['cafe']]);
        $featured = $this->benefit('food', true);
        $cafe = $this->benefit('cafe');

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', false)
            ->where('featuredBenefits.0.id', $featured->id)
            ->where('featuredBenefits.1.id', $cafe->id));
    }

    public function test_member_admin_is_personalized_and_the_order_is_deterministic(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->profile()->create(['interests' => ['cafe']]);
        $first = $this->benefit('cafe', false, 0);
        $second = $this->benefit('cafe', false, 0);

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('isPersonalizedHome', true)
            ->where('featuredBenefits.0.id', $first->id)
            ->where('featuredBenefits.1.id', $second->id));

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('featuredBenefits.0.id', $first->id)
            ->where('featuredBenefits.1.id', $second->id));
    }

    private function benefit(string $category, bool $featured = false, int $sortOrder = 0): Benefit
    {
        return Benefit::factory()->published()->for(Partner::factory()->published())->create([
            'category' => $category,
            'featured' => $featured,
            'sort_order' => $sortOrder,
        ]);
    }

    private function experience(string $category, bool $featured = false, int $sortOrder = 0, mixed $startsAt = null): Experience
    {
        $experience = Experience::factory()->published()->create([
            'category' => $category,
            'featured' => $featured,
            'sort_order' => $sortOrder,
        ]);
        ExperienceSession::factory()->upcoming()->for($experience)->create(['starts_at' => $startsAt ?? now()->addDay()]);

        return $experience;
    }
}
