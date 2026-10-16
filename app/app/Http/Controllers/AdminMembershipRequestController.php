<?php

namespace App\Http\Controllers;

use App\Models\MembershipPurchaseRequest;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/membership-requests/index', [
            'requests' => \App\Support\ListingPage::make(MembershipPurchaseRequest::query()->when(request('q'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where(fn ($u) => $u->where('name', 'ilike', '%'.addcslashes(request('q'), '\\%_').'%')->orWhere('email', 'ilike', '%'.addcslashes(request('q'), '\\%_').'%'))))->when(request('status'), fn ($q) => $q->where('status', request('status')))->with('user:id,name,email')->latest('requested_at'), 25),
        ]);
    }

    public function show(MembershipPurchaseRequest $purchaseRequest): Response
    {
        $purchaseRequest->load(['user.profile', 'attributionTouch']);
        return Inertia::render('admin/membership-requests/show', [
            'purchaseRequest' => [
                'id' => $purchaseRequest->id, 'status' => $purchaseRequest->status,
                'requested_at' => $purchaseRequest->requested_at,
                'user' => $purchaseRequest->user->only(['id', 'name', 'email']),
                'whatsapp' => $purchaseRequest->user->profile?->whatsapp,
                'journey' => $purchaseRequest->journey_type,
                'action' => $purchaseRequest->journey_action,
                'resource_id' => $purchaseRequest->journey_resource_id,
                'campaign_key' => $purchaseRequest->attributionTouch?->campaign_key,
                'utm_source' => $purchaseRequest->attributionTouch?->utm_source,
                'utm_campaign' => $purchaseRequest->attributionTouch?->utm_campaign,
            ],
        ]);
    }
}
