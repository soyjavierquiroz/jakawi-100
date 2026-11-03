<?php

namespace App\Providers;

use App\Integrations\ShareContest\Inspector;
use App\Integrations\ShareContest\ShareContestClient;
use App\Models\ChallengeParticipation;
use App\Models\ExperienceReservation;
use App\Models\Membership;
use App\Models\MembershipPurchaseRequest;
use App\Models\PartnerApplication;
use App\Models\Redemption;
use App\Models\UnlockParticipation;
use App\Models\User;
use App\Models\UserProfile;
use App\Observers\CrmOutcomeObserver;
use App\Observers\GrowthOutcomeObserver;
use App\Payments\Qr\Contracts\QrPaymentGateway;
use App\Payments\Qr\Exceptions\UnsafeQrPaymentConfiguration;
use App\Payments\Qr\Providers\DisabledQrPaymentGateway;
use App\Payments\Qr\Providers\FakeQrPaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Inspector::class, ShareContestClient::class);
        $this->app->singleton(QrPaymentGateway::class, function (): QrPaymentGateway {
            $driver = config('payments.qr.driver', 'disabled');

            if ($driver === 'fake') {
                if ($this->app->isProduction()) {
                    throw new UnsafeQrPaymentConfiguration('QR_PAYMENT_DRIVER=fake is not allowed in production.');
                }

                return new FakeQrPaymentGateway;
            }

            if ($driver === 'disabled') {
                return new DisabledQrPaymentGateway;
            }

            throw new UnsafeQrPaymentConfiguration("Unsupported QR payment driver [{$driver}].");
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        foreach ([\App\Models\Partner::class, \App\Models\Location::class, \App\Models\Benefit::class,
            \App\Models\Experience::class, \App\Models\ExperienceSession::class, \App\Models\Challenge::class, \App\Models\Unlock::class] as $editorialModel) {
            $editorialModel::saved(function ($model) {
                $request = request();
                $support = $request->attributes->get('support_session');
                if (! $support || ! $request->attributes->get('support_editorial_write')) return;
                $changes = $model->getChanges();
                if ($model->wasRecentlyCreated) $changes = $model->getAttributes();
                unset($changes['redemption_pin_hash']);
                if (! $changes) return;
                \App\Models\AuditLog::create(['actor_user_id' => $support->actor_user_id,
                    'action' => 'support_partner_record_saved', 'subject_type' => $model::class, 'subject_id' => $model->getKey(),
                    'metadata' => ['support_session_id' => $support->id, 'partner_id' => $support->partner_id,
                        'route' => $request->route()->getName(), 'before' => \Illuminate\Support\Arr::only($model->getRawOriginal(), array_keys($changes)), 'after' => $changes]]);
            });
        }
        Redemption::observe(\App\Observers\CommercialRedemptionObserver::class);
        foreach ([User::class, UserProfile::class, PartnerApplication::class,
            MembershipPurchaseRequest::class, Membership::class, Redemption::class,
            ExperienceReservation::class, ChallengeParticipation::class, UnlockParticipation::class] as $model) {
            $model::observe(CrmOutcomeObserver::class);
        }
        foreach ([MembershipPurchaseRequest::class, Membership::class,
            Redemption::class, ExperienceReservation::class,
            ChallengeParticipation::class, UnlockParticipation::class] as $model) {
            $model::observe(GrowthOutcomeObserver::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('redemption-validator', fn ($request) => [
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
