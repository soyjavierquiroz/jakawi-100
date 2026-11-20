<?php

namespace Tests\Feature;

use App\Exceptions\PrelaunchOperationBlocked;
use App\Models\{AuditLog, Challenge, JpHold, Location, Partner, RewardTransaction, Unlock, UnlockParticipation, User};
use App\Services\UnlockParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerUnlockChallengeV3bTest extends TestCase
{
    use RefreshDatabase;

    private function business(string $role = 'owner'): array
    {
        config(['jakawi.prelaunch_mode' => false, 'jakawi.analytics.enabled' => false]);
        $partner = Partner::factory()->published()->create();
        $user = User::factory()->create(); $user->partners()->attach($partner, ['role' => $role]);
        return [$partner, $user, Location::factory()->published()->withPartner($partner)->create()];
    }

    private function proposal(Partner $partner, array $fields = []): Unlock
    {
        return Unlock::create($fields + ['title' => 'Propuesta propia', 'slug' => 'unlock-'.uniqid(), 'partner_id' => $partner->id, 'origin' => 'PARTNER', 'type' => 'EXPERIENCE', 'minimum_commitments' => 3, 'status' => Unlock::DRAFT]);
    }

    private function unlockData(array $fields = []): array
    {
        return $fields + ['title' => 'Propuesta revisada', 'type' => 'EXPERIENCE', 'minimum_commitments' => 3, 'maximum_capacity' => 8, 'terms' => 'Condiciones de la propuesta'];
    }

    private function challengeData(array $fields = []): array
    {
        return $fields + ['title' => 'Reto editorial', 'evidence_type' => 'SOCIAL_POST', 'qualification_type' => 'VALID_EVIDENCE', 'selection_type' => 'ALL_QUALIFIED', 'evaluation_mode' => 'CONTINUOUS', 'review_mode' => 'AUTOMATIC', 'participation_eligibility' => 'ALL_USERS', 'reward_eligibility' => 'ALL_USERS', 'reward_type' => 'MANUAL_PRIZE', 'manual_prize_description' => 'Premio propuesto', 'allowed_platforms' => ['instagram'], 'required_hashtags' => [], 'required_mentions' => []];
    }

    public function test_unlock_create_edit_preserve_omitted_locations_and_clear_only_when_explicit_during_prelaunch(): void
    {
        [$partner, $user, $location] = $this->business(); config(['jakawi.prelaunch_mode' => true]);
        $base = '/partner/'.$partner->slug.'/desbloqueos'; $this->actingAs($user);
        $this->post($base, $this->unlockData(['location_ids' => [$location->id], 'status' => 'ACTIVE', 'jp_deposit' => 900, 'partner_id' => 999]))->assertRedirect();
        $unlock = Unlock::sole(); $this->assertSame(Unlock::DRAFT, $unlock->status); $this->assertSame(0, $unlock->jp_deposit); $this->assertSame($partner->id, $unlock->partner_id);
        $this->get($base.'/'.$unlock->slug.'/editar')->assertInertia(fn (Assert $p) => $p->where('editable', true)->where('locationIds.0', $location->id));
        $this->put($base.'/'.$unlock->slug, $this->unlockData(['title' => 'Sedes conservadas']))->assertSessionHasNoErrors();
        $this->assertSame([$location->id], $unlock->locations()->pluck('locations.id')->all());
        $this->put($base.'/'.$unlock->slug, $this->unlockData(['location_ids' => []]))->assertSessionHasNoErrors();
        $this->assertSame([], $unlock->locations()->pluck('locations.id')->all());
    }

    public function test_foreign_locations_are_field_errors_and_do_not_partially_save(): void
    {
        [$partner, $user, $location] = $this->business(); $unlock = $this->proposal($partner); $unlock->locations()->attach($location);
        $foreign = Location::factory()->withPartner(Partner::factory()->create())->create();
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/desbloqueos/'.$unlock->slug, $this->unlockData(['location_ids' => [$foreign->id]]))->assertSessionHasErrors('location_ids.0');
        $this->assertSame('Propuesta propia', $unlock->fresh()->title); $this->assertSame([$location->id], $unlock->locations()->pluck('locations.id')->all());
        $this->post('/partner/'.$partner->slug.'/desbloqueos', $this->unlockData(['maximum_capacity' => 1]))->assertSessionHasErrors(['maximum_capacity' => 'El valor debe ser igual o mayor que 3.']);
        $this->assertSame(1, Unlock::count());
    }

    public function test_partner_challenge_validation_is_spanish_without_changing_admin_messages(): void
    {
        [$partner, $owner] = $this->business(); config(['jakawi.prelaunch_mode' => true]);
        $this->actingAs($owner)->post('/partner/'.$partner->slug.'/retos', $this->challengeData(['title' => '']))->assertSessionHasErrors(['title' => 'Completa este campo.']);
        $this->post('/partner/'.$partner->slug.'/retos', $this->challengeData(['reward_type' => 'JP', 'reward_jp_amount' => 0]))->assertSessionHasErrors(['reward_jp_amount' => 'El mínimo permitido es 1.']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/retos', $this->challengeData(['title' => '', 'status' => 'draft', 'slug' => 'invalid-admin']))->assertSessionHasErrors('title');
        $this->assertNotSame('Completa este campo.', session('errors')->get('title')[0]);
        $this->assertSame(0, Challenge::count());
    }

    public function test_unlock_review_and_operational_states_cannot_be_edited_or_resubmitted(): void
    {
        [$partner, $user] = $this->business(); config(['jakawi.prelaunch_mode' => true]); $this->actingAs($user);
        foreach ([Unlock::PENDING_REVIEW, Unlock::APPROVED, Unlock::ACTIVE, Unlock::UNLOCKED, Unlock::COMPLETED, Unlock::CANCELLED] as $state) {
            config(['jakawi.prelaunch_mode' => false]); $u = $this->proposal($partner, ['status' => $state]); config(['jakawi.prelaunch_mode' => true]);
            $base = '/partner/'.$partner->slug.'/desbloqueos/'.$u->slug;
            $this->get($base.'/editar')->assertInertia(fn (Assert $p) => $p->where('editable', false));
            $this->put($base, $this->unlockData())->assertForbidden();
            $this->post($base.'/enviar')->assertSessionHasErrors('status');
            $this->assertSame('Propuesta propia', $u->fresh()->title); $this->assertSame($state, $u->fresh()->status);
        }
        $rejected = $this->proposal($partner, ['status' => Unlock::REJECTED]);
        $rejected->statusHistory()->create(['to_status' => Unlock::REJECTED, 'reason' => 'Aclara las condiciones']);
        $this->get('/partner/'.$partner->slug.'/desbloqueos/'.$rejected->slug.'/editar')->assertInertia(fn (Assert $p) => $p->where('editable', true)->where('reviewNotes.0.reason', 'Aclara las condiciones'));
        $this->post('/partner/'.$partner->slug.'/desbloqueos/'.$rejected->slug.'/enviar')->assertRedirect();
        $this->assertSame(Unlock::PENDING_REVIEW, $rejected->fresh()->status);
    }

    public function test_unlock_material_guard_is_retained_even_for_an_inconsistent_draft_with_commitments(): void
    {
        [$partner, $user] = $this->business(); $u = $this->proposal($partner);
        UnlockParticipation::create(['unlock_id' => $u->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::CONFIRMED]);
        $this->actingAs($user)->put('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug, $this->unlockData())->assertStatus(422);
        $this->withHeader('X-Inertia', 'true')->put('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug, $this->unlockData())->assertSessionHasErrors('status');
        $this->post('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/enviar')->assertSessionHasErrors('status');
        $this->assertSame(Unlock::DRAFT, $u->fresh()->status);
    }

    public function test_manager_and_owner_fulfillment_keep_operator_trace_and_financial_idempotence(): void
    {
        foreach (['owner', 'manager'] as $role) {
            [$partner, $actor] = $this->business($role); $u = $this->proposal($partner, ['status' => Unlock::UNLOCKED, 'jp_completion_bonus' => 4]);
            $p = UnlockParticipation::create(['unlock_id' => $u->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::CONFIRMED, 'confirmed_at' => now()]);
            JpHold::create(['user_id' => $p->user_id, 'unlock_participation_id' => $p->id, 'amount' => 3, 'status' => JpHold::HELD, 'held_at' => now()]);
            $url = '/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones/'.$p->id.'/cumplir';
            $this->actingAs($actor)->post($url, ['note' => 'Comprobado en QA'])->assertRedirect(); $snapshot = $p->fresh()->getAttributes();
            $this->post($url, ['note' => 'No sobrescribir'])->assertRedirect(); $this->assertSame($snapshot, $p->fresh()->getAttributes());
            $this->assertSame($actor->id, $p->fresh()->fulfilled_by_user_id); $this->assertSame('partner_manual', $p->fresh()->fulfillment_source);
            $this->assertSame(1, RewardTransaction::where('unlock_participation_id', $p->id)->count());
            $this->assertSame(1, AuditLog::where('action', 'unlock_fulfilled')->where('subject_id', $p->id)->count());
            $this->assertSame(JpHold::RELEASED, JpHold::where('unlock_participation_id', $p->id)->sole()->status);
        }
    }

    public function test_participation_whitelist_is_scoped_private_and_read_only_during_prelaunch(): void
    {
        [$partner, $actor] = $this->business(); $u = $this->proposal($partner, ['status' => Unlock::UNLOCKED]);
        $member = User::factory()->create(['name' => 'Ana Pérez Privada']);
        $p = UnlockParticipation::create(['unlock_id' => $u->id, 'user_id' => $member->id, 'status' => UnlockParticipation::CONFIRMED, 'confirmed_at' => now()]);
        config(['jakawi.prelaunch_mode' => true]); $before = $p->fresh()->getAttributes();
        $base = '/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones';
        $this->actingAs($actor)->get($base)->assertInertia(fn (Assert $page) => $page->component('partner/unlocks/participations')->where('participations.data.0.member_display_name', 'Ana P.')->where('participations.data.0.can_fulfill', false)->missing('participations.data.0.user_id')->missing('participations.data.0.user')->missing('participations.data.0.email')->missing('participations.data.0.fulfilled_by_user_id'));
        $this->postJson($base.'/'.$p->id.'/cumplir')->assertStatus(423)->assertJsonPath('code', 'PRELAUNCH_OPERATION_BLOCKED');
        try { app(UnlockParticipationService::class)->fulfill($p, $actor); $this->fail('PRELAUNCH service bypass'); } catch (PrelaunchOperationBlocked) {}
        $this->assertSame($before, $p->fresh()->getAttributes()); $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_staff_cannot_access_editorial_or_participations_even_when_directly_invoked(): void
    {
        [$partner, $actor] = $this->business('staff'); $u = $this->proposal($partner, ['status' => Unlock::UNLOCKED]);
        $p = UnlockParticipation::create(['unlock_id' => $u->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::CONFIRMED]);
        $this->actingAs($actor);
        foreach (['desbloqueos', 'desbloqueos/crear', 'desbloqueos/'.$u->slug.'/editar', 'desbloqueos/'.$u->slug.'/participaciones', 'retos', 'retos/crear'] as $path) $this->get('/partner/'.$partner->slug.'/'.$path)->assertForbidden();
        $this->post('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones/'.$p->id.'/cumplir')->assertForbidden();
        $this->assertSame(UnlockParticipation::CONFIRMED, $p->fresh()->status);
    }

    public function test_multi_partner_operator_cannot_mix_unlocks_participations_or_challenges(): void
    {
        [$partner, $actor] = $this->business(); [$other] = $this->business(); $actor->partners()->attach($other, ['role' => 'manager']);
        $u = $this->proposal($partner, ['status' => Unlock::UNLOCKED]); $foreign = $this->proposal($other, ['status' => Unlock::UNLOCKED]);
        $p = UnlockParticipation::create(['unlock_id' => $foreign->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::CONFIRMED]);
        $this->actingAs($actor)->get('/partner/'.$partner->slug.'/desbloqueos/'.$foreign->slug.'/participaciones')->assertNotFound();
        $this->post('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones/'.$p->id.'/cumplir')->assertNotFound();
        $this->get('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones')->assertInertia(fn (Assert $page) => $page->has('participations.data', 0));
        $c = Challenge::create($this->challengeData() + ['slug' => 'foreign-challenge', 'partner_id' => $other->id, 'status' => 'draft', 'review_status' => 'DRAFT']);
        $this->get('/partner/'.$partner->slug.'/retos/'.$c->slug.'/preview')->assertNotFound();
        $this->assertSame(UnlockParticipation::CONFIRMED, $p->fresh()->status);
    }

    public function test_participation_state_window_and_cancelled_unlock_prevent_fulfillment_without_writes(): void
    {
        [$partner, $actor] = $this->business();
        foreach ([['status' => Unlock::CANCELLED], ['status' => Unlock::UNLOCKED, 'fulfillment_starts_at' => now()->addHour()], ['status' => Unlock::UNLOCKED, 'fulfillment_ends_at' => now()->subHour()]] as $fields) {
            $u = $this->proposal($partner, $fields); $p = UnlockParticipation::create(['unlock_id' => $u->id, 'user_id' => User::factory()->create()->id, 'status' => UnlockParticipation::CONFIRMED]);
            $this->actingAs($actor)->post('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones/'.$p->id.'/cumplir')->assertSessionHasErrors('unlock');
            $this->assertSame(UnlockParticipation::CONFIRMED, $p->fresh()->status);
        }
        $u = $this->proposal($partner, ['status' => Unlock::UNLOCKED]);
        foreach ([UnlockParticipation::INTERESTED, UnlockParticipation::COMMITTED, UnlockParticipation::UNLOCKED_PENDING_CONFIRMATION, UnlockParticipation::NO_SHOW, UnlockParticipation::CANCELLED_ON_TIME, UnlockParticipation::EXPIRED] as $state) {
            $p = UnlockParticipation::create(['unlock_id' => $u->id, 'user_id' => User::factory()->create()->id, 'status' => $state]);
            $this->actingAs($actor)->post('/partner/'.$partner->slug.'/desbloqueos/'.$u->slug.'/participaciones/'.$p->id.'/cumplir')->assertSessionHasErrors('unlock');
            $this->assertSame($state, $p->fresh()->status);
        }
        $this->assertDatabaseCount('reward_transactions', 0);
    }

    public function test_challenge_editorial_creation_image_preview_and_submit_work_during_prelaunch(): void
    {
        [$partner, $actor] = $this->business('manager'); config(['jakawi.prelaunch_mode' => true]); Storage::fake('public'); config(['media.disk' => 'public']);
        $base = '/partner/'.$partner->slug.'/retos'; $this->actingAs($actor)->post($base, $this->challengeData(['status' => 'open', 'review_status' => 'APPROVED']))->assertSessionHasNoErrors();
        $c = Challenge::sole(); $this->assertSame('draft', $c->status); $this->assertSame('DRAFT', $c->review_status);
        $this->get($base.'/'.$c->slug.'/editar')->assertInertia(fn (Assert $p) => $p->where('editable', true));
        $this->post($base.'/'.$c->slug.'/imagen', ['image' => UploadedFile::fake()->image('reto.png')])->assertSessionHasNoErrors();
        $this->assertNotNull($c->fresh()->hero_path);
        $this->get($base.'/'.$c->slug.'/preview')->assertInertia(fn (Assert $p) => $p->where('preview', true)->where('canParticipate', false)->where('canSubmit', false));
        $this->post($base.'/'.$c->slug.'/enviar')->assertSessionHasNoErrors(); $this->assertSame('SUBMITTED', $c->fresh()->review_status);
        $this->put($base.'/'.$c->slug, $this->challengeData())->assertForbidden();
        $this->post($base.'/'.$c->slug.'/imagen', ['image_remove' => true])->assertForbidden();
        $this->assertNotNull($c->fresh()->hero_path);
    }

    public function test_challenge_states_and_requested_changes_match_backend_editability(): void
    {
        [$partner, $actor] = $this->business(); $this->actingAs($actor); config(['jakawi.prelaunch_mode' => true]);
        foreach (['SUBMITTED', 'APPROVED', 'REJECTED'] as $review) {
            $c = Challenge::create($this->challengeData() + ['slug' => 'challenge-'.uniqid(), 'partner_id' => $partner->id, 'status' => 'draft', 'review_status' => $review]);
            $base = '/partner/'.$partner->slug.'/retos/'.$c->slug;
            $this->get($base.'/editar')->assertInertia(fn (Assert $p) => $p->where('editable', false));
            $this->put($base, $this->challengeData())->assertForbidden(); $this->post($base.'/imagen', ['image_remove' => true])->assertForbidden();
            $this->post($base.'/enviar')->assertSessionHasErrors('challenge');
        }
        $c = Challenge::create($this->challengeData() + ['slug' => 'changes-required', 'partner_id' => $partner->id, 'status' => 'draft', 'review_status' => 'CHANGES_REQUESTED', 'review_comment' => 'Detalla las instrucciones']);
        $this->get('/partner/'.$partner->slug.'/retos/'.$c->slug.'/editar')->assertInertia(fn (Assert $p) => $p->where('editable', true)->where('challenge.review_comment', 'Detalla las instrucciones'));
        $this->put('/partner/'.$partner->slug.'/retos/'.$c->slug, $this->challengeData(['instructions' => 'Reglas aclaradas']))->assertSessionHasNoErrors();
        $this->assertSame('CHANGES_REQUESTED', $c->fresh()->review_status);
        $this->post('/partner/'.$partner->slug.'/retos/'.$c->slug.'/enviar')->assertSessionHasNoErrors(); $this->assertSame('SUBMITTED', $c->fresh()->review_status);
    }
}
