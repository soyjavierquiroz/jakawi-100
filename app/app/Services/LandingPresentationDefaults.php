<?php
namespace App\Services;

use App\Models\LandingPresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandingPresentationDefaults
{
    public function choose(Model $subject, ?LandingPresentation $presentation): void
    {
        DB::transaction(function () use ($subject, $presentation) {
            $subject::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
            if ($presentation && ($presentation->subject_type !== $subject->getMorphClass() || $presentation->subject_id !== $subject->getKey() || $presentation->status !== 'PUBLISHED')) {
                throw ValidationException::withMessages(['presentation' => 'Solo una presentación publicada de este producto puede ser predeterminada.']);
            }
            LandingPresentation::query()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->where('is_default', true)->update(['is_default' => false]);
            if ($presentation) LandingPresentation::query()->whereKey($presentation->id)->update(['is_default' => true]);
        });
    }
}
