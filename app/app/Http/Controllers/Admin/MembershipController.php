<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateMembershipRequest;
use App\Http\Requests\Admin\CancelMembershipRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with(['activeMembership', 'memberships' => fn ($query) => $query->latest()])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/memberships/index', [
            'users' => $users->through(fn (User $user) => $this->serializeUser($user)),
            'filters' => ['search' => $search],
            'membershipConfig' => [
                'price_bob' => config('jakawi.membership.price_bob'),
                'duration_days' => config('jakawi.membership.duration_days'),
            ],
            'paymentMethods' => ['cash', 'bank_transfer', 'qr', 'other'],
        ]);
    }

    public function store(ActivateMembershipRequest $request): RedirectResponse
    {
        $created = DB::transaction(function () use ($request): bool {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($request->integer('user_id'));

            if ($user->hasActiveMembership()) {
                return false;
            }

            $startsAt = now();

            Membership::query()->create([
                'user_id' => $user->id,
                'status' => Membership::STATUS_ACTIVE,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays((int) config('jakawi.membership.duration_days')),
                'amount_paid' => $request->filled('amount_paid')
                    ? $request->input('amount_paid')
                    : config('jakawi.membership.price_bob'),
                'payment_method' => $request->input('payment_method'),
                'payment_reference' => $request->input('payment_reference'),
                'notes' => $request->input('notes'),
                'activated_by' => $request->user()->id,
            ]);

            return true;
        });

        if (! $created) {
            return back()->with('error', 'Este usuario ya tiene una membresia activa.');
        }

        return back()->with('success', 'Membresia activada.');
    }

    public function cancel(CancelMembershipRequest $request, Membership $membership): RedirectResponse
    {
        if (! Membership::query()->active()->whereKey($membership->id)->exists()) {
            return back()->with('error', 'Esta membresia no esta activa.');
        }

        $membership->forceFill(['status' => Membership::STATUS_CANCELLED])->save();

        return back()->with('success', 'Membresia cancelada.');
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $user): array
    {
        $activeMembership = $user->activeMembership;
        $latestMembership = $activeMembership ?? $user->memberships->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'membership' => $latestMembership ? [
                'id' => $latestMembership->id,
                'status' => $activeMembership ? Membership::STATUS_ACTIVE : $latestMembership->status,
                'starts_at' => $latestMembership->starts_at?->toDateTimeString(),
                'ends_at' => $latestMembership->ends_at?->toDateTimeString(),
                'amount_paid' => $latestMembership->amount_paid,
                'is_active' => (bool) $activeMembership,
            ] : null,
        ];
    }
}
