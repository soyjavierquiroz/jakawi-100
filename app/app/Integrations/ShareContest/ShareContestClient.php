<?php
namespace App\Integrations\ShareContest;
use App\Models\ChallengeSocialEntry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
final class ShareContestClient implements Inspector {
    public function inspect(ChallengeSocialEntry $entry): InspectionResult {
        $challenge = $entry->challenge;
        $base = rtrim((string) config('services.sharecontest.url'), '/');
        $token = (string) config('services.sharecontest.token');
        if ($base === '' || $token === '') throw new InspectionException('configuration', false);
        try {
            $response = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(30)->post($base.'/api/v1/inspect', [
                'url'=>$entry->social_url, 'external_reference'=>$entry->external_reference,
                'campaign_reference'=>'social-challenge-'.$challenge->id,
                'rules'=>['allowed_platforms'=>$challenge->allowed_platforms ?? [], 'required_hashtags'=>$challenge->required_hashtags ?? [],
                    'required_mentions'=>$challenge->required_mentions ?? [], 'published_from'=>$challenge->published_from?->toIso8601String(), 'published_until'=>$challenge->published_until?->toIso8601String()],
            ]);
        } catch (ConnectionException) { throw new InspectionException('connection', true); }
        if (!$response->successful()) {
            $status = $response->status();
            throw new InspectionException(match ($status) { 401=>'unauthorized', 422=>'invalid_request', 429=>'rate_limited', default=>'http_'.$status }, $status === 429 || $status >= 500);
        }
        $body = $response->json();
        if (!is_array($body) || !is_array($body['data'] ?? null) || !is_array($body['validation'] ?? null)) throw new InspectionException('malformed_response', true);
        $data = $body['data']; $validation = $body['validation'];
        $status = $validation['status'] ?? null;
        if (!in_array($status, ['valid','invalid','review_required','not_requested'], true)) throw new InspectionException('malformed_response', true);
        $quality = $data['data_quality'] ?? 'unknown';
        if (!in_array($quality, ['unknown','complete','partial','unavailable'], true)) $quality = 'unknown';
        $metrics = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];
        $safeMetric = fn ($key) => isset($metrics[$key]) && is_numeric($metrics[$key]) && $metrics[$key] >= 0 ? (int) $metrics[$key] : null;
        $normalized = [
            'sharecontest_id'=>$data['sharecontest_id'] ?? null, 'sharecontest_request_id'=>$body['request_id'] ?? null,
            'canonical_url'=>$data['canonical_url'] ?? null, 'platform'=>$data['platform'] ?? null,
            'social_external_id'=>$data['external_id'] ?? null, 'author'=>$data['author'] ?? null,
            'username'=>$data['username'] ?? null, 'caption'=>$data['caption'] ?? null,
            'published_at'=>$data['published_at'] ?? null, 'published_at_precision'=>$data['published_at_precision'] ?? null,
            'views'=>$safeMetric('views'), 'likes'=>$safeMetric('likes'), 'comments'=>$safeMetric('comments'),
            'data_quality'=>$quality, 'validation_status'=>$status, 'checked_at'=>$data['checked_at'] ?? now()->toIso8601String(),
        ];
        // Persist an explicit allowlist only. Remote headers, credentials and arbitrary keys are discarded.
        $payload = ['request_id'=>$body['request_id'] ?? null, 'data'=>array_intersect_key($data,array_flip(['sharecontest_id','platform','canonical_url','external_id','published_at','published_at_precision','is_public','data_quality','checked_at'])),
            'validation'=>['status'=>$status,'checks'=>array_map(fn ($check)=>is_array($check) ? array_intersect_key($check,array_flip(['key','status','message'])) : [], array_slice(is_array($validation['checks'] ?? null) ? $validation['checks'] : [],0,30))]];
        return new InspectionResult($normalized,$payload);
    }
}
