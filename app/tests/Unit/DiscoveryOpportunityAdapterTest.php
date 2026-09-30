<?php

namespace Tests\Unit;

use App\Discovery\BenefitOpportunityAdapter;
use App\Discovery\DiscoveryCity;
use App\Discovery\DiscoveryOpportunity;
use App\Discovery\ExperienceOpportunityAdapter;
use App\Discovery\OpportunityType;
use App\Discovery\UnlockOpportunityAdapter;
use App\Discovery\UnlockProgress;
use App\Models\Benefit;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use App\Services\MediaUrl;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class DiscoveryOpportunityAdapterTest extends TestCase
{
    public function test_benefit_adapter_maps_public_read_fields_without_economic_or_redemption_logic(): void
    {
        $benefit = $this->model(Benefit::class, [
            'id' => 41, 'slug' => 'cafe', 'title' => 'Café de especialidad', 'short_description' => '20% de descuento',
            'category' => 'food', 'benefit_type' => 'percentage', 'estimated_savings' => '30.00', 'status' => 'published',
            'featured' => true, 'sort_order' => 4, 'image_path' => 'benefits/cafe.jpg',
            'starts_at' => CarbonImmutable::parse('2026-09-01T10:00:00+00:00'), 'ends_at' => CarbonImmutable::parse('2026-10-31T10:00:00+00:00'),
        ]);
        $partner = $this->model(Partner::class, ['id' => 9, 'name' => 'Casa Café', 'slug' => 'casa-cafe']);
        $location = $this->model(Location::class, ['id' => 12, 'name' => 'Cala Cala', 'zone' => 'Norte']);

        $opportunity = app(BenefitOpportunityAdapter::class)->adapt($benefit, $this->city(), $partner, $location);

        $this->assertInstanceOf(DiscoveryOpportunity::class, $opportunity);
        $this->assertSame(OpportunityType::BENEFIT, $opportunity->type);
        $this->assertSame(41, $opportunity->sourceId);
        $this->assertSame(route('benefits.show', $benefit), $opportunity->destinationUrl);
        $this->assertSame(['id' => 9, 'name' => 'Casa Café', 'slug' => 'casa-cafe'], $opportunity->partner?->toArray());
        $this->assertSame(['food'], $opportunity->categories);
        $this->assertSame(app(MediaUrl::class)->url('benefits/cafe.jpg', 'benefit_card'), $opportunity->image);
        $this->assertSame('2026-09-01T10:00:00+00:00', $opportunity->startsAt?->format(DATE_ATOM));
        $this->assertSame('2026-10-31T10:00:00+00:00', $opportunity->endsAt?->format(DATE_ATOM));
        $this->assertSame('20% de descuento', $opportunity->primaryValue);
        $this->assertNotSame('Bs 30.00 ahorrados', $opportunity->primaryValue);
        $this->assertSame(999996.0, $opportunity->editorialPriority);
        $this->assertTrue($opportunity->availability?->isAvailable);
    }

    public function test_experience_adapter_uses_only_the_explicit_selected_city_session_and_location(): void
    {
        $experience = $this->model(Experience::class, [
            'id' => 44, 'slug' => 'ceramica', 'title' => 'Taller de cerámica', 'category' => 'culture',
            'experience_type' => 'workshop', 'reservation_method' => 'whatsapp', 'status' => 'published',
            'featured' => false, 'sort_order' => 2, 'image_path' => 'experiences/ceramica.jpg',
        ]);
        $location = $this->model(Location::class, ['id' => 13, 'city_id' => 1, 'name' => 'Casa Taller', 'zone' => 'Centro']);
        $session = $this->model(ExperienceSession::class, [
            'id' => 99, 'location_id' => 13, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2),
            'status' => 'scheduled', 'venue_label' => 'Otra ciudad',
        ]);
        $partner = $this->model(Partner::class, ['id' => 7, 'name' => 'Taller', 'slug' => 'taller']);

        $opportunity = app(ExperienceOpportunityAdapter::class)->adapt($experience, $this->city(), $session, $location, $partner);

        $this->assertInstanceOf(DiscoveryOpportunity::class, $opportunity);
        $this->assertSame(OpportunityType::EXPERIENCE, $opportunity->type);
        $this->assertSame(44, $opportunity->sourceId);
        $this->assertSame(route('experiences.show', $experience), $opportunity->destinationUrl);
        $this->assertSame(99, $session->getKey());
        $this->assertSame(['id' => 13, 'name' => 'Casa Taller', 'label' => 'Centro'], $opportunity->location?->toArray());
        $this->assertSame(['culture'], $opportunity->categories);
        $this->assertSame(app(MediaUrl::class)->url('experiences/ceramica.jpg', 'experience_card'), $opportunity->image);
        $this->assertSame($session->starts_at?->format(DATE_ATOM), $opportunity->startsAt?->format(DATE_ATOM));
        $this->assertNull($opportunity->primaryValue);
        $this->assertSame('workshop', $opportunity->secondaryValue);
        $this->assertSame(-2.0, $opportunity->editorialPriority);
    }

    public function test_experience_adapter_rejects_a_wrong_city_session_without_using_venue_label_for_geography(): void
    {
        $experience = $this->model(Experience::class, ['id' => 44, 'slug' => 'ceramica', 'title' => 'Cerámica']);
        $location = $this->model(Location::class, ['id' => 13, 'city_id' => 2, 'name' => 'Casa Taller']);
        $session = $this->model(ExperienceSession::class, ['location_id' => 13, 'starts_at' => now()->addDay(), 'status' => 'scheduled', 'venue_label' => 'Cochabamba']);

        $this->expectException(InvalidArgumentException::class);

        app(ExperienceOpportunityAdapter::class)->adapt($experience, $this->city(), $session, $location);
    }

    public function test_unlock_adapter_uses_supplied_progress_and_hides_secret_partner_without_committed_count_queries(): void
    {
        $unlock = new class extends Unlock
        {
            public function committedCount(): int
            {
                throw new LogicException('The adapter must receive precomputed progress.');
            }
        };
        $unlock->setRawAttributes([
            'id' => 77, 'slug' => 'cena', 'title' => 'Cena especial', 'short_description' => 'Hagámoslo posible',
            'hero_path' => 'unlocks/cena.jpg', 'minimum_commitments' => 8, 'status' => Unlock::ACTIVE,
            'featured' => true, 'secret_mode' => true, 'hide_partner_until_unlock' => true, 'hide_location_until_unlock' => true,
            'commitment_deadline' => CarbonImmutable::parse('2026-10-15T10:00:00+00:00'),
        ], true);
        $partner = $this->model(Partner::class, ['id' => 9, 'name' => 'Secreto', 'slug' => 'secreto']);
        $location = $this->model(Location::class, ['id' => 12, 'name' => 'Secreto', 'zone' => 'Norte']);
        DB::enableQueryLog();

        $opportunity = app(UnlockOpportunityAdapter::class)->adapt($unlock, $this->city(), new UnlockProgress(8, 1), $partner, $location);

        $this->assertInstanceOf(DiscoveryOpportunity::class, $opportunity);
        $this->assertSame(OpportunityType::UNLOCK, $opportunity->type);
        $this->assertSame(77, $opportunity->sourceId);
        $this->assertSame(route('unlocks.show', $unlock), $opportunity->destinationUrl);
        $this->assertSame([], $opportunity->categories);
        $this->assertSame('Faltan 7', $opportunity->primaryValue);
        $this->assertSame('1 de 8', $opportunity->secondaryValue);
        $this->assertSame(['target' => 8, 'progress' => 1, 'remaining' => 7, 'state' => 'ACTIVE'], $opportunity->metadata);
        $this->assertNull($opportunity->partner);
        $this->assertNull($opportunity->location);
        $this->assertSame(app(MediaUrl::class)->url('unlocks/cena.jpg', 'hero'), $opportunity->image);
        $this->assertSame(1_000_000.0, $opportunity->editorialPriority);
        $this->assertSame([], DB::getQueryLog());
    }

    private function city(): DiscoveryCity
    {
        return new DiscoveryCity(1, 'Cochabamba', 'cochabamba');
    }

    /** @template T of Model @param class-string<T> $class @return T */
    private function model(string $class, array $attributes): Model
    {
        /** @var T $model */
        $model = new $class;
        $model->setRawAttributes($attributes, true);

        return $model;
    }
}
