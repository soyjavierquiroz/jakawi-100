<?php
namespace App\Services;

use App\Models\LandingPresentation;
use App\Models\Unlock;
use Illuminate\Validation\Rule;

/** One nullable configuration, using the existing Unlock disclosure boundary. */
class LandingVideoConfiguration
{
    public static function rules(): array
    {
        $url = ['nullable', 'string', 'url:https', 'max:2048', 'regex:~^https://[^\s<>"\x27]+$~i'];
        return [
            'vsl' => ['nullable', 'min:1', 'array:source,mode,framing,autoplay,poster,theme,colors'],
            'vsl.source' => ['required_with:vsl', 'array:type,url', function ($attribute, $source, $fail) {
                if (!is_array($source) || !is_string($source['url'] ?? null)) return;
                $url = parse_url($source['url']);
                if (!$url || isset($url['user']) || isset($url['pass'])) {
                    $fail('La URL no debe contener credenciales.');
                    return;
                }
                if (($source['type'] ?? null) !== 'youtube') return;
                $host = strtolower($url['host'] ?? '');
                $path = $url['path'] ?? '';
                parse_str($url['query'] ?? '', $query);
                $id = null;
                if ($host === 'youtu.be') $id = ltrim($path, '/');
                elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
                    if ($path === '/watch') $id = $query['v'] ?? null;
                    elseif (preg_match('~^/(?:embed|shorts|live|v)/([^/]+)$~', $path, $matches)) $id = $matches[1];
                }
                if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $id)) $fail('Selecciona una URL válida de YouTube.');
            }],
            'vsl.source.type' => ['required_with:vsl', Rule::in(['youtube', 'mp4', 'bunny'])],
            'vsl.source.url' => ['required_with:vsl', ...$url],
            'vsl.mode' => ['sometimes', Rule::in(['vsl'])],
            'vsl.framing' => ['sometimes', Rule::in(['embedded-vertical', 'native-vertical', 'horizontal'])],
            'vsl.autoplay' => ['sometimes', Rule::in(['muted', 'off'])],
            'vsl.poster' => $url,
            'vsl.theme' => ['sometimes', Rule::in(['inherit', 'custom'])],
            'vsl.colors' => ['sometimes', 'array:accent,text,overlay'],
            'vsl.colors.accent' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'vsl.colors.text' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'vsl.colors.overlay' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function forPresentation(LandingPresentation $presentation): ?array
    {
        $subject = $presentation->subject;
        // Never serialize the configuration (including poster) while disclosure is locked.
        if ($subject instanceof Unlock && (! $subject->revealable() || ! app(\App\Http\Controllers\UnlockController::class)->authorizedReveal($subject))) return null;
        return $presentation->vsl;
    }
}
