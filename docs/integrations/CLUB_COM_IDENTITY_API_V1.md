# COM identity authority — CLUB integration v1

Implementation of [JAKAWI_IDENTITY_CRM_CONTRACT_V1](../architecture/JAKAWI_IDENTITY_CRM_CONTRACT_V1.md), sections 3, 4, 10–12. This document specifies its previously proposed wire protocol. COM alone issues global subjects. CLUB and CRM retain their local accounts, sessions and business responsibilities.

## Production and credentials

- Origin/issuer: **`https://jakawi.com`**.
- API base: **`https://jakawi.com/internal/identity`**.
- Contract: `jakawi-identity-handoff-v1`.
- External CLUB account key: lowercase UUID **`users.uuid`**, transmitted as `club_user_id`. Neither application's bigint PK is exposed by this API.
- Runtime variables in COM: `IDENTITY_CLUB_KEY_ID`, `IDENTITY_CLUB_SECRET`, `IDENTITY_CLUB_EMAIL_PROOF_SECRET`.
- Two independently generated secrets of at least 32 characters are required. They must differ from each other and from `CRM_BRIDGE_SECRET`. Missing or shared credentials fail closed with 503.
- Provisioned deployment credentials are in **`/opt/sites/jakawi.com/identity-club.env`**, root-only mode 0600, outside Git. Codex CLUB must read them through its authorized server access and store them privately in CLUB's server configuration. Never send these values to the browser, Git, a URL or logs. This COM implementation does not configure or deploy CLUB.
- Suggested CLUB-side aliases: `COM_IDENTITY_BASE_URL=https://jakawi.com/internal/identity`, `COM_IDENTITY_KEY_ID`, `COM_IDENTITY_SECRET`, `COM_IDENTITY_EMAIL_PROOF_SECRET`. Map the latter three to the corresponding COM values above.
- Rotate by replacing the key ID and both secrets in the private file and recreating COM app/worker/scheduler with the updated environment, then updating the CLUB sender. One active key is supported in v1; coordinate the cutover. Durable handoffs/links survive rotation.

## Routes

All `/internal/identity` routes require the signature below, even GET. They use JSON and do not accept cookies as integration authentication. They never log a browser in or return access/session tokens.

| Method | Absolute route | Purpose |
| --- | --- | --- |
| POST | `https://jakawi.com/internal/identity/handoffs` | Idempotent provision or begin an existing-account link |
| GET | `https://jakawi.com/internal/identity/handoffs/{handoff_id}` | Persisted operation/result, including completion after COM consent |
| GET | `https://jakawi.com/internal/identity/links/{club_user_id}` | Stable account mapping; independent of later email changes |
| POST | `https://jakawi.com/internal/identity/links/{club_user_id}/revoke` | Idempotently revoke the mapping without deleting accounts or history |

GET and revoke use an empty body. No query strings or arbitrary redirect URLs are accepted. Use lowercase UUIDs and the exact path shown. Responses carry `Cache-Control: no-store`. Keep this API server-side: its existence results must not become a public email enumeration endpoint. No browser CORS integration is provided.

## Server authentication

Headers on every request:

```http
Accept: application/json
Content-Type: application/json
X-Jakawi-Key-Id: club-identity-v1
X-Jakawi-Timestamp: <decimal Unix seconds>
X-Jakawi-Nonce: <fresh UUID for this HTTP attempt>
X-Jakawi-Signature: <lowercase hex HMAC-SHA256>
```

Sign these seven lines with `IDENTITY_CLUB_SECRET`, UTF-8, LF separators, **no final LF**:

```text
jakawi-identity-v1
<key_id>
<timestamp>
<nonce>
<UPPERCASE HTTP METHOD>
<absolute path starting /internal/identity, no host or query>
<lowercase hex SHA256 of exact raw body bytes; SHA256 of empty bytes for GET/revoke>
```

The clock window is ±300 seconds; synchronize server clocks. A nonce can be consumed once per key. A repeated signed HTTP attempt returns 409 `REQUEST_REPLAY`. For a legitimate retry, generate a fresh timestamp/nonce/signature while retaining the **same handoff_id and exact original body bytes**. Persist those bytes in CLUB's outbox; do not regenerate proof/consent timestamps for that operation. Nonces are pruned only after 15 minutes, beyond their entire acceptance window. Authenticated calls are limited to 120/minute per integration. Maximum raw body: 16,384 bytes.

