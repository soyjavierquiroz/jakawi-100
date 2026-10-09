<?php

namespace App\Services;

use App\Enums\AcquisitionProvider;
use Illuminate\Http\Request;

class AcquisitionProviderResolver
{
    public function parse(mixed $value): AcquisitionProvider
    {
        return is_string($value) ? match (strtolower(trim($value))) {
            'meta' => AcquisitionProvider::META,
            'tiktok' => AcquisitionProvider::TIKTOK,
            'google' => AcquisitionProvider::GOOGLE,
            default => AcquisitionProvider::NONE,
        } : AcquisitionProvider::NONE;
    }

    public function suppressed(Request $request): bool
    {
        return str_contains(strtolower($request->header('Purpose', '').$request->header('Sec-Purpose', '')), 'prefetch')
            || $request->is('admin', 'admin/*')
            || $request->routeIs('*.preview')
            || (bool) $request->header('X-Inertia-Partial-Component');
    }

    public function forNewTouch(Request $request): AcquisitionProvider
    {
        return $this->suppressed($request) ? AcquisitionProvider::NONE : $this->parse($request->query('acq'));
    }
}
