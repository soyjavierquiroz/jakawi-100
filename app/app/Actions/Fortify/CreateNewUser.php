<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\ReferralCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'referral_code' => ['nullable', 'string', 'max:64'],
        ])->validate();
        /** @var Request $request */
        $request = app(Request::class);
        $user = DB::transaction(function () use ($input, $request): User {
            $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            ]);
            app(ReferralCodeService::class)->ensureFor($user);
            app(AttributionService::class)->associateRegisteredUser($user, $request, $input['referral_code'] ?? null);
            return $user;
        });
        if (filled($input['referral_code'] ?? null)) app(AnalyticsTracker::class)->record('referral_code_entered');
        app(AnalyticsTracker::class)->record('signup_completed', ['user_id' => $user->id]);
        return $user;
    }
}