Minimal Python signing function (values come from private server configuration):

```python
import hashlib, hmac, time, uuid

def headers(method, path, raw_body, key_id, secret):
    timestamp, nonce = str(int(time.time())), str(uuid.uuid4())
    canonical = "\n".join([
        "jakawi-identity-v1", key_id, timestamp, nonce, method.upper(), path,
        hashlib.sha256(raw_body).hexdigest(),
    ])
    return {
        "Accept": "application/json", "Content-Type": "application/json",
        "X-Jakawi-Key-Id": key_id, "X-Jakawi-Timestamp": timestamp,
        "X-Jakawi-Nonce": nonce,
        "X-Jakawi-Signature": hmac.new(secret.encode(), canonical.encode(), hashlib.sha256).hexdigest(),
    }
```

Serialize the JSON once to UTF-8 bytes, sign those bytes and transmit exactly those bytes. The signature authenticates `source_app=club`; a body label alone is never trusted.

## Verifiable email activation assertion

An active session or `email_verified: true` is insufficient. Before provisioning/link initiation, CLUB must verify its **currently activated email** and obtain the historical evidence of an actually consumed activation token. Generate this short-lived server attestation only from that verified state, with a stable reference to the activation evidence. Do not include the activation token, password, password hash or broker secret.

`email_activation_proof = BASE64URL_UNPADDED(UTF8_JSON(claims)) + "." + HEX_HMAC`

`HEX_HMAC = HMAC-SHA256(IDENTITY_CLUB_EMAIL_PROOF_SECRET, "jakawi-email-activation-v1\n" + encoded_claims)`, lowercase hex. This separate proof key belongs only to the identity integration. COM verifies the attestation's cryptographic integrity, authenticated issuer, scope, account/email/handoff binding, activation evidence fields and freshness. CLUB remains responsible for the truth of the underlying activation event; a signature does not establish email delivery by itself.

Claims (illustrative values; generate fresh values for a real request):

```json
{
  "iss": "https://jakawi.club",
  "aud": "https://jakawi.com",
  "sub": "7c20155c-42a7-4c55-bda4-933923dd257c",
  "email": "persona@example.com",
  "handoff_id": "d2221051-b609-4f76-a278-40605f2e349a",
  "jti": "3a4457cd-7bd1-45bb-9c6f-fd7511190410",
  "method": "email_activation_token",
  "activation_ref": "activation:opaque-stable-reference",
  "activated_at": "2026-10-29T18:00:00Z",
  "iat": 1793297400,
  "exp": 1793297640
}
```

`sub`, `email` and `handoff_id` must exactly match the request's lowercase club UUID, lowercased/trimmed email and handoff UUID. `jti` is a UUID. `activation_ref` is 1–128 characters from `[A-Za-z0-9:_-]`. `activated_at` is an ISO8601 timestamp with timezone, year >=2020, and no more than 30 seconds after `iat`. Old activation events remain usable if CLUB still validates that email as current. `iat`/`exp` are JSON integers: `iat` is at most 300 seconds old, at most 30 seconds in the future, `exp > now`, and `0 < exp - iat <= 300` seconds.

For example:

```python
import base64, hashlib, hmac, json
encoded = base64.urlsafe_b64encode(
    json.dumps(claims, separators=(",", ":")).encode()
).rstrip(b"=").decode()
proof = encoded + "." + hmac.new(
    proof_secret.encode(), ("jakawi-email-activation-v1\n" + encoded).encode(), hashlib.sha256
).hexdigest()
```

COM validates a new proof at initial receipt and encrypts the accepted assertion/consent evidence using its existing application encryption. A pending handoff can be confirmed during its separate 15-minute lifetime. Retries of an already accepted operation use the originally persisted result, even after proof expiry. If the initial result was `VERIFICATION_REQUIRED`, correct the evidence and create a **new handoff_id**.

## Create a handoff

```http
POST /internal/identity/handoffs
```

```json
{
  "contract": "jakawi-identity-handoff-v1",
  "source_app": "club",
  "handoff_id": "d2221051-b609-4f76-a278-40605f2e349a",
  "club_user_id": "7c20155c-42a7-4c55-bda4-933923dd257c",
  "name": "Persona Ejemplo",
  "email": "persona@example.com",
  "whatsapp": "+59170000001",
  "email_activation_proof": "<encoded claims>.<hex MAC>",
  "consent": {
    "purpose": "identity_link",
    "version": "1",
    "accepted_at": "2026-10-29T18:10:00Z"
  }
}
```

