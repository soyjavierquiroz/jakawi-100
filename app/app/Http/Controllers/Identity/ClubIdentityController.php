<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityAuthority;
use App\Services\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClubIdentityController extends Controller
{
    public function store(Request $request, IdentityAuthority $authority): JsonResponse
    {
        $allowed = ['contract', 'handoff_id', 'source_app', 'club_user_id', 'name', 'email', 'whatsapp', 'email_activation_proof', 'consent'];
        if (array_diff(array_keys($request->all()), $allowed)) {
            return response()->json(['error' => 'UNKNOWN_FIELDS'], 422);
        }
        $data = $request->validate([
            'contract' => ['required', Rule::in([IdentityAuthority::CONTRACT])],
            'source_app' => ['required', Rule::in(['club'])],
            'handoff_id' => ['required', 'uuid', 'lowercase'],
            'club_user_id' => ['required', 'uuid', 'lowercase'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email_activation_proof' => ['nullable', 'string', 'max:8192'],
            'consent' => ['required', 'array:purpose,version,accepted_at'],
            'consent.purpose' => ['required', Rule::in(['identity_link'])],
            'consent.version' => ['required', Rule::in(['1'])],
            'consent.accepted_at' => ['required', 'date', 'before_or_equal:'.now()->addSeconds(30)->toISOString(), 'after:'.now()->subDay()->toISOString()],
        ]);
        $data['email'] = Str::lower(trim($data['email']));
        if (! empty($data['whatsapp'])) {
            $data['whatsapp'] = app(PhoneNormalizer::class)->whatsapp($data['whatsapp']);
            if ($data['whatsapp'] === null) {
                throw ValidationException::withMessages(['whatsapp' => 'Ingresa un número de WhatsApp válido.']);
            }
        }
        $result = $authority->handoff($data, hash('sha256', $request->getContent()));

        return response()->json($result, $result['status'] === 'CONFLICT' ? 409 : 200);
    }

    public function show(string $handoff, IdentityAuthority $authority): JsonResponse
    {
        return response()->json(json_decode($authority->getHandoff($handoff)->result, true));
    }

    public function link(string $clubUser, IdentityAuthority $authority): JsonResponse
    {
        $link = $authority->link($clubUser) ?? abort(404);

        return response()->json($authority->publicLink($link));
    }

    public function revoke(string $clubUser, IdentityAuthority $authority): JsonResponse
    {
        return response()->json($authority->revoke($clubUser));
    }
}
