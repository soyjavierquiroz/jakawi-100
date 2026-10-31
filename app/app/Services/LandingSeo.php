<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\LandingPresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LandingSeo
{
    public function imagePath(LandingPresentation $landing, Model $subject): string
    {
        return $landing->social_image_path ?: $landing->hero_path
            ?: ($subject instanceof Experience ? ($subject->cover_path ?: $subject->image_path) : ($subject->image_path ?: $subject->hero_path))
            ?: config('media.social_fallback');
    }

    public function metadata(LandingPresentation $landing, Model $subject, string $canonical, bool $noindex): array
    {
        $title = $this->plain($landing->seo_title ?: $landing->headline ?: $subject->title);
        $description = $this->plain($landing->seo_description ?: $landing->subheadline ?: $subject->short_description ?: $subject->description);
        if ($description === '') $description = $this->plain($subject->title).' · Descubre la propuesta, condiciones y disponibilidad en JAKAWI.';

        return [
            'title' => $title.' | JAKAWI', 'description' => Str::limit($description, 300, '…'),
            'canonical' => $canonical, 'url' => route('landing-presentations.show', $landing->slug),
            'robots' => $noindex ? 'noindex,nofollow' : 'index,follow',
            'image' => app(MediaUrl::class)->url($this->imagePath($landing, $subject), 'social'),
            'imageAlt' => $this->plain($landing->seo_title ?: $subject->title).' · JAKAWI',
        ];
    }

    private function plain(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