Required: contract, source_app, both lowercase UUIDs, name (1–255 characters), email (RFC-valid, <=255), consent with the exact three keys shown. Consent represents the authenticated person's explicit “Activar mi JAKAWI” request in CLUB; `accepted_at` must be within the past day and at most 30 seconds in the future. Store its evidence in CLUB. Unknown top-level fields are rejected, including caller-supplied subjects, IDs, passwords, roles, memberships or economic attribution.

`whatsapp` is optional/nullable, normalized by COM's existing normalizer, and is **not** marked verified. A provided invalid number produces 422. Proof can be omitted/null to obtain `VERIFICATION_REQUIRED`; it must be valid to provision or initiate account authorization. CLUB lead/campaign/referral/marketing projection is outside this API: do not send or invent attribution or opt-ins here.

All resolved results use HTTP 200 except `CONFLICT` (409). `PROVISIONED` means a new COM account and mapping were created in one transaction. The password is randomly generated inside COM and never disclosed; use COM's existing local recovery/login mechanisms for access. No password is copied and no welcome message, paid membership, Partner/affiliate enrollment, commission or marketing permission is granted. The new account's email_verified_at comes from the verified activation evidence. Existing COM users' verification fields are untouched.

New account, HTTP 200 (UUID generated by COM):

```json
{
  "contract": "jakawi-identity-handoff-v1",
  "issuer": "https://jakawi.com",
  "handoff_id": "d2221051-b609-4f76-a278-40605f2e349a",
  "club_user_id": "7c20155c-42a7-4c55-bda4-933923dd257c",
  "status": "PROVISIONED",
  "link": {
    "source_app": "club",
    "club_user_id": "7c20155c-42a7-4c55-bda4-933923dd257c",
    "global_subject_id": "29d25157-9baa-479d-91cb-72cbfd6b9325",
    "issuer": "https://jakawi.com",
    "status": "LINKED",
    "method": "CLUB_EMAIL_ACTIVATION",
    "authorized_at": "2026-10-29 18:10:00+00",
    "revoked_at": null
  }
}
```

Existing COM account, HTTP 200, **no subject disclosed before authorization**:

```json
{
  "contract": "jakawi-identity-handoff-v1",
  "issuer": "https://jakawi.com",
  "handoff_id": "d2221051-b609-4f76-a278-40605f2e349a",
  "club_user_id": "7c20155c-42a7-4c55-bda4-933923dd257c",
  "status": "AUTH_REQUIRED",
  "authorization_url": "https://jakawi.com/identity/club/link/d2221051-b609-4f76-a278-40605f2e349a",
  "expires_at": "2026-10-29T18:25:00.000000Z"
}
```

Navigate the person to that fixed COM URL. It is a correlation identifier, **not an authentication credential**. COM requires its native authenticated session, completion of applicable 2FA/passkey login, a full local authentication within 5 minutes and a CSRF-protected explicit consent POST by the exact target account. An older or remember-only session must use the native re-login flow. Wrong account: 403; expired pending operation: 410; consent is never inferred from visiting a GET. No password is sent to CLUB. After completion, the person can return to CLUB; CLUB polls the authenticated GET handoff or stable mapping. There is no callback, cross-domain cookie or custom token exchange.

Completion gives the same envelope with `status=LINKED` and `link.method=COM_AUTHENTICATED_CONSENT`. No session is created by the server API. Existing name, email, password, roles, Partner permissions and memberships are unchanged.

## Stable lookup, idempotency, conflicts and revocation

`GET /internal/identity/links/{club_user_id}` returns the `link` object above directly. It returns 404 when no authorized mapping exists, including a pending `AUTH_REQUIRED`. Email is not a lookup key. A later email change keeps the subject/mapping stable.

`GET /internal/identity/handoffs/{handoff_id}` returns the persisted envelope. Initial results: `PROVISIONED`, `AUTH_REQUIRED`, `VERIFICATION_REQUIRED`, `CONFLICT`; a recognized existing mapping returns `LINKED`; authorized/revoked operations transition to `LINKED`/`REVOKED`. `VERIFICATION_REQUIRED` has the common envelope without `link`/authorization_url. Conflict results omit `link`.

