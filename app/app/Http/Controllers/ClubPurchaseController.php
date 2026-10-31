<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractMembershipReceipt;
use App\Models\MembershipBankPayment;
use App\Models\User;
use App\Services\ClubPurchaseIdentity;
use App\Services\MembershipBankQr;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ClubPurchaseController extends Controller
{
    private function payment(string $reference): MembershipBankPayment
    {
        return MembershipBankPayment::where('origin', 'CLUB')->where('club_reference', $reference)->firstOrFail();
    }

    public function store(Request $r, ClubPurchaseIdentity $identity): JsonResponse
    {
        $d = $r->validate([
            'club_reference' => ['required', 'uuid', 'lowercase'], 'club_user_uuid' => ['nullable', 'uuid', 'lowercase'],
            'guest_purchase_id' => ['required_without:club_user_uuid', 'nullable', 'uuid', 'lowercase'],
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:40'], 'amount' => ['required', 'in:100,100.00'],
            'currency' => ['required', 'in:BOB'], 'origin' => ['required', 'in:CLUB'],
            'claim_secret' => ['required', 'string', 'min:32', 'max:128'],
        ]);
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($d, $hash, $identity) {
            $identity->lock($d['club_reference']);
            $p = MembershipBankPayment::where('club_reference', $d['club_reference'])->first();
            if ($p) {
                abort_unless(hash_equals($p->intent_hash, $hash), 409, 'INTENT_CONFLICT');

                return response()->json($this->result($p));
            }
            $p = MembershipBankPayment::create(['reference' => (string) Str::uuid(), 'checkout_event_id' => (string) Str::uuid(),
                'checkout_at' => now(), 'amount' => '100.00', 'origin' => 'CLUB', 'status' => 'awaiting_payment',
                'club_reference' => $d['club_reference'], 'club_user_uuid' => $d['club_user_uuid'] ?? null,
                'guest_purchase_id' => $d['guest_purchase_id'] ?? null, 'buyer_name' => $d['name'], 'buyer_email' => $d['email'],
                'buyer_phone' => $d['phone'], 'intent_hash' => $hash, 'claim_hash' => hash('sha256', $d['claim_secret']), 'identity_status' => 'pending']);

            return response()->json($this->result($p), 201);
        }, 3);
    }

    public function show(string $reference): JsonResponse
    {
        return response()->json($this->result($this->payment($reference)));
    }

    public function qr(MembershipBankQr $qr): Response
    {
        return $qr->response();
    }

    public function receipt(Request $r, string $reference, ClubPurchaseIdentity $identity): JsonResponse
    {
        $d = $r->validate(['content_base64' => ['required', 'string', 'max:13981016'], 'sha256' => ['required', 'regex:/\A[a-f0-9]{64}\z/']]);
        $bytes = base64_decode($d['content_base64'], true);
        abort_unless($bytes !== false && strlen($bytes) > 0 && strlen($bytes) <= 10485760 && hash_equals($d['sha256'], hash('sha256', $bytes)), 422, 'RECEIPT_INTEGRITY');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'application/pdf'], true), 422, 'RECEIPT_TYPE');
        $path = null;
        try {
            return DB::transaction(function () use ($identity, $reference, $d, $bytes, $mime, &$path) {
                $identity->lock($reference);
                $p = $this->payment($reference);
                $p = MembershipBankPayment::lockForUpdate()->findOrFail($p->id);
                if ($p->receipt_sha256 === $d['sha256']) {
                    return response()->json($this->result($p));
                }
                abort_unless(in_array($p->status, ['awaiting_payment', 'rejected'], true), 409, 'RECEIPT_LOCKED');
                $path = 'memberships/receipts/'.$p->reference.'/'.Str::uuid().(['image/png' => '.png', 'image/jpeg' => '.jpg', 'application/pdf' => '.pdf'][$mime]);
                abort_unless(Storage::disk('minio-media')->put($path, $bytes, ['visibility' => 'private']) === true, 503, 'RECEIPT_STORAGE_UNAVAILABLE');
                $version = (string) Str::uuid();
                $p->update(['receipt_path' => $path, 'receipt_disk' => 'minio-media', 'receipt_mime' => $mime, 'receipt_sha256' => $d['sha256'],
                    'status' => 'under_review', 'submitted_at' => now(), 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                    'declared_reference' => null, 'paid_date' => null, 'paid_time' => null, 'origin_bank' => null, 'payer_name' => null,
                    'extraction_version' => $version, 'extraction_status' => 'pending', 'extraction_json' => null, 'extraction_error' => null,
                    'extraction_attempts' => 0, 'extraction_claimed_at' => null, 'extraction_processed_at' => null]);
                ExtractMembershipReceipt::dispatch($p->id, $version);

                return response()->json($this->result($p), 201);
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('minio-media')->delete($path);
            }
            throw $e;
        }
    }

    public function identity(Request $r, string $reference, ClubPurchaseIdentity $identity): JsonResponse
    {
        $d = $r->validate(['club_user_uuid' => ['required', 'uuid', 'lowercase'], 'claim_secret' => ['required', 'string', 'max:128']]);

        return DB::transaction(function () use ($identity, $reference, $d) {
            $identity->lock($reference);
            $p = $this->payment($reference);
            abort_unless(hash_equals($p->claim_hash, hash('sha256', $d['claim_secret'])), 403, 'INVALID_PURCHASE_CLAIM');
            abort_if($p->club_user_uuid && $p->club_user_uuid !== $d['club_user_uuid'], 409, 'PURCHASE_OWNER_CONFLICT');
            $p->update(['club_user_uuid' => $d['club_user_uuid']]);
            $identity->attach($p);

            return response()->json($this->result($p), $p->identity_status === 'conflict' ? 409 : 200);
        }, 3);
    }

    /** @return array<string, mixed> */
    private function result(MembershipBankPayment $p): array
    {
        /** @var CarbonInterface|null $activatedAt */
        $activatedAt = $p->activated_at;

        return ['contract' => 'jakawi-club-purchases-v1', 'club_reference' => $p->club_reference, 'payment_reference' => $p->reference,
            'status' => $p->status, 'amount' => '100.00', 'currency' => 'BOB', 'origin' => 'CLUB', 'identity_status' => $p->identity_status,
            'club_user_uuid' => $p->club_user_uuid, 'guest_purchase_id' => $p->guest_purchase_id,
            'global_subject_id' => $p->identity_status === 'linked' ? User::whereKey($p->user_id)->first()?->global_subject_id : null,
            'membership_reference' => $p->membership_id ? (string) $p->membership_id : null,
            'activated_at' => $activatedAt?->toRfc3339String(), 'receipt_received' => $p->receipt_path !== null,
            'receipt_sha256' => $p->receipt_sha256, 'review_note' => $p->review_note,
            'qr_ready' => app(MembershipBankQr::class)->ready(), 'qr_path' => '/internal/club/purchases/qr'];
    }
}
