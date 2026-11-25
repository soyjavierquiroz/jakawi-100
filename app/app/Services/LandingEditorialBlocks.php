<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Versioned optional extension. Legacy presentation JSON is left untouched. No economics or action URLs. */
class LandingEditorialBlocks
{
    public const TYPES = ['hero', 'trust', 'problem', 'benefits', 'steps', 'testimonials', 'offer', 'guarantee', 'faq', 'final', 'footer'];

    public function validate(array $blocks, array $previous, Request $request, ?int $id): array
    {
        $data = Validator::make(['blocks' => $blocks], [
            'blocks' => 'array|max:11',
            'blocks.*' => 'array:type,enabled,title,subtitle,primary_label,secondary_label,note,source,verified,items,availability,background,variant,image_path,image_upload,image_remove,image_alt,image_position,layout,title_emphasis',
            'blocks.*.layout' => ['sometimes', Rule::in(['flow', 'split', 'spotlight', 'proof'])],
            'blocks.*.title_emphasis' => 'nullable|string|max:240',
            'blocks.*.background' => ['sometimes', Rule::in(['light', 'soft', 'dark'])],
            'blocks.*.variant' => ['sometimes', Rule::in(['cards', 'minimal'])],
            'blocks.*.image_position' => ['sometimes', Rule::in(['left', 'right'])],
            'blocks.*.image_path' => 'nullable|string|max:2048',
            'blocks.*.image_upload' => 'nullable|string|max:5000',
            'blocks.*.image_remove' => 'sometimes|boolean',
            'blocks.*.image_alt' => 'nullable|string|max:320',
            'blocks.*.type' => ['required', 'distinct', Rule::in(self::TYPES)],
            'blocks.*.availability' => ['sometimes', Rule::in(['COMING_SOON'])],
            'blocks.*.enabled' => 'required|boolean', 'blocks.*.verified' => 'sometimes|boolean',
            'blocks.*.title' => 'nullable|string|max:240', 'blocks.*.subtitle' => 'nullable|string|max:3000',
            'blocks.*.primary_label' => 'nullable|string|max:100', 'blocks.*.secondary_label' => 'nullable|string|max:100',
            'blocks.*.note' => 'nullable|string|max:3000', 'blocks.*.source' => 'nullable|string|max:2000',
            'blocks.*.items' => 'sometimes|array|max:20',
            'blocks.*.items.*' => 'array:title,description,name,city,result,image_path,image_upload,image_remove,value,label,question,answer,href',
            'blocks.*.items.*.title' => 'nullable|string|max:240', 'blocks.*.items.*.description' => 'nullable|string|max:3000',
            'blocks.*.items.*.name' => 'nullable|string|max:200', 'blocks.*.items.*.city' => 'nullable|string|max:200',
            'blocks.*.items.*.result' => 'nullable|string|max:500', 'blocks.*.items.*.image_path' => 'nullable|string|max:2048',
            'blocks.*.items.*.image_upload' => 'nullable|string|max:5000', 'blocks.*.items.*.image_remove' => 'sometimes|boolean',
            'blocks.*.items.*.value' => 'nullable|string|max:100', 'blocks.*.items.*.label' => 'nullable|string|max:200',
            'blocks.*.items.*.href' => ['nullable', 'string', 'max:2048', 'regex:~^(?:/(?!/)[^\\\\]*|https://[^\\\\]+|mailto:[^\\\\]+)$~'],
            'blocks.*.items.*.question' => 'nullable|string|max:500', 'blocks.*.items.*.answer' => 'nullable|string|max:3000',
        ])->validate()['blocks'];
        foreach ($data as $i => &$block) {
            if (filled($block['title_emphasis'] ?? null) && !str_contains($block['title'] ?? '', $block['title_emphasis'])) throw ValidationException::withMessages(["editorial_blocks.$i.title_emphasis" => 'El énfasis debe ser parte del título.']);
            $old = collect($previous)->firstWhere('type', $block['type']);
            $path = $block['image_path'] ?? null;
            if ($path && $path !== ($old['image_path'] ?? null)) {
                throw ValidationException::withMessages(["editorial_blocks.$i.image_path" => 'Sube una imagen desde tu dispositivo.']);
            }
            $input = new Request(['image_upload' => $block['image_upload'] ?? null, 'image_remove' => $block['image_remove'] ?? false]);
            $input->setUserResolver(fn () => $request->user());
            $block['image_path'] = app(MediaUploadService::class)->fromRequest($input, 'image', 'landings', $id ?? 1, 'image', $path);
            unset($block['image_upload'], $block['image_remove']);
            foreach ($block['items'] ?? [] as $j => $item) {
                if (filled($item['href'] ?? null) && $block['type'] !== 'footer') {
                    throw ValidationException::withMessages(["editorial_blocks.$i.items.$j.href" => 'Los enlaces se editan únicamente en el pie de página.']);
                }
                $path = $item['image_path'] ?? null;
                // A saved managed image may be retained or reordered; external/private paths cannot be injected.
                if ($path && ! in_array($path, array_column($old['items'] ?? [], 'image_path'), true)) {
                    throw ValidationException::withMessages(["editorial_blocks.$i.items.$j.image_path" => 'Sube una imagen desde tu dispositivo.']);
                }
                $input = new Request(['image_upload' => $item['image_upload'] ?? null, 'image_remove' => $item['image_remove'] ?? false]);
                $input->setUserResolver(fn () => $request->user());
                $item['image_path'] = app(MediaUploadService::class)->fromRequest($input, 'image', 'landings', $id ?? 1, 'image', $path);
                unset($item['image_upload'], $item['image_remove']);
                $block['items'][$j] = $item;
            }
        }
        return $data;
    }

    public function present(array $blocks, bool $preview, bool $editorialReview = false): array
    {
        return collect($blocks)->filter(fn ($b) => ($b['enabled'] ?? false) && ($preview || $editorialReview || ! in_array($b['type'], ['trust', 'testimonials', 'guarantee'], true) || (($b['verified'] ?? false) && filled($b['source'] ?? null))))
            ->map(function ($block) use ($preview) {
                if (! $preview) unset($block['source']);
                $block['image_url'] = app(MediaUrl::class)->url($block['image_path'] ?? null, 'hero');
                unset($block['image_path']);
                $block['items'] = array_map(function ($item) {
                    $item['image_url'] = app(MediaUrl::class)->url($item['image_path'] ?? null, 'thumbnail');
                    unset($item['image_path']);
                    return $item;
                }, $block['items'] ?? []);
                return $block;
            })->values()->all();
    }
}
