<?php
namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Benefit;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Model;
use App\Models\LandingPresentation;
use App\Services\LandingPresentationDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminLandingPresentationController extends Controller
{
    public function index(Request $request, string $subjectSlug)
    {
        $subject = $this->subject($request);
        return Inertia::render('admin/landing-presentations/index', [
            'subject'=>$subject->only(['title','slug']),
            'baseUrl'=>$this->baseUrl($subject),
            'productUrl'=>$subject instanceof Experience ? route('admin.experiences.edit', $subject) : ($subject instanceof Benefit ? route('admin.benefits.edit', $subject) : '/admin/retos/'.$subject->slug),
            'presentations'=>$subject->landingPresentations()->orderBy('id')->get(),
        ]);
    }

    public function save(Request $request, string $subjectSlug, ?LandingPresentation $presentation = null)
    {
        $subject = $this->subject($request);
        if ($presentation) $this->belongsTo($subject, $presentation);
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
        // Editorial fields may change tone; quantities and rules remain on the product.
        $presentation ? $presentation->update($data) : $subject->landingPresentations()->create($data + ['status'=>'DRAFT','default_scope'=>'NONE']);
        return redirect($this->baseUrl($subject));
    }

    public function publish(Request $request, string $subjectSlug, LandingPresentation $presentation)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject,$presentation);
        if (!($subject instanceof Experience ? $subject->isPublished() : ($subject instanceof Benefit ? $subject->isPublished() && $subject->partner?->isPublished() : $subject->isPublic()))) throw ValidationException::withMessages(['presentation'=>'Publica el producto y su partner antes de publicar su landing.']);
        $presentation->update(['status'=>'PUBLISHED']);
        return back();
    }

    public function archive(Request $request, string $subjectSlug, LandingPresentation $presentation)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject,$presentation);
        DB::transaction(function () use ($subject,$presentation) {
            $subject->newQuery()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $presentation->update(['status'=>'ARCHIVED','default_scope'=>'NONE']);
        });
        return back();
    }

    public function useDefault(Request $request, string $subjectSlug, LandingPresentation $presentation, LandingPresentationDefaults $defaults)
    {
        $subject = $this->subject($request);
        $this->belongsTo($subject,$presentation);
        $data = $request->validate(['default_scope'=>['required',Rule::in(['GUESTS','ALL'])]]);
        $defaults->choose($subject,$presentation,$data['default_scope']);
        return back();
    }

    public function productDefault(Request $request, string $subjectSlug, LandingPresentationDefaults $defaults)
    {
        $subject = $this->subject($request);
        $defaults->choose($subject,null,'NONE');
        return back();
    }

    private function subject(Request $request): Model
    {
        return $request->route()->hasParameter('experience') ? Experience::where('slug', $request->route('experience'))->firstOrFail() : ($request->route()->hasParameter('benefit') ? Benefit::where('slug', $request->route('benefit'))->firstOrFail() : Challenge::where('slug', $request->route('challenge'))->firstOrFail());
    }

    private function baseUrl(Model $subject): string
    {
        return '/admin/'.($subject instanceof Experience ? 'experiencias' : ($subject instanceof Benefit ? 'beneficios' : 'retos')).'/'.$subject->slug.'/landings';
    }

    private function belongsTo(Model $subject, LandingPresentation $presentation): void
    {
        abort_unless($presentation->subject_type === $subject->getMorphClass() && $presentation->subject_id === $subject->id,404);
    }
}
