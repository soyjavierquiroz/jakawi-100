<?php

namespace App\Http\Middleware;

use App\Support\PrelaunchOperations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectPrelaunchOperations
{
    // Guard before controllers can write analytics, files, accounts or queue jobs.
    private const ACTIONS = [
        'DigitalOrderController' => ['start','report','qr','download'],
        'AdminDigitalSalesController' => ['confirm','reject','retry','refund'],
        'PublicController' => ['reserve'],
        'PartnerPublicController' => ['checkout'],
        'ExperienceReservationController' => ['store', 'cancel'],
        'PartnerReservationController' => ['respond'],
        'ExperienceCheckInController' => ['confirm'],
        'RedemptionController' => ['start', 'confirm', 'scanConfirm'],
        'UnlockController' => ['interest', 'commit', 'confirm', 'cancel'],
        'PartnerUnlockController' => ['fulfill'],
        'AdminUnlockController' => ['extend', 'closeEarly', 'remove', 'releaseJp', 'correctParticipation', 'fulfill'],
        'ChallengeController' => ['intent', 'submit', 'refresh'],
        'AdminChallengeController' => ['refresh', 'close', 'review', 'winner', 'grant', 'fulfill', 'cancelGrant'],
        'AdminMembershipController' => ['activate', 'cancel'],
        'MembershipOfferController' => ['request'],
        'PromoterSalesController' => ['store'],
        'AdminSalesController' => ['store', 'refund'],
        'AdminPayoutController' => ['pay', 'reject'],
        'AdminAdjustmentController' => ['ledger', 'rewardStatus'],
        'MembershipBankController' => ['show', 'report', 'submit', 'import', 'approve', 'batch', 'review'],
        'BankStatementController' => ['upload', 'preview', 'confirm'],
        'AdminReceiptExtractionController' => ['update', 'retry'],
        'ClubPurchaseController' => ['store', 'report', 'receipt', 'identity'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        [$controller, $method] = array_pad(explode('@', $request->route()?->getActionName() ?? ''), 2, '');
        if (in_array($method, self::ACTIONS[class_basename($controller)] ?? [], true)) {
            $subject = match (class_basename($controller)) {
                'DigitalOrderController' => $request->route('product') ?? $request->route('order') ?? $request->route('access'),
                'AdminDigitalSalesController' => $method === 'refund' ? null : ($request->route('payment') ?? $request->route('access')),
                default => null,
            };
            if ($subject instanceof \Illuminate\Database\Eloquent\Model) PrelaunchOperations::assertDigitalAllowed($subject);
            else PrelaunchOperations::assertAllowed();
        }

        return $next($request);
    }
}
