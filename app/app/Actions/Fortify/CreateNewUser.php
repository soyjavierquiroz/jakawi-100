<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\OwnershipAssignmentService;
use App\Services\PhoneNormalizer;
use App\Services\ReferralCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['email'] = isset($input['email']) && is_string($input['email']) ? Str::lower(trim($input['email'])) : ($input['email'] ?? null);
        $whatsapp = isset($input['whatsapp']) && is_string($input['whatsapp'])
            ? app(PhoneNormalizer::class)->whatsapp($input['whatsapp']) : null;

        Validator::make([...$input, 'whatsapp' => $whatsapp], [
            ...$this->profileRules(),
            'whatsapp' => ['required', 'string', 'max:16'],
            'referral_code' => ['nullable', 'string', 'max:64'],
        ], ['whatsapp.required' => 'Ingresa un número de WhatsApp válido.'])->validate();
        /** @var Request $request */
        $request = app(Request::class);
        $user = DB::transaction(function () use ($input, $request, $whatsapp): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Str::password(64),
            ]);
            $user->profile()->create(['whatsapp' => $whatsapp]);
            app(ReferralCodeService::class)->ensureFor($user);
            app(AttributionService::class)->associateRegisteredUser($user, $request, $input['referral_code'] ?? null);
            app(OwnershipAssignmentService::class)->autoAssignUserFromReferral($user);
            return $user;
        });
        if (filled($input['referral_code'] ?? null)) app(AnalyticsTracker::class)->record('referral_code_entered');
        app(\App\Services\GrowthMeasurementService::class)->signupCompleted($user);
        return $user;
    }
}
