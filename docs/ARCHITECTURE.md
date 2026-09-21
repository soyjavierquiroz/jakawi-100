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
