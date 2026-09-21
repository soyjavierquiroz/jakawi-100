# JAKAWI

Vive más. Gasta menos.

Produccion:
[https://jakawi.com](https://jakawi.com)

## Stack

- Laravel 13
- PHP 8.4
- React
- TypeScript
- Inertia
- Tailwind CSS
- PostgreSQL 16
- Docker Compose
- nginx
- OpenLiteSpeed

## Architecture

```text
Internet HTTPS
-> OpenLiteSpeed
-> 127.0.0.1:8080
-> nginx Docker
-> Laravel/PHP-FPM
-> PostgreSQL
```

## Repository Structure

```text
.
├── app/                 Laravel application and React/Inertia frontend
├── docker/              Docker image and service configuration
├── docs/                Project operations and product documentation
├── compose.yaml         Docker Compose production stack
├── README.md            Project overview
└── CHANGELOG.md         Release history
```

Runtime-only paths such as `.env`, `logs/`, `public_html/`, `.ssh/`, Docker data, dependencies, and build output are intentionally ignored.

## Health Checks

```bash
curl -f http://127.0.0.1:8080/up
curl -f https://jakawi.com/up
```

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Operations](docs/OPERATIONS.md)
- [MVP](docs/MVP.md)
- [Status](docs/STATUS.md)
