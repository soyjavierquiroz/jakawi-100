<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Services\PublicJourneyContinuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PublicJourneyContinuationTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_get_consume_and_allowed_destination(): void
    {
        $experience = Experience::factory()->create();
        $service = app(PublicJourneyContinuation::class);
        $service->set('EXPERIENCE', $experience->id, 'RESERVE');

        $intent = ['journey' => 'EXPERIENCE', 'resource_id' => $experience->id, 'action' => 'RESERVE'];
        $this->assertSame($intent, $service->get());
        $this->assertSame(route('experiences.show', $experience->slug, false), $service->destination($intent));
        $this->assertSame($intent, $service->consume());
        $this->assertNull($service->consume());
    }

    public function test_unapproved_actions_and_external_destinations_are_rejected(): void
    {
        $service = app(PublicJourneyContinuation::class);
        foreach ([['EXPERIENCE', 1, 'DELETE'], ['https://evil.test', 1, 'RESERVE'], ['EXPERIENCE', 0, 'RESERVE']] as [$journey, $id, $action]) {
            try {
                $service->set($journey, $id, $action);
                $this->fail('Unsafe continuation was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertNull($service->get());
            }
        }

        $this->withSession(['url.intended' => 'https://evil.test/mi-jakawi'])
            ->post('/register', ['name' => 'Test', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect('/dashboard');
    }

    public function test_valid_continuation_has_priority_over_intended_url(): void
    {
        $experience = Experience::factory()->create();
        app(PublicJourneyContinuation::class)->set('EXPERIENCE', $experience->id, 'RESERVE');

        $this->withSession(['url.intended' => '/mi-jakawi'])
            ->post('/register', ['name' => 'Test', 'email' => 'new@example.test', 'whatsapp' => '71234567'])
            ->assertRedirect(route('experiences.show', $experience->slug, false));
    }
}