A handoff UUID is a durable operation key in the authenticated CLUB namespace. COM stores SHA256 of the raw original body. Same handoff and bytes return the persisted result without new users/links; different bytes return HTTP 409 with `status=CONFLICT`, `error=IDEMPOTENCY_CONFLICT`. The conflicting attempt never overwrites the original operation/result. The initial result can advance after explicit authorization or revocation; retries report its current persisted state. A pending operation expires after 900 seconds; retain its result for diagnostics and start a new handoff to retry authorization. New handoff IDs never bypass existing-account authentication.

One CLUB UUID maps to at most one COM subject, and one COM subject to at most one CLUB UUID. Provisioning and confirmation serialize by account/operation, lock candidate users and enforce database uniqueness. Lowercased/trimmed COM email has a database unique index that also covers concurrent ordinary COM registrations. Historical normalized duplicates stop migration for explicit resolution; they are never merged automatically.

`POST /internal/identity/links/{club_user_id}/revoke`, empty body, returns the link with `status=REVOKED` and `revoked_at`. Repeated revocation is idempotent. Completed handoffs also report revocation. Revocation does not delete accounts, subjects, business history or attribution. v1 does not automatically reactivate/reassign revoked or conflicting mappings: return `CONFLICT` and resolve through the COM operator. A deleted local COM account leaves its issued subject and mapping tombstone; stable lookup reports it revoked.

## Errors

| HTTP | Meaning / machine response |
| --- | --- |
| 200 | Resolved envelope or mapping; check `status`, including `AUTH_REQUIRED` and `VERIFICATION_REQUIRED` |
| 401 | `{"error":"UNAUTHENTICATED"}` — missing/bad signature, timestamp, key, nonce format, unsupported query or oversized body |
| 404 | Handoff or authorized mapping not found; Laravel JSON `message` |
| 409 | `{"error":"REQUEST_REPLAY"}`; or handoff `CONFLICT`, optionally `error=IDEMPOTENCY_CONFLICT` |
| 422 | `{"error":"UNKNOWN_FIELDS"}`, `JSON_REQUIRED`, `BODY_MUST_BE_EMPTY`; or Laravel `message` and per-field `errors` for invalid schema |
| 429 | Integration rate limit; honor `Retry-After` and retry with fresh transport nonce |
| 503 | `{"error":"IDENTITY_UNAVAILABLE"}` — missing/unsafe credential configuration |
| 5xx | Unexpected/transient server failure; retry the same operation/body with a fresh transport signature, then reconcile by GET |

Interactive COM consent additionally uses 302 to native login, 403 for wrong/stale authentication, 410 for expiration, 409 for mapping conflict, 419 for invalid CSRF, and validation errors for absent consent. Never interpret an HTTP 200 alone as `LINKED`.

## Persistence, SSO boundary and verification

- `users.global_subject_id`: PostgreSQL UUID, NOT NULL, unique, default generated by COM. Backfill changes no other user values. A trigger prevents subject mutation.
- `identity_subjects`: permanent issuance ledger. A deleted account's subject cannot be reissued or removed.
- `identity_links`: durable unique mapping, method, timestamps and revocation.
- `identity_handoffs`: stable operation, hash, persisted response, target COM mapping, encrypted assertion/consent evidence and expiry.
- `identity_request_nonces`: short-lived replay protection, pruned hourly after 15 minutes.
- `identity_link_events`: minimal transition/conflict diagnostic records without email/password/session payloads.

No OIDC server is implemented. A later OIDC Authorization Code + PKCE S256 provider must explicitly map its `sub` to these COM-issued global subjects. Local sessions and email-verification policy remain separate. Possession of a global subject alone never establishes `email_verified=true`. Migration is intentionally irreversible: roll back code/images while retaining issued subjects and mapping data; never drop/reissue identity data as a release rollback.

Focused checks:

```sh
# Dedicated network/database required; wrapper refuses production DB configuration.
bin/jakawi-identity-test php vendor/bin/phpunit tests/Feature/Identity tests/Feature/Auth
bin/jakawi-identity-test php tests/identity/concurrency.php
```

The concurrency check runs three independent PHP workers for one handoff, then three different handoffs for one email, and asserts exactly one created user per case. Test credentials, activation claims and accounts are isolated fixtures. Production pilot verification compares existing users/permissions/password fingerprints around migration and does not impersonate the pilot, reset its password or authorize its link.
