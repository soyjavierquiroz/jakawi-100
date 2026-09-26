<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExperienceMvpPublicFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_exposes_one_upcoming_session_location_and_organizer(): void
    {
        $partner = Partner::factory()->published()->create(['name' => 'Organizador']);
        $location = Location::factory()->withoutPartner()->published()->create(['maps_url' => 'https://maps.example.test/place']);
        $experience = Experience::factory()->published()->create(['reservation_method' => 'whatsapp', 'reservation_whatsapp' => '+59170000000']);
        $experience->syncPartnersWithRoles([['partner_id' => $partner->id, 'role' => 'organizer']]);
        ExperienceSession::factory()->for($experience)->withLocation($location)->upcoming()->create();
        ExperienceSession::factory()->for($experience)->past()->create();

        $this->get('/experiencias/'.$experience->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('experience.partners.0.name', 'Organizador')
            ->has('experience.sessions', 1)
            ->where('experience.sessions.0.location.slug', $location->slug)
            ->where('experience.reservation_targets.0.method', 'whatsapp')
        );
    }

    public function test_multiple_sessions_and_both_external_targets_are_exposed_in_configured_order(): void
    {
        $experience = Experience::factory()->published()->create([
            'reservation_method' => 'url',
            'reservation_url' => 'https://example.test/reservar',
            'reservation_whatsapp' => '+59170000000',
        ]);
        ExperienceSession::factory()->for($experience)->upcoming()->count(2)->create();

        $this->get('/experiencias/'.$experience->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('experience.sessions', 2)
            ->where('experience.reservation_targets.0.method', 'url')
            ->where('experience.reservation_targets.1.method', 'whatsapp')
        );
    }

    public function test_no_future_session_has_no_reservation_target_and_external_destination_is_blocked(): void
    {
        $experience = Experience::factory()->published()->create(['reservation_method' => 'external', 'reservation_url' => 'https://example.test/reservar']);
        ExperienceSession::factory()->for($experience)->past()->create();

        $this->get('/experiencias/'.$experience->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('experience.sessions', 0)
            ->has('experience.reservation_targets', 1)
        );
        $this->get('/experiencias/'.$experience->slug.'/reservar?method=external')->assertNotFound();
    }

    public function test_whatsapp_and_external_clicks_are_tracked_without_a_dead_target(): void
    {
        $experience = Experience::factory()->published()->create([
            'reservation_method' => 'whatsapp',
            'reservation_whatsapp' => '+59170000000',
            'reservation_url' => 'https://example.test/reservar',
        ]);
        ExperienceSession::factory()->for($experience)->upcoming()->create();

        $this->get('/experiencias/'.$experience->slug.'/reservar?method=whatsapp')->assertRedirect('https://wa.me/59170000000');
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'experience_reserve_click', 'experience_id' => $experience->id]);
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'whatsapp_click', 'experience_id' => $experience->id]);
        $this->get('/experiencias/'.$experience->slug.'/reservar?method=url')->assertRedirect('https://example.test/reservar');
        $this->assertSame('url', AnalyticsEvent::where('event_name', 'experience_reserve_click')->latest('id')->value('metadata')['reservation_method']);
    }

    public function test_unpublished_experience_is_not_public(): void
    {
        $experience = Experience::factory()->create(['status' => 'draft']);

        $this->get('/experiencias/'.$experience->slug)->assertNotFound();
    }
}
