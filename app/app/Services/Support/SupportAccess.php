<?php

namespace App\Services\Support;

use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportAccess
{
    public function allows(?User $actor): bool
    {
        return $actor && Gate::forUser($actor)->allows('create', SupportSession::class);
    }

    public function fresh(Request $request): bool
    {
        $stamp = $request->session()->get('support.reauthenticated_at');

        return $request->session()->get('support.reauthenticated_user_id') === $request->user()?->id
            && is_int($stamp) && $stamp >= time() - 300 && $stamp <= time();
    }
}
