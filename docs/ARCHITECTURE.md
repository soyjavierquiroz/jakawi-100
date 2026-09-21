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

## Decisions

- No microservices.
- No Kubernetes.
- No native app yet.
- Mobile-first.
- PWA will be the next layer.
