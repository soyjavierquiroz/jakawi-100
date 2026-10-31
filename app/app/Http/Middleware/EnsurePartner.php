<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartner
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->partners()->exists(), 403);

        $partner = $request->route('partner');
        if ($partner instanceof Partner) {
            $relation = $user->partners()->whereKey($partner->id)->first();
            abort_unless($relation, 403);
            $canManage = in_array($relation->pivot->role, ['owner', 'manager'], true);
            $request->attributes->set('partner_access', [
                'can_manage' => $canManage,
                'can_edit_content' => $canManage && in_array($partner->status, ['draft', 'published'], true),
                'can_operate' => $partner->isPublished(),
            ]);
            $operational = $request->routeIs(
                'redemptions.validate', 'redemptions.confirm', 'partner.redemptions.scan.confirm',
                'partner.reservations.respond', 'partner.checkins.*', 'partner.unlocks.fulfill',
            );
            if ($operational) {
                abort_unless($partner->isPublished(), 403, 'El establecimiento no está activo para operar.');
            } elseif (! $request->isMethodSafe() || $request->routeIs(
                'partner.*.create', 'partner.*.edit', 'partner.challenges.preview',
            )) {
                abort_unless(in_array($relation->pivot->role, ['owner', 'manager'], true), 403);
                abort_unless(in_array($partner->status, ['draft', 'published'], true), 403, 'El establecimiento está pausado o archivado.');
            }
        }

        return $next($request);
    }
}
