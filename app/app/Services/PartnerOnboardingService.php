<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerOnboardingService
{
    public function convert(PartnerApplication $application, User $admin, array $data): Partner
    {
        abort_unless($admin->is_admin, 403);

        return DB::transaction(function () use ($application, $admin, $data): Partner {
            $application = PartnerApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->partner_id !== null) {
                return $application->partner()->firstOrFail();
            }
            abort_unless($application->status === PartnerApplication::APPROVED, 409, 'Primero aprueba la solicitud.');
            // Locking the application serializes retries; slug uniqueness also
            // protects different applications from claiming the same public URL.
            if (Partner::where('slug', $data['slug'])->exists()) {
                throw ValidationException::withMessages(['slug' => 'Este slug ya pertenece a otro Partner.']);
            }
            try {
                $partner = Partner::create([
                    'name' => $data['name'], 'slug' => $data['slug'],
                    'entity_type' => 'organization', 'partner_type' => 'business',
                    'category' => $data['category'] ?? null,
                    'contact_name' => $application->contact_name,
                    'contact_email' => $application->contact_email,
                    'contact_phone' => $application->contact_phone,
                    'status' => 'draft',
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'Este slug ya pertenece a otro Partner.']);
            }
            $application->update(['partner_id' => $partner->id]);
            AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'partner_application_converted',
                'subject_type' => PartnerApplication::class, 'subject_id' => $application->id,
                'metadata' => ['partner_id' => $partner->id, 'city_id' => $application->city_id]]);

            return $partner;
        });
    }

    public function assignOwner(PartnerApplication $application, User $admin, int $ownerId, string $reason): void
    {
        abort_unless($admin->is_admin, 403);
        DB::transaction(function () use ($application, $admin, $ownerId, $reason): void {
            $application = PartnerApplication::lockForUpdate()->findOrFail($application->id);
            abort_unless($application->partner_id, 409, 'Primero convierte la solicitud en Partner.');
            $partner = Partner::lockForUpdate()->findOrFail($application->partner_id);
            $owner = User::findOrFail($ownerId);
            $before = $partner->users()->whereKey($owner->id)->first()?->pivot->role;
            if ($before === 'owner') {
                return;
            }
            // Never derive ownership from the contact email or application user.
            // This explicit admin grant does not change credentials/verification.
            $partner->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
            AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'partner_owner_authorized',
                'subject_type' => Partner::class, 'subject_id' => $partner->id,
                'metadata' => ['application_id' => $application->id, 'owner_user_id' => $owner->id,
                    'before_role' => $before, 'after_role' => 'owner', 'reason' => $reason]]);
        });
    }
}
