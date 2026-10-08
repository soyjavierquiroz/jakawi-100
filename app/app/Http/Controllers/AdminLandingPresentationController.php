<?php
namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\LandingPresentation;
use App\Services\LandingPresentationDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminLandingPresentationController extends Controller
{
    public function index(Challenge $challenge)
    {
        return Inertia::render('admin/landing-presentations/index', [
            'challenge'=>$challenge->only(['title','slug']),
            'presentations'=>$challenge->landingPresentations()->orderBy('id')->get(),
        ]);
    }

    public function save(Request $request, Challenge $challenge, ?LandingPresentation $presentation = null)
    {
        if ($presentation) $this->belongsTo($challenge, $presentation);
        $data = $request->validate([
            'name'=>['required','string','max:255'],
            'slug'=>['required','alpha_dash','max:255',Rule::unique('landing_presentations','slug')->ignore($presentation?->id)],
            'campaign_key'=>['nullable','string','max:255','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'eyebrow'=>['nullable','string','max:100'],
            'headline'=>['nullable','string','max:180','regex:/^[^0-9]*$/u'],
            'subheadline'=>['nullable','string','max:1000','regex:/^[^0-9]*$/u'],
            'hero_path'=>['nullable','string','max:2048'], 'hero_alt'=>['nullable','string','max:255'],
            'reward_display_override'=>['nullable','string','max:180','regex:/^[^0-9]*$/u'],
            'final_cta_headline'=>['nullable','string','max:180','regex:/^[^0-9]*$/u'],
        ]);
        // Editorial fields may change tone; quantities and rules remain on the Challenge.
        $presentation ? $presentation->update($data) : $challenge->landingPresentations()->create($data + ['status'=>'DRAFT','is_default'=>false]);
        return to_route('admin.landing-presentations.index', $challenge);
    }

    public function publish(Challenge $challenge, LandingPresentation $presentation)
    {
        $this->belongsTo($challenge,$presentation);
        if (!$challenge->isPublic()) throw ValidationException::withMessages(['presentation'=>'Publica el reto antes de publicar su landing.']);
        $presentation->update(['status'=>'PUBLISHED']);
        return back();
    }

    public function archive(Challenge $challenge, LandingPresentation $presentation, LandingPresentationDefaults $defaults)
    {
        $this->belongsTo($challenge,$presentation);
        DB::transaction(function () use ($challenge,$presentation,$defaults) {
            if ($presentation->fresh()->is_default) $defaults->choose($challenge,null);
            $presentation->update(['status'=>'ARCHIVED','is_default'=>false]);
        });
        return back();
    }

    public function useDefault(Challenge $challenge, LandingPresentation $presentation, LandingPresentationDefaults $defaults)
    {
        $this->belongsTo($challenge,$presentation);
        $defaults->choose($challenge,$presentation);
        return back();
    }

    public function productDefault(Challenge $challenge, LandingPresentationDefaults $defaults)
    {
        $defaults->choose($challenge,null);
        return back();
    }

    private function belongsTo(Challenge $challenge, LandingPresentation $presentation): void
    {
        abort_unless($presentation->subject_type === $challenge->getMorphClass() && $presentation->subject_id === $challenge->id,404);
    }
}
