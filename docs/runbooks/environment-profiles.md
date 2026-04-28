# Environment Profiles

Date: 2026-04-28

## Local

- Runtime: Windows PHP or Docker-backed services
- Database: MySQL on `127.0.0.1:3310`
- Redis: `127.0.0.1:6379`
- Mail capture: Mailpit on `127.0.0.1:1025` with UI on `127.0.0.1:8025`
- Filesystem: local disk
- Queue/cache/session baseline: database

## Staging

- Runtime: Linux production-like environment
- Database: managed MySQL
- Cache and queues: Redis preferred
- Mail: SMTP provider sandbox
- Filesystem: local or object storage depending on staging parity needs

## Production

- Runtime: Linux application hosts or containers behind reverse proxy
- Database: managed MySQL with backups
- Cache and queues: Redis
- Filesystem: S3-compatible object storage for tenant and agreement documents
- Mail: production SMTP / transactional provider
- Secrets: all credentials sourced from deployment environment, not repository files
