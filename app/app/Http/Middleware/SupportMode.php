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
use Illuminate\Support\Str;
use Inertia\Inertia;
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

    private const EDITORIAL = [
        'GET' => ['partner.performance', 'partner.profile.edit', 'partner.profile.logo', 'partner.locations.create', 'partner.locations.edit',
            'partner.benefits.create', 'partner.benefits.edit', 'partner.experiences.create', 'partner.experiences.edit',
            'partner.challenges.create', 'partner.challenges.edit', 'partner.challenges.preview',
            'partner.unlocks.create', 'partner.unlocks.edit'],
        'POST' => ['media.uploads', 'partner.locations.store', 'partner.benefits.store', 'partner.benefits.submit',
            'partner.experiences.store', 'partner.experiences.submit', 'partner.experiences.sessions.store',
            'partner.challenges.store', 'partner.challenges.submit', 'partner.challenges.image', 'partner.unlocks.store', 'partner.unlocks.submit'],
        'PUT' => ['partner.profile.update', 'partner.locations.update', 'partner.benefits.update', 'partner.experiences.update',
            'partner.experiences.sessions.update', 'partner.challenges.update', 'partner.unlocks.update'],
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
            $request->session()->forget(['support_session_id', 'support.oidc_continuations']);
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();

            return redirect('/admin', 303)->with('error', 'El modo soporte terminó.');
        }
        $request->attributes->set('support_session', $support);
        $support->displayLabel = (User::find($support->target_user_id)?->name ?? 'Cuenta #'.$support->target_user_id).($support->partner_id ? ' / '.(Partner::find($support->partner_id)?->name ?? 'Partner #'.$support->partner_id) : '');
        if ($request->routeIs('support.destroy', 'support.club', 'support.continue-club') && $request->isMethod('POST')) {
            return $next($request);
        }
        if ($request->isMethod('GET') && $request->routeIs('passport.authorizations.authorize')) {
            if ($request->header('X-Inertia')) {
                return Inertia::location('/oauth/authorize?'.http_build_query($request->query(), '', '&', PHP_QUERY_RFC3986));
            }
            $continuation = null;
            if (app(ValidateOidcAuthorization::class)->queryError($request->query()) === null) {
                $continuation = (string) Str::uuid();
                $pending = $request->session()->get('support.oidc_continuations', []);
                $pending[$continuation] = ['support_id' => $support->id, 'actor_id' => $actor->id, 'expires_at' => $support->expires_at->timestamp, 'query' => $request->query()];
                $request->session()->put('support.oidc_continuations', array_slice($pending, -5, null, true));
            }

            return response()->view('support-limited', ['support' => $support, 'continuation' => $continuation], 403)
                ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }
        $managing = $support->mode === 'manage' && $support->partner_id;
        $editorial = $managing && $request->routeIs(...(self::EDITORIAL[$request->method()] ?? []));
        if (! $editorial && (! in_array($request->method(), ['GET', 'HEAD'], true) || ! $request->routeIs(...self::SCREENS))) {
            return $this->limited($support);
        }
        $target = User::find($support->target_user_id);
        if (! $target || (($target->is_admin || $target->is_superadmin) && $target->id !== $actor->id)) {
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
        if ($managing) {
            abort_unless($partner && $target->partners()->whereKey($partner->id)->whereIn('partner_user.role', ['owner', 'manager'])->exists(), 403);
            // No user/guard substitution in management: the real admin remains the actor.
            if ($request->routeIs('partner.index')) {
                return to_route('partner.portal.show', $partner);
            }
            if (! $request->routeIs('partner.*', 'media.uploads')) {
                return $this->limited($support);
            }
            return $this->manage($request, $next, $support, $actor, $editorial && ! $request->isMethodSafe());
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

    private function manage(Request $request, Closure $next, SupportSession $support, User $actor, bool $write): Response
    {
        $connection = DB::connection();
        $level = $connection->transactionLevel();
        try {
            $connection->beginTransaction();
            if (! $write) {
                $connection->statement('SET TRANSACTION READ ONLY');
            }
            $request->attributes->set('support_editorial_write', $write);
            $response = $next($request);
            if ($write && $response->getStatusCode() < 400 && ! ($response->exception ?? null)) {
                \App\Models\AuditLog::create(['actor_user_id' => $actor->id, 'action' => 'support_partner_action',
                    'subject_type' => Partner::class, 'subject_id' => $support->partner_id,
                    'metadata' => ['support_session_id' => $support->id, 'partner_id' => $support->partner_id,
                        'route' => $request->route()->getName(), 'location_ids' => $request->input('location_ids'), 'records' => collect($request->route()->parameters())->map(fn ($value) => $value instanceof \Illuminate\Database\Eloquent\Model ? ['type' => $value::class, 'id' => $value->getKey()] : $value)->all()]]);
                $connection->commit();
                if ($response->isRedirection() && ! $request->session()->has('success')) $request->session()->flash('success', 'Cambios guardados correctamente.');
            }
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            return $response;
        } finally {
            if ($connection->transactionLevel() > $level) {
                $connection->rollBack($level);
            }
            $request->attributes->remove('support_editorial_write');
        }
    }

    private function limited(SupportSession $support): Response
    {
        return response()->view('support-limited', ['support' => $support], 403)
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
