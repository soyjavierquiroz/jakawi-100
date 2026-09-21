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

## Decisions

- No microservices.
- No Kubernetes.
- No native app yet.
- Mobile-first.
