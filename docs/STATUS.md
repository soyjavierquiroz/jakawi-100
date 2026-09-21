# JAKAWI Current Status

Updated: 2026-09-21

Production:
[https://jakawi.com](https://jakawi.com)

Runtime production commit:
`a37805421baf604b949114931b1a3d1f6660bfe7`

Phase:
PWA shell deployed / MVP product development

Working:

- HTTPS
- OpenLiteSpeed reverse proxy
- Docker
- Laravel
- React/Inertia
- authentication
- PostgreSQL
- login/register
- health endpoint
- installable PWA shell
- manifest
- service worker
- Android/Chromium installation support
- iOS Add to Home Screen guidance

Next:

1. Finish Merchant + Benefit vertical
2. Membership
3. Redemption

In development:

- Merchant + Benefit vertical
- Public benefits catalog and detail pages
- Minimal package-free admin for merchants and benefits
- Persistent local public uploads through Docker named volume

Technical note:

Direct host typechecking is not the supported workflow because the host does not keep the dev/Wayfinder dependencies installed. The Docker build is the production validation path.

Repository HEAD can move forward with documentation while the production runtime remains built from `a37805421baf604b949114931b1a3d1f6660bfe7`.
