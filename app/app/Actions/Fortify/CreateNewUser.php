<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AnalyticsTracker;
use App\Services\AttributionService;
use App\Services\Crm\CrmContactProjectionService;
use App\Services\GrowthMeasurementService;
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

        $contextual = isset(app(\App\Services\PublicJourneyContinuation::class)->get()['landing_presentation_id'])
            || request()->session()->has('partner_profile_return');
        if ($contextual && is_string($input['email']) && User::whereRaw('lower(email) = ?', [$input['email']])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'YA TIENES JAKAWI. Inicia sesión para continuar.']);
        }
        Validator::make([...$input, 'whatsapp' => $whatsapp], [
            ...$this->profileRules(),
            'whatsapp' => ['required', 'string', 'max:16'],
            'referral_code' => ['nullable', 'string', 'max:64'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ], ['whatsapp.required' => 'Ingresa un número de WhatsApp válido.', 'email.unique' => 'YA TIENES JAKAWI. Inicia sesión para continuar.'])->validate();
        /** @var Request $request */
        $request = app(Request::class);
        $user = DB::transaction(function () use ($input, $request, $whatsapp, $contextual): User {
            if ($contextual) {
                // Serialize normalized phone identity checks without adding acquisition schema.
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$whatsapp]);
                if (\App\Models\UserProfile::where('whatsapp', $whatsapp)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['whatsapp' => 'YA TIENES JAKAWI. Inicia sesión o recupera tu acceso para continuar.']);
                }
            }
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Str::password(64),
                'marketing_opt_in' => filter_var($input['marketing_opt_in'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'marketing_opt_in_at' => now(),
                'marketing_opt_in_source' => 'register',
            ]);
            $user->profile()->create(['whatsapp' => $whatsapp]);
            app(ReferralCodeService::class)->ensureFor($user);
            app(AttributionService::class)->associateRegisteredUser($user, $request, $input['referral_code'] ?? null);
            app(OwnershipAssignmentService::class)->autoAssignUserFromReferral($user);
            app(CrmContactProjectionService::class)->signal($user, 'signup_completed', $user->marketing_opt_in ? 'GRANT' : 'REVOKE');

            return $user;
        });
        if (filled($input['referral_code'] ?? null)) {
            app(AnalyticsTracker::class)->record('referral_code_entered');
        }
        app(GrowthMeasurementService::class)->signupCompleted($user);
        if ($contextual) {
            $request->session()->put('acquisition_new_user_id', $user->id);
            try {
                $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
                $user->notify(new \App\Notifications\SecureAccountAccess($token));
                $request->session()->put('acquisition_access_email_queued', true);
            } catch (\Throwable $exception) {
                report($exception);
                $request->session()->put('acquisition_access_email_queued', false);
            }
        }

        return $user;
    }
}
