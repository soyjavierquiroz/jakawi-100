<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Challenge;
use App\Models\Experience;
use App\Models\ExperienceSession;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Unlock;
use App\Models\User;
use App\Services\LandingAiBriefBuilder;
use App\Services\LandingPresentationEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingAiBriefTest extends TestCase
{
    use RefreshDatabase;

    private function brief($subject): array
    {
        return app(LandingPresentationEditor::class)->context($subject, Request::create('/admin/landings'))['aiBrief'];
    }

    public function test_benefit_exports_authoritative_truth_without_treating_savings_as_discount(): void
    {
        $partner = Partner::factory()->published()->create(['name' => 'Luna Lateral — DEMO']);
        $benefit = Benefit::factory()->published()->forPartner($partner)->create([
            'title' => '20% en peinado de evento — DEMO', 'short_description' => 'Un peinado para tu evento.',
            'description' => 'Servicio de peinado.', 'terms' => 'No acumulable. Reserva previa.',
            'estimated_savings' => 999, 'starts_at' => '2026-10-01 12:00:00', 'ends_at' => '2026-12-31 23:00:00',
            'applies_to_all_locations' => true,
        ]);
        Location::factory()->published()->withPartner($partner)->create(['name' => 'Estudio', 'city' => 'Cochabamba', 'zone' => 'Cala Cala', 'address' => 'Calle Aroma 123']);
        $brief = $this->brief($benefit);
        $this->assertSame('Benefit', $brief['domain']);
        $this->assertSame($benefit->title, $brief['title']);
        $this->assertSame($partner->name, $brief['partner']);
        $this->assertTrue($brief['demo']);
        $this->assertSame('DEMO DE PRODUCTO · oferta simulada, no canjeable', $brief['demo_disclosure']);
        $truth = $brief['commercial_truth'];
        $this->assertSame('percentage', $truth['benefit_type']);
        $this->assertSame(['description' => $benefit->title], $truth['value']);
        $this->assertTrue($truth['membership_required']);
        $this->assertSame('2026-12-31T19:00:00-04:00', $truth['validity']['ends_at']);
        $this->assertSame('America/La_Paz', $truth['validity']['timezone']);
        $this->assertSame('Cala Cala', $truth['locations'][0]['area']);
        $this->assertSame([$benefit->terms], $truth['conditions']);
        $this->assertNotEmpty($truth['requirements']);
        $this->assertStringNotContainsString('999', json_encode($brief));
    }

    public function test_experience_exports_all_upcoming_sessions_prices_and_reservation_mode_without_contact_data(): void
    {
        $experience = Experience::factory()->published()->create(['regular_price' => 80, 'member_price' => 60, 'reservation_method' => 'url', 'reservation_url' => 'https://private.example/booking?signature=secret', 'terms' => 'Incluye materiales. Trae ropa cómoda.']);
        $location = Location::factory()->published()->create(['name' => 'Mirador', 'address' => 'Avenida Central', 'city' => 'Cochabamba']);
        ExperienceSession::factory()->count(7)->create(['experience_id' => $experience->id, 'location_id' => $location->id, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2), 'capacity' => 20]);
        ExperienceSession::factory()->past()->create(['experience_id' => $experience->id]);
        ExperienceSession::factory()->cancelled()->create(['experience_id' => $experience->id]);
        $truth = $this->brief($experience)['commercial_truth'];
        $this->assertSame(['currency' => 'BOB', 'regular_price' => '80.00', 'member_price' => '60.00'], $truth['prices']);
        $this->assertSame('external', $truth['reservation_mode']);
        $this->assertSame(7, $truth['available_sessions']);
        $this->assertCount(7, $truth['sessions']);
        $this->assertSame('Mirador', $truth['sessions'][0]['venue']);
        $this->assertSame(20, $truth['sessions'][0]['capacity']);
        $this->assertArrayHasKey('ends_at', $truth['sessions'][0]);
        $this->assertArrayNotHasKey('membership_required', $truth);
        $this->assertStringNotContainsString('private.example', json_encode($truth));
        $experience->update(['reservation_method' => 'jakawi']);
        $this->assertTrue($this->brief($experience)['commercial_truth']['membership_required']);
        $experience->update(['reservation_method' => 'none']);
        $this->assertSame('informational', $this->brief($experience)['commercial_truth']['reservation_mode']);
    }

    public function test_challenge_exports_objective_evidence_selection_reward_and_verification(): void
    {
        $challenge = Challenge::create([
            'title' => 'Reto creativo', 'slug' => 'reto-creativo', 'description' => 'Comparte tu ciudad.', 'instructions' => 'Publica una foto original.',
            'status' => 'open', 'review_status' => 'APPROVED', 'evidence_type' => 'SOCIAL_POST', 'allowed_platforms' => ['instagram'],
            'qualification_type' => 'METRIC_THRESHOLD', 'qualification_metric' => 'likes', 'qualification_target' => 20,
            'selection_type' => 'FIRST_N', 'winner_limit' => 10, 'evaluation_mode' => 'CONTINUOUS', 'review_mode' => 'REQUIRED',
            'participation_eligibility' => 'ALL_USERS', 'reward_eligibility' => 'ACTIVE_MEMBERS', 'reward_type' => 'MANUAL_PRIZE',
            'manual_prize_description' => 'Un taller creativo', 'max_entries_per_user' => 2, 'ends_at' => now()->addWeek(),
        ]);
        $truth = $this->brief($challenge)['commercial_truth'];
        $this->assertSame('Publica una foto original.', $truth['objective']);
        $this->assertSame('Enlace de una publicación social.', $truth['evidence_type']);
        $this->assertSame(['instagram'], $truth['platforms']);
        $this->assertStringContainsString('20 likes', $truth['qualification']);
        $this->assertStringContainsString('primeros 10 participantes verificados', $truth['selection']);
        $this->assertSame('Un taller creativo', $truth['reward']);
        $this->assertFalse($truth['membership_required']);
        $this->assertTrue($truth['review_required']);
        $this->assertSame(2, $truth['entry_limit_per_person']);
        $this->assertArrayHasKey('ends_at', $truth['validity']);
        $this->assertStringContainsString('Confirma tu correo', $truth['verification_boundary']);
        $this->assertStringNotContainsString('FIRST_N', json_encode($truth));
    }

    public function test_allowlist_excludes_internal_and_private_data_even_from_public_text(): void
    {
        $partner = Partner::factory()->create(['name' => 'Partner', 'phone' => '+59170012345', 'email' => 'private@example.test']);
        $benefit = Benefit::factory()->forPartner($partner)->create(['title' => 'Oferta', 'review_notes' => 'SECRET-PRIVATE-NOTE', 'image_path' => 'private/storage.jpg', 'description' => 'Escribe a private@example.test', 'terms' => 'Llama al +59170012345']);
        Location::factory()->published()->withPartner($partner)->create(['email' => 'manager@example.test']);
        $brief = $this->brief($benefit);
        $encoded = json_encode($brief);
        foreach (['SECRET-PRIVATE-NOTE', 'storage.jpg', '@example.test', '+59170012345', 'estimated_savings', 'App\\\\Models', 'review_notes'] as $private) $this->assertStringNotContainsString($private, $encoded);
        $walk = function (array $data) use (&$walk): void {
            foreach ($data as $key => $value) {
                $this->assertDoesNotMatchRegularExpression('/(^id$|_id$|uuid|secret|url|path|email|phone|notes)/i', (string) $key);
                if (is_array($value)) $walk($value);
            }
        };
        $walk($brief);
        $this->assertArrayNotHasKey('description', $brief);
        $this->assertArrayNotHasKey('conditions', $brief['commercial_truth']);
    }

    public function test_missing_facts_are_omitted_and_grant_access_is_not_public_membership(): void
    {
        $benefit = Benefit::factory()->create(['title' => 'Oferta', 'short_description' => null, 'description' => null, 'terms' => null, 'estimated_savings' => null, 'redemption_limit_per_member' => null, 'access_mode' => 'social_challenge_grant']);
        $brief = $this->brief($benefit);
        foreach (['summary', 'description', 'city', 'demo_disclosure'] as $key) $this->assertArrayNotHasKey($key, $brief);
        foreach (['validity', 'locations', 'usage_limit', 'conditions', 'membership_required'] as $key) $this->assertArrayNotHasKey($key, $brief['commercial_truth']);
        $this->assertStringContainsString('premio vigente', $brief['commercial_truth']['access_requirement']);
        $experience = Experience::factory()->create(['regular_price' => null, 'member_price' => null, 'reservation_method' => 'none']);
        $truth = $this->brief($experience)['commercial_truth'];
        $this->assertArrayNotHasKey('prices', $truth);
        $this->assertArrayNotHasKey('sessions', $truth);
        $this->assertSame(0, $truth['available_sessions']);
    }

    public function test_version_order_json_and_read_only_admin_projection_are_stable(): void
    {
        Http::fake();
        $benefit = Benefit::factory()->create(['title' => 'Oferta']);
        $before = $benefit->refresh()->getAttributes();
        $brief = $this->brief($benefit);
        $this->assertSame(LandingAiBriefBuilder::VERSION, $brief['brief_version']);
        $this->assertSame(['brief_version', 'domain', 'title'], array_slice(array_keys($brief), 0, 3));
        $this->assertSame($brief, $this->brief($benefit->fresh()));
        $json = json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertSame($brief, json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/landings/subject?subject_type=BENEFIT&subject_id='.$benefit->id)->assertOk()->assertJsonPath('aiBrief.title', 'Oferta')->assertJsonPath('aiBrief.brief_version', LandingAiBriefBuilder::VERSION);
        $this->assertSame($before, $benefit->fresh()->getAttributes());
        $this->assertDatabaseCount('landing_presentations', 0);
        Http::assertNothingSent();
    }

    public function test_unlock_is_excluded(): void
    {
        $this->assertNull(app(LandingAiBriefBuilder::class)->build(new Unlock, []));
    }
}
