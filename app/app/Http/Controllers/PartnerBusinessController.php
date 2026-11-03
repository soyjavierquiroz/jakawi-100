<?php
namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Location;
use Illuminate\Http\Request;

/** Reuse the existing business editor, validations and media pipeline. */
class PartnerBusinessController extends Controller
{
    public const PARTNER_FIELDS = ['name', 'entity_type', 'partner_type', 'legal_name', 'tax_id', 'category', 'description', 'phone', 'whatsapp', 'email', 'contact_name', 'contact_phone', 'contact_email', 'instagram', 'tiktok', 'facebook', 'website'];
    public const LOCATION_FIELDS = ['name', 'slug', 'location_type', 'city_id', 'zone', 'country_code', 'region', 'address', 'address_reference', 'latitude', 'longitude', 'phone', 'whatsapp', 'email', 'website', 'instagram', 'facebook', 'tiktok', 'manager_name', 'manager_phone', 'manager_email', 'maps_url', 'google_place_id', 'timezone', 'opening_hours', 'is_primary'];

    private function context(Request $request, Partner $partner, ?Location $location = null): void
    {
        abort_unless($request->attributes->get('partner_access')['can_edit_content'] ?? false, 403);
        if ($location) abort_unless($location->partner_id === $partner->id, 404);
        $request->attributes->set('partner_editor', $partner);
    }

    public function profile(Request $request, Partner $partner)
    {
        $this->context($request, $partner);
        return app(AdminController::class)->partnerForm($partner);
    }

    public function logo(Request $request, Partner $partner)
    {
        $this->context($request, $partner);
        return app(AdminController::class)->partnerLogoSource($partner);
    }

    public function saveProfile(Request $request, Partner $partner)
    {
        $this->context($request, $partner);
        return app(AdminController::class)->savePartner($request, $partner);
    }

    public function location(Request $request, Partner $partner, ?Location $location = null)
    {
        $this->context($request, $partner, $location);
        return app(AdminController::class)->locationForm($request, $location);
    }

    public function saveLocation(Request $request, Partner $partner, ?Location $location = null)
    {
        $this->context($request, $partner, $location);
        return app(AdminController::class)->saveLocation($request, $location);
    }
}
