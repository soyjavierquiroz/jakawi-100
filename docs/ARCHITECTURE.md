# Architecture

JAKAWI is a Laravel monolith with an Inertia, React, and TypeScript frontend. The application stores data in PostgreSQL and runs in Docker.

TLS terminates at OpenLiteSpeed. OpenLiteSpeed listens on ports 80 and 443, then proxies the application to the Docker nginx service on `127.0.0.1:8080`.

```text
Internet HTTPS
-> OpenLiteSpeed
-> 127.0.0.1:8080
-> nginx Docker
-> Laravel/PHP-FPM
-> PostgreSQL
```

## Network

- `80/443`: OpenLiteSpeed
- `127.0.0.1:8080`: nginx Docker
- `9000`: internal PHP-FPM
- `5432`: internal PostgreSQL

## OpenLiteSpeed

- vhost: `/usr/local/lsws/conf/vhosts/jakawi.com/vhost.conf`
- backend: `127.0.0.1:8080`

## Design System

- `app/resources/css/theme.css` is the single source of truth for color.
- React components must use semantic tokens instead of hardcoded brand colors.
- Tailwind and shadcn consume the same token system through `app/resources/css/app.css`.
- The current colors are provisional.
- JAKAWI's full chromatic identity must be changeable from `theme.css`.

## PWA

- JAKAWI is a mobile-first web app with a minimal installable PWA shell.
- `app/public/manifest.webmanifest` declares the standalone app experience.
- `app/public/sw.js` registers a conservative service worker for PWA capability.
- PWA icons in `app/public/icons/` and `app/public/apple-touch-icon.png` are temporary application icons until the final JAKAWI mark is approved.
- Installation requires explicit user action through the browser or platform UI.
- Android/Chromium can use the install prompt when the browser exposes it.
- iOS receives Add to Home Screen guidance when Safari is not already standalone.
- The PWA is not offline-first yet; no offline database, push notifications, background sync, or persistent authenticated page caching are implemented.

## Merchant + Benefit Vertical

- `merchants` stores the public commerce profile and activation state.
- `benefits` stores offers linked to one merchant.
- Public benefit availability is server-side: the benefit must be active, within its optional date window, and attached to an active merchant.
- Public routes expose a catalog at `/beneficios` and benefit detail pages by slug.

## Admin

- The first admin surface is intentionally simple and package-free.
- Users have an `is_admin` boolean.
- `/admin` routes require authentication and the server-side `admin` middleware.
- Initial promotion uses `php artisan user:make-admin {email}`.
- No roles, permissions package, merchant dashboard, analytics, or operational workflow beyond Merchant/Benefit CRUD is implemented yet.

## Membership

- Membership v1 is manual: a registered user becomes a member when an admin activates a membership from `/admin/memberships`.
- Business constants live in `app/config/jakawi.php`: `jakawi.membership.price_bob` is `100` and `jakawi.membership.duration_days` is `365`.
- `memberships` stores `user_id`, `status`, `starts_at`, `ends_at`, optional payment metadata, `activated_by`, optional notes, and timestamps.
- Initial statuses are `active`, `expired`, and `cancelled`; the database does not enforce a rigid enum yet.
- The source of truth for active membership is `status = active`, `starts_at <= now()`, and `ends_at >= now()`.
- An expired date wins even if the stored status still says `active`. No scheduler marks expired rows yet.
- Reusable logic is centralized in `Membership::active()`, `User::activeMembership()`, and `User::hasActiveMembership()`.

## Redemption

- Redemption v1 is the first transactional member-to-merchant flow: an active member creates a temporary code for an available benefit, the merchant validates that code with its 6 digit PIN at `/validar`, and the redemption is confirmed.
- Business constants live in `app/config/jakawi.php`: `jakawi.redemption.code_ttl_minutes` is `10`.
- Merchant redemption PINs are stored only as `redemption_pin_hash` using Laravel hashing. The hash is hidden on the model and admin edit screens expose only whether a PIN is configured.
- `benefits.redemption_limit_per_member` controls confirmed redemption limits: `1` means once per member, `2` means twice, and `null` means unlimited. Pending or expired redemptions do not consume the limit.
- `redemptions.public_id` is the public route identifier for member pages. Numeric IDs are not used in public redemption URLs.
- `redemptions.code` is a unique 6 character uppercase code generated server-side with a non-ambiguous alphabet. Codes are not sequential.
- `merchant_name`, `benefit_title`, and `savings_amount` are snapshots captured when the code is created. `savings_amount` copies the benefit's `estimated_savings`; if that value is null, no savings amount is invented.
- `merchant_id` and `benefit_id` are nullable with `nullOnDelete` so deleting a merchant or benefit does not destroy redemption history. Snapshots keep the historical display usable.
- `membership_id` records the membership that authorized code creation. It follows the existing membership ownership convention and cascades if the owning user is deleted through membership deletion.
- `user_id` follows the existing user-owned data convention and cascades on user deletion.
- Statuses are `pending`, `confirmed`, `expired`, and `cancelled`. For MVP, expiration is derived from `expires_at`; expired pending rows may be marked `expired` during validation without a scheduler.
- Creation is idempotent for double clicks: the server locks the user row, revalidates active membership, benefit availability, merchant PIN presence, confirmed redemption limit, and reuses an existing valid pending redemption for the same user and benefit.
- Confirmation is idempotent for repeated valid submits: the redemption row is locked, already-confirmed rows are not duplicated, and `savings_amount` is not changed at confirmation time.
- Confirmation revalidates pending state, expiration, active membership, current benefit availability, active merchant, merchant PIN, and confirmed redemption limit. These rules live server-side; React only reflects state.
- The public validator route is rate limited separately at 20 attempts per minute per IP and uses a generic failure message for incorrect code/PIN combinations.
- `/mi-jakawi` savings use only confirmed redemptions, sum non-null snapshot `savings_amount`, and show recent confirmed redemptions using snapshots.

## Uploads

- Merchant logos, merchant covers, and benefit images use Laravel's `public` storage disk.
- The database stores only file paths.
- Docker Compose mounts `jakawi_public_uploads` into both the PHP app container and the nginx web container so uploaded files survive container recreation and are served from `/storage`.

## Decisions

- No microservices.
- No Kubernetes.
- No native app yet.
- Mobile-first.
- Admin is intentionally minimal until product workflows prove what is needed.
