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
            'blocks.*' => 'array:type,enabled,title,subtitle,primary_label,secondary_label,note,source,verified,items',
            'blocks.*.type' => ['required', 'distinct', Rule::in(self::TYPES)],
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
            $old = collect($previous)->firstWhere('type', $block['type']);
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

    public function present(array $blocks, bool $preview): array
    {
        return collect($blocks)->filter(fn ($b) => ($b['enabled'] ?? false) && ($preview || ! in_array($b['type'], ['trust', 'testimonials', 'guarantee'], true) || (($b['verified'] ?? false) && filled($b['source'] ?? null))))
            ->map(function ($block) use ($preview) {
                if (! $preview) unset($block['source']);
                $block['items'] = array_map(function ($item) {
                    $item['image_url'] = app(MediaUrl::class)->url($item['image_path'] ?? null, 'thumbnail');
                    unset($item['image_path']);
                    return $item;
                }, $block['items'] ?? []);
                return $block;
            })->values()->all();
    }
}
