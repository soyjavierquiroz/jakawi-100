<?php
namespace App\Services;

use App\Models\ChallengeSocialEntry;

final class SocialEntryState {
    public function __construct(private SocialEntryRules $rules) {}

    public function entry(ChallengeSocialEntry $entry): array {
        return [
            'entry_id'=>$entry->id, 'social_url'=>$entry->social_url,
            'inspection_status'=>$entry->inspection_status, 'validation_status'=>$entry->validation_status,
            'data_quality'=>$entry->data_quality, 'platform'=>$entry->platform,
            'likes'=>$entry->likes, 'views'=>$entry->views, 'comments'=>$entry->comments,
            'checked_at'=>$entry->checked_at?->toIso8601String(), 'refresh_pending'=>$entry->refresh_pending,
            'requirements'=>$this->rules->requirements($entry->challenge, $entry),
        ];
    }

    public function participation(ChallengeSocialEntry $entry): array {
        $participation = $entry->participation;
        return $participation->only(['id','qualification_status','qualified_entry_id','selection_status','selected_entry_id','review_status']);
    }

    public function grant(ChallengeSocialEntry $entry): ?array {
        $grant = $entry->participation->grant;
        if (!$grant) return null;
        return ['id'=>$grant->id,'status'=>$grant->status,'reward_type'=>$grant->reward_type,'jp_amount'=>$grant->jp_amount,
            'jp_reversed'=>$grant->reward_type === 'JP' && $grant->jpCredit?->reversal !== null,
            'benefit'=>$grant->benefit?->only(['title','slug']),
            'locations'=>$grant->benefit?->availableLocations()->get(['id','name'])];
    }
}
