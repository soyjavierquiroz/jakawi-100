<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\Conversion;
use App\Models\RewardRule;
use App\Models\User;
use App\Services\RewardResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_campaign_and_non_admin_cannot(): void
    {
        $payload = ['name' => 'Fundadores', 'code' => 'fundadores1000', 'status' => 'active', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'participant_types' => ['CREATOR'], 'start_at' => now()->subDay()->toDateTimeString(), 'end_at' => now()->addDay()->toDateTimeString()];
        $this->actingAs(User::factory()->create())->post('/admin/campaigns', $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post('/admin/campaigns', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('campaigns', ['code' => 'fundadores1000']);
    }

    public function test_campaign_rules_win_without_stacking_and_fall_back_outside_window(): void
    {
        $creator = User::factory()->create();
        $campaign = Campaign::create(['name' => 'Fundadores', 'code' => 'fundadores1000', 'status' => 'active', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'start_at' => now()->subDay(), 'end_at' => now()->addDay()]);
        CampaignParticipant::create(['campaign_id' => $campaign->id, 'participant_type' => 'CREATOR']);
        $base = ['participant_type' => 'CREATOR', 'event' => 'membership_purchased', 'product_key' => 'jakawi_annual', 'reward_type' => 'CASH', 'calculation_type' => 'FIXED', 'currency' => 'BOB', 'status' => 'active'];
        RewardRule::create([...$base, 'name' => 'global', 'value' => 10]);
        RewardRule::create([...$base, 'name' => 'campaign', 'campaign_id' => $campaign->id, 'value' => 15]);
        $specific = RewardRule::create([...$base, 'name' => 'specific', 'campaign_id' => $campaign->id, 'beneficiary_type' => 'USER', 'beneficiary_id' => $creator->id, 'beneficiary_user_id' => $creator->id, 'value' => 20]);
        $this->assertSame($specific->id, app(RewardResolver::class)->ruleFor($creator, 'USER', 'CREATOR', 'membership_purchased', 'jakawi_annual', 'CASH', $campaign->id, now())?->id);
        $this->assertSame('10.00', (string) app(RewardResolver::class)->ruleFor($creator, 'USER', 'CREATOR', 'membership_purchased', 'jakawi_annual')?->value);
    }

    public function test_raw_unknown_utm_is_preserved_and_campaign_link_is_historical(): void
    {
        $campaign = Campaign::create(['name' => 'Known', 'code' => 'known', 'status' => 'inactive', 'event' => 'membership_purchased']);
        $conversion = Conversion::create(['user_id' => User::factory()->create()->id, 'campaign_id' => $campaign->id, 'type' => 'membership_purchased', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'gross_amount' => 1, 'eligible_amount' => 1, 'status' => 'confirmed', 'occurred_at' => now(), 'attribution_snapshot' => ['utm_campaign' => 'unknown', 'campaign_code' => 'known']]);
        $campaign->update(['status' => 'inactive']);
        $this->assertSame($campaign->id, $conversion->fresh()->campaign_id);
        $this->assertSame('unknown', $conversion->fresh()->attribution_snapshot['utm_campaign']);
    }
}
