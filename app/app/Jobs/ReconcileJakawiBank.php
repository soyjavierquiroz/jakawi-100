<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
class ReconcileJakawiBank implements ShouldQueue {
 use Dispatchable, Queueable;
 public int $tries=3;
 public int $timeout=60;
 public function __construct() { $this->onConnection('database')->onQueue('membership-receipts')->afterCommit(); }
 public function handle(): void { app(\App\Services\JakawiBankReconciliation::class)->run(); }
}
