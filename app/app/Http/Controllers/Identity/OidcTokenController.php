<?php

namespace App\Http\Controllers\Identity;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

class OidcTokenController extends AccessTokenController
{
    public function issueToken(ServerRequestInterface $psrRequest, ResponseInterface $psrResponse): Response
    {
        $body = $psrRequest->getParsedBody();
        if (! is_array($body) || ($body['grant_type'] ?? null) !== 'authorization_code') {
            return response()->json(['error' => 'unsupported_grant_type'], 400, ['Cache-Control' => 'no-store']);
        }
        foreach ($body as $value) {
            if (! is_string($value)) {
                return response()->json(['error' => 'invalid_request'], 400, ['Cache-Control' => 'no-store']);
            }
        }

        if (($body['redirect_uri'] ?? null) !== config('oidc.club_redirect_uri')) {
            return response()->json(['error' => 'invalid_request'], 400, ['Cache-Control' => 'no-store']);
        }

        // Serialize exchanges of the same opaque code across PHP processes.
        // Passport/League remain responsible for authenticating the client,
        // checking PKCE/expiry/redirect_uri, issuing tokens and revoking the code.
        DB::beginTransaction();
        try {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                // Defuse's opaque ciphertext is hex; its decoder also accepts uppercase.
                hash('sha256', strtolower($body['code'] ?? '')),
            ]);
            $response = parent::issueToken($psrRequest, $psrResponse);
            if ($response->getStatusCode() < 400) {
                DB::commit();
            } else {
                DB::rollBack();
            }

        } catch (OAuthServerException $e) {
            DB::rollBack();
            // Preserve Passport's standard error/status while applying token cache policy.
            $response = $e->getResponse();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
