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
            PrelaunchOperations::assertAllowed();
        }

        return $next($request);
    }
}
