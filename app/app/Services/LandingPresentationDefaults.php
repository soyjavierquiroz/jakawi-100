<?php
namespace App\Services;

use App\Models\LandingPresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandingPresentationDefaults
{
    public function choose(Model $subject, ?LandingPresentation $presentation, string $scope): void
    {
        DB::transaction(function () use ($subject, $presentation, $scope) {
            $subject::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
            if (!in_array($scope, LandingPresentation::SCOPES, true) || ($scope !== 'NONE' && !$presentation)) {
                throw ValidationException::withMessages(['default_scope' => 'Selecciona un alcance válido.']);
            }
            $current = $presentation ? LandingPresentation::query()->whereKey($presentation->id)->lockForUpdate()->firstOrFail() : null;
            if ($current && ($current->subject_type !== $subject->getMorphClass() || $current->subject_id !== $subject->getKey() || $current->status !== 'PUBLISHED')) {
                throw ValidationException::withMessages(['presentation' => 'Solo una presentación publicada de este producto puede ser predeterminada.']);
            }
            LandingPresentation::query()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->where('default_scope', '<>', 'NONE')->update(['default_scope' => 'NONE']);
            if ($current && $scope !== 'NONE') LandingPresentation::query()->whereKey($current->id)->update(['default_scope' => $scope]);
        });
    }
}
