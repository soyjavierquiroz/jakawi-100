<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\CityInterest;
use App\Models\PartnerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cities/index', [
            'cities' => City::orderByDesc('priority')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cities/form', ['statuses' => City::statuses()]);
    }

    public function store(Request $request)
    {
        $city = City::create($this->validatedCity($request));

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'city_created',
            'subject_type' => City::class,
            'subject_id' => $city->id,
            'metadata' => ['before' => null, 'after' => $this->attributes($city)],
        ]);

        return to_route('admin.cities.show', $city);
    }

    public function show(City $city): Response
    {
        $interests = CityInterest::query()->where('city_id', $city->id)->with('user:id,name,email')->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/cities/show', [
            'city' => $city,
            'interestSummary' => [
                'total' => $interests->total(),
                'registered' => CityInterest::query()->where('city_id', $city->id)->whereNotNull('user_id')->count(),
                'guests' => CityInterest::query()->where('city_id', $city->id)->whereNull('user_id')->count(),
            ],
            'interests' => $interests,
            'partnerApplicationSummary' => [
                'total' => PartnerApplication::where('city_id', $city->id)->count(),
                'submitted' => PartnerApplication::where('city_id', $city->id)->where('status', PartnerApplication::SUBMITTED)->count(),
                'qualified' => PartnerApplication::where('city_id', $city->id)->where('status', PartnerApplication::QUALIFIED)->count(),
                'approved' => PartnerApplication::where('city_id', $city->id)->where('status', PartnerApplication::APPROVED)->count(),
            ],
        ]);
    }

    public function edit(City $city): Response
    {
        return Inertia::render('admin/cities/form', ['city' => $city, 'statuses' => City::statuses()]);
    }

    public function update(Request $request, City $city)
    {
        $before = $this->attributes($city);
        $city->update($this->validatedCity($request, $city, false));

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'city_updated',
            'subject_type' => City::class,
            'subject_id' => $city->id,
            'metadata' => ['before' => $before, 'after' => $this->attributes($city->fresh())],
        ]);

        return to_route('admin.cities.show', $city);
    }

    public function updateStatus(Request $request, City $city)
    {
        $data = $request->validate(['status' => ['required', 'string', Rule::in(City::statuses())]]);
        $previous = $city->status;
        $city->update(['status' => $data['status']]);

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'city_status_changed',
            'subject_type' => City::class,
            'subject_id' => $city->id,
            'metadata' => [
                'before' => ['status' => $previous],
                'after' => ['status' => $city->status],
                'previous_status' => $previous,
                'new_status' => $city->status,
            ],
        ]);

        return to_route('admin.cities.show', $city);
    }

    /** @return array<string, mixed> */
    private function validatedCity(Request $request, ?City $city = null, bool $includeStatus = true): array
    {
        $request->merge(['country_code' => Str::upper((string) $request->input('country_code'))]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cities', 'slug')->ignore($city)],
            'region' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'priority' => ['required', 'integer'],
        ];

        if ($includeStatus) {
            $rules['status'] = ['required', 'string', Rule::in(City::statuses())];
        }

        return $request->validate($rules);
    }

    /** @return array<string, mixed> */
    private function attributes(City $city): array
    {
        return $city->only(['name', 'slug', 'region', 'country_code', 'status', 'priority']);
    }
}
