<?php

namespace App\Services\Crm;

use App\Models\AttributionTouch;
use App\Models\CrmContactLink;
use App\Models\CrmDelivery;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class CrmContactProjectionService
{
    public function signal(Model $source, string $event, string $action = 'UNCHANGED'): void
    {
        if (! app(CrmConfiguration::class)->ready()) {
            return;
        }
        $class = $source::class;
        $id = $source->getKey();
        DB::afterCommit(function () use ($class, $id, $event, $action): void {
            try {
                $source = $class::find($id);
                if (! $source) {
                    return;
                }
                $payload = $this->project($source, $event, $action);
                if (! $payload) {
                    return;
                }
                DB::transaction(fn () => CrmDelivery::create(['provider' => 'fluentcrm', 'event_id' => $payload['event_id'], 'operation' => 'CONTACT_UPSERT', 'source_type' => $class, 'source_id' => $id, 'status' => 'PENDING', 'payload' => $payload]));
            } catch (Throwable) {
                Log::warning('CRM outbox unavailable.', ['error_code' => 'OUTBOX_UNAVAILABLE']);
            }
        });
    }

    public function project(Model $source, string $event, string $action = 'UNCHANGED'): ?array
    {
        $activities = ['lead_submitted', 'signup_completed', 'profile_updated', 'marketing_preference_changed', 'membership_purchase_requested', 'membership_activated', 'benefit_redeemed', 'experience_reserved', 'challenge_joined', 'unlock_committed', 'partner_application_submitted', 'program_application_submitted'];
        if (! in_array($event, [...$activities, 'membership_updated'], true)) {
            return null;
        }
        $user = $source instanceof User ? $source : ($source->user_id ? User::find($source->user_id) : null);
        // Partner forms identify their submitted email; never infer an email for phone-only leads.
        $email = $source instanceof PartnerApplication ? $source->contact_email : $user?->email;
        if (! is_string($email) || ! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $email = Str::lower(trim($email));
        // A different partner contact email must not take over the authenticated user's CRM identity.
        if ($source instanceof PartnerApplication && $user && Str::lower(trim($user->email)) !== $email) {
            $user = null;
        }
        $link = $user ? CrmContactLink::where('provider', 'fluentcrm')->where('user_id', $user->id)->first() : null;
        $fields = ['jakawi_lifecycle_stage' => $user ? 'user' : 'lead'];
        if (in_array($event, $activities, true)) {
            $fields['last_activity_type'] = $event;
            $fields['last_activity_at'] = ($source->updated_at ?? $source->created_at ?? now())->toISOString();
        }
        $lists = $user ? ['users'] : ['leads'];
        $remove = $user ? ['leads'] : [];
        $tags = $user ? ['jakawi-user'] : ['jakawi-lead'];
        $tagsRemove = [];
        if ($user) {
            $fields['signup_at'] = $user->created_at->toISOString();
            $fields['jakawi_user_id'] = (string) $user->id;
            $fields['jakawi_city'] = $user->profile?->city;
            $membership = $user->activeMembership()->first() ?? $user->memberships()->orderByDesc('starts_at')->orderByDesc('id')->first();
            $active = $user->hasActiveMembership();
            $fields['membership_status'] = $active ? 'active' : ($membership ? ($membership->status === 'active' ? 'expired' : $membership->status) : 'none');
            $fields['membership_started_at'] = $membership?->starts_at?->toISOString();
            $fields['membership_expires_at'] = $membership?->ends_at?->toISOString();
            $tagsRemove[] = $active ? 'member-expired' : 'member-active';
            if ($active) {
                $tags[] = 'member-active';
                if ($event !== 'membership_purchase_requested') {
                    $tagsRemove[] = 'membership-requested';
                }
            } elseif ($membership && ($membership->status === 'expired' || ($membership->status === 'active' && $membership->ends_at?->isPast()))) {
                $tags[] = 'member-expired';
            }
            if ($user->partners()->exists()) {
                $lists[] = 'partners';
                $tags[] = 'jakawi-partner';
            } else {
                $remove[] = 'partners';
                $tagsRemove[] = 'jakawi-partner';
            }
        }
        $tag = match ($event) {
            'membership_purchase_requested' => 'membership-requested','partner_application_submitted' => 'partner-applicant','program_application_submitted' => 'program-applicant','benefit_redeemed' => 'benefit-redeemer','experience_reserved' => 'experience-reserver','challenge_joined' => 'challenge-participant','unlock_committed' => 'unlock-participant',default => null
        };
        if ($tag) {
            $tags[] = $tag;
        }
        $consent = $source instanceof PartnerApplication ? $source : $user;
        if ($action !== 'UNCHANGED' && $consent) {
            $fields['marketing_opt_in'] = $consent->marketing_opt_in;
            $fields['marketing_opt_in_at'] = $consent->marketing_opt_in_at?->toISOString();
            $fields['marketing_opt_in_source'] = $consent->marketing_opt_in_source;
        }
        if ($source instanceof PartnerApplication) {
            $fields['lead_form'] = 'partner_application';
            $fields['lead_source'] = 'partner_application';
            if (! $user) {
                $fields['jakawi_city'] = $source->city?->name;
            }
        }
        if (! isset($fields['lead_source']) && in_array($event, ['signup_completed', 'program_application_submitted', 'lead_submitted'], true)) {
            $fields['lead_source'] = $event === 'signup_completed' ? 'registration' : ($event === 'program_application_submitted' ? 'program_application' : 'lead');
            $fields['lead_form'] = $fields['lead_source'];
        }
        $touches = AttributionTouch::query();
        if ($user) {
            $touches->where('user_id', $user->id);
        } elseif ($source instanceof PartnerApplication && $source->visitor_id) {
            $touches->whereNull('user_id')->where('anonymous_id', $source->visitor_id);
        } else {
            $touches->whereRaw('1=0');
        }
        $first = (clone $touches)->orderBy('occurred_at')->orderBy('id')->first();
        $last = (clone $touches)->orderByDesc('occurred_at')->orderByDesc('id')->first();
        $context = ['type' => $event, 'id' => (string) $source->id];
        foreach (['first' => $first, 'last' => $last] as $prefix => $touch) {
            if ($touch) {
                $context[$prefix.'_attribution'] = ['occurred_at' => $touch->occurred_at->toISOString(), 'id' => $touch->id];
                $provider = $touch->acquisition_provider?->value ?? 'NONE';
                $snapshot = ['acquisition_provider' => $provider, 'utm_source' => $touch->utm_source, 'utm_medium' => $touch->utm_medium, 'utm_campaign' => $touch->utm_campaign, 'utm_content' => $touch->utm_content, 'utm_term' => $touch->utm_term, 'touch_at' => $touch->occurred_at->toISOString(), 'campaign_key' => $touch->campaign_key, 'landing_slug' => $this->landing($touch->landing_page)];
                foreach ($snapshot as $key => $value) {
                    $fields[$prefix.'_'.$key] = $value;
                }
                if ($prefix === 'first') {
                    $sourceTag = match ($provider) {
                        'META' => 'source-meta','TIKTOK' => 'source-tiktok','GOOGLE' => 'source-google',default => null
                    };
                    if ($sourceTag) {
                        $tags[] = $sourceTag;
                    }
                }
            }
        }

        return ['version' => 1, 'event_id' => (string) Str::uuid(), 'operation' => 'CONTACT_UPSERT', 'occurred_at' => now()->toISOString(), 'source' => $context,
            'contact' => ['email' => $email, 'name' => $source instanceof PartnerApplication ? $source->contact_name : $user->name],
            'identity' => ['jakawi_user_id' => $user ? (string) $user->id : null, 'provider_contact_id' => $link?->provider_contact_id],
            'lists_add' => $lists, 'lists_remove' => $remove, 'tags_add' => array_values(array_unique($tags)), 'tags_remove' => $tagsRemove, 'fields' => $fields, 'marketing' => ['action' => $action]];
    }

    private function landing(?string $url): ?string
    {
        if (! $url) {
            return null;
        } $path = parse_url($url, PHP_URL_PATH);

        return $path ? basename(rtrim($path, '/')) : null;
    }
}
