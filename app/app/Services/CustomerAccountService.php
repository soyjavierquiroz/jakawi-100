<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CustomerAccountService
{
    /** Creates an account with an unshared random secret and sends Fortify's reset flow. */
    public function create(User $actor, string $name, string $email): User
    {
        $user = DB::transaction(function () use ($actor, $name, $email): User {
            $user = User::create(['name' => $name, 'email' => Str::lower(trim($email)), 'password' => Str::password(64)]);
            app(ReferralCodeService::class)->ensureFor($user);
            AuditLog::create(['actor_user_id' => $actor->id, 'action' => 'customer_created_by_sales_agent', 'subject_type' => User::class, 'subject_id' => $user->id, 'metadata' => ['email' => $user->email]]);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }
}
