<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use App\Models\SupportSession;
use App\Models\User;
use App\Services\Support\SupportAccess;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SupportMode
{
    // Reviewed read-only screens. Everything else, including future routes, fails closed.
    private const SCREENS = [
        'mi-jakawi', 'consumer.profile', 'member.profile.show',
        'home', 'explore', 'benefits.index', 'benefits.show', 'experiences.index', 'experiences.show',
        'partners.public', 'partners.show', 'partners.public.resolver', 'locations.show',
        'partner.index', 'partner.portal.show', 'partner.benefits.index', 'partner.experiences.index',
        'partner.reservations.index', 'partner.unlocks.index', 'partner.challenges.index',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('support_session_id');
        if (! $id) {
            return $next($request);
        }
        $actor = Auth::guard('web')->user();
        $support = SupportSession::find($id);
        $bound = $support && $actor && $support->actor_user_id === $actor->id
            && hash_equals($support->session_hash, hash('sha256', $request->session()->getId()));
        $valid = $bound && app(SupportAccess::class)->allows($actor);
        if (! $valid || $support->ended_at->isPast() || $support->expires_at->isPast()) {
            if ($bound && $support->expires_at->isFuture() && $support->end_reason === 'expired') {
                $support->update(['ended_at' => now(), 'end_reason' => 'revoked']);
            }
            $request->session()->forget('support_session_id');
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();

            return redirect('/admin', 303)->with('error', 'El modo soporte terminó.');
        }
        $request->attributes->set('support_session', $support);
        $support->displayLabel = (User::find($support->target_user_id)?->name ?? 'Cuenta #'.$support->target_user_id).($support->partner_id ? ' / '.(Partner::find($support->partner_id)?->name ?? 'Partner #'.$support->partner_id) : '');
        if ($request->routeIs('support.destroy') && $request->isMethod('POST')) {
            return $next($request);
        }
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || ! $request->routeIs(...self::SCREENS)) {
            return $this->limited($support);
        }
        $target = User::find($support->target_user_id);
        if (! $target || $target->is_admin || $target->is_superadmin) {
            return $this->limited($support);
        }
        $partner = $support->partner_id ? Partner::find($support->partner_id) : null;
        if ($support->partner_id && (! $partner || ! $target->managesPartner($partner->id))) {
            return $this->limited($support);
        }
        $routePartner = $request->route('partner');
        $routePartnerId = $routePartner instanceof Partner ? $routePartner->id : Partner::where('slug', $routePartner)->value('id');
        if ($request->routeIs('partner.*') && $support->partner_id && $routePartner && $routePartnerId !== $support->partner_id) {
            return $this->limited($support);
        }
        // Never log in as the target: the persistent guard session continues to identify the actor.
        $sessionSnapshot = $request->session()->all();
        Auth::guard('web')->setUser($target);
        $request->setUserResolver(fn () => $target);
        $connection = DB::connection();
        $transactionLevel = $connection->transactionLevel();
        try {
            $connection->beginTransaction();
            $connection->statement('SET TRANSACTION READ ONLY');
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                return $this->limited($support);
            }
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        } catch (QueryException $e) {
            if ($e->getCode() !== '25006') {
                throw $e;
            }

            return $this->limited($support);
        } finally {
            $connection->rollBack($transactionLevel);
            $request->session()->replace($sessionSnapshot);
            Auth::guard('web')->setUser($actor);
            $request->setUserResolver(fn () => $actor);
        }
    }

    private function limited(SupportSession $support): Response
    {
        return response()->view('support-limited', ['support' => $support], 403)
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
