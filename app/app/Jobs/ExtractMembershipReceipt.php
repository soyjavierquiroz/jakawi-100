<?php

namespace App\Jobs;

use App\Models\MembershipBankPayment;
use App\Services\MembershipReceiptVision;
use App\Services\ReceiptExtractionException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExtractMembershipReceipt implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public int $timeout = 65;

    public function __construct(public int $paymentId, public string $version)
    {
        $this->onConnection('database')->onQueue('membership-receipts')->beforeCommit();
    }

    public function backoff(): array
    {
        return [120, 600, 1800];
    }

    public function handle(MembershipReceiptVision $vision): void
    {
        $p = DB::transaction(function () use ($vision) {
            $p = MembershipBankPayment::lockForUpdate()->find($this->paymentId);
            if (! $p || $p->extraction_version !== $this->version || $p->status !== 'under_review' || in_array($p->extraction_status, ['completed', 'manual'], true)) {
                return null;
            }
            if ($p->extraction_status === 'processing' && $p->extraction_claimed_at?->gt(now()->subSeconds(90))) {
                return null;
            }
            if (! $vision->ready()) {
                $p->update(['extraction_status' => 'unconfigured', 'extraction_error' => 'not_configured']);

                return null;
            }
            if ($p->extraction_attempts >= 3) {
                return null;
            }
            $p->update(['extraction_status' => 'processing', 'extraction_claimed_at' => now(), 'extraction_attempts' => $p->extraction_attempts + 1]);

            return $p;
        });
        if (! $p) {
            return;
        }
        try {
            $bytes = Storage::disk($p->receipt_disk ?? 'membership-private')->get($p->receipt_path);
            if ($p->receipt_sha256 && ! hash_equals($p->receipt_sha256, hash('sha256', $bytes))) {
                throw new ReceiptExtractionException('receipt_integrity');
            }
            $result = $vision->extract($bytes, $p->receipt_mime);
        } catch (\Throwable $e) {
            $code = $e instanceof ReceiptExtractionException ? $e->errorCode : 'receipt_unavailable';
            $retry = $e instanceof ReceiptExtractionException ? $e->retryable : true;
            $retry = $retry && $p->extraction_attempts < 3;
            MembershipBankPayment::whereKey($p->id)->where('extraction_version', $this->version)->where('extraction_status', 'processing')->update(['extraction_status' => $retry ? 'pending' : 'failed', 'extraction_error' => $code, 'extraction_claimed_at' => null, 'extraction_processed_at' => now()]);
            if ($retry) {
                throw new \RuntimeException('receipt_extraction_retry');
            } // Never log request/response/document bytes.

            return;
        }
        DB::transaction(function () use ($vision, $result) {
            $p = MembershipBankPayment::lockForUpdate()->find($this->paymentId);
            if (! $p || $p->extraction_version !== $this->version || $p->status !== 'under_review' || $p->extraction_status !== 'processing') {
                return;
            }
            $p->update([...$vision->paymentFields($result), 'extraction_json' => $result, 'extraction_status' => 'completed', 'extraction_error' => null, 'extraction_claimed_at' => null, 'extraction_processed_at' => now()]);
        });
    }

    public function failed(?\Throwable $e): void
    {
        MembershipBankPayment::whereKey($this->paymentId)->where('extraction_version', $this->version)->whereIn('extraction_status', ['pending', 'processing'])->update(['extraction_status' => 'failed', 'extraction_error' => 'retry_exhausted', 'extraction_claimed_at' => null, 'extraction_processed_at' => now()]);
    }
}
