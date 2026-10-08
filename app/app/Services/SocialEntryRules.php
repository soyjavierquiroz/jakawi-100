<?php
namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeSocialEntry;

final class SocialEntryRules {
    public function requirements(Challenge $challenge, ChallengeSocialEntry $entry): array {
        if ($entry->inspection_status !== 'inspected') return [];
        $requirements = [];
        $add = function (string $key, string $label, string $status, mixed $value = null) use (&$requirements): void {
            $rule = ['key'=>$key, 'label'=>$label, 'status'=>$status];
            if ($value !== null) $rule['value'] = $value;
            $requirements[] = $rule;
        };

        $found = $entry->data_quality === 'unavailable' ? 'UNKNOWN' :
            (($entry->platform || $entry->social_external_id || $entry->canonical_url) ? 'PASS' : 'UNKNOWN');
        $add('publication', 'Publicación encontrada', $found);

        $platforms = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $challenge->allowed_platforms ?? []);
        if ($platforms) $add('platform', 'Plataforma permitida', $entry->platform === null ? 'UNKNOWN' :
            (in_array(mb_strtolower($entry->platform), $platforms, true) ? 'PASS' : 'FAIL'), $entry->platform);

        $public = $entry->sharecontest_payload['data']['is_public'] ?? null;
        $add('public', 'Publicación pública', is_bool($public) ? ($public ? 'PASS' : 'FAIL') : 'UNKNOWN');

        foreach ($challenge->required_hashtags ?? [] as $tag) {
            $normalized = $this->normalizedToken($tag, '#');
            if ($normalized === '') continue;
            $add('hashtag:'.$normalized, '#'.$normalized, $this->captionTokenStatus($entry->caption, '#', $normalized));
        }
        foreach ($challenge->required_mentions ?? [] as $mention) {
            $normalized = $this->normalizedToken($mention, '@');
            if ($normalized === '') continue;
            $add('mention:'.$normalized, '@'.$normalized, $this->captionTokenStatus($entry->caption, '@', $normalized));
        }
        foreach (['published_from'=>'Publicada después del inicio', 'published_until'=>'Publicada antes del cierre'] as $field=>$label) {
            if (!$challenge->{$field}) continue;
            $published = $entry->published_at;
            $pass = $published && ($field === 'published_from' ? $published->gte($challenge->{$field}) : $published->lte($challenge->{$field}));
            $add($field, $label, $published ? ($pass ? 'PASS' : 'FAIL') : 'UNKNOWN');
        }
        return $requirements;
    }

    public function validationStatus(Challenge $challenge, ChallengeSocialEntry $entry, string $providerStatus): string {
        $requirements = $this->requirements($challenge, $entry);
        if (collect($requirements)->contains(fn ($rule) => $rule['status'] === 'FAIL')) return 'invalid';
        // Public visibility is optional when the provider has no visibility field.
        if (collect($requirements)->contains(fn ($rule) => $rule['key'] !== 'public' && $rule['status'] === 'UNKNOWN')) return 'review_required';
        return $providerStatus === 'valid' ? 'valid' : 'review_required';
    }

    private function normalizedToken(string $token, string $prefix): string {
        return mb_strtolower(ltrim(trim($token), $prefix));
    }

    private function captionTokenStatus(?string $caption, string $prefix, string $token): string {
        if ($caption === null) return 'UNKNOWN';
        preg_match_all('/(?<![\pL\pN_])'.preg_quote($prefix, '/').'([\pL\pN_]+)/u', $caption, $matches);
        $tokens = array_map(fn ($value) => mb_strtolower($value), $matches[1] ?? []);
        return in_array($token, $tokens, true) ? 'PASS' : 'FAIL';
    }
}
