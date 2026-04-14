# Maya DMS — Contexto del proyecto

## Qué es este proyecto
Sistema de gestión documental (Document Management System) para el ecosistema Maya (CEEDCV).
- **Backend**: Laravel + PHP 8.2
- **Frontend**: React 19 + Vite + TypeScript
- **IdP**: Keycloak 24 (realm `maya`, client `maya-dms-dashboard`)
- **BD**: PostgreSQL 17 (`maya_dms_db`, usuario `maya_dms_user`)
- **Caché/Colas**: Redis 7 (DB 1), RabbitMQ 3
- **WebSockets**: Laravel Reverb (puerto 8082)

## Infraestructura
- Red Docker compartida: `maya_network`
- Infra compartida en `../infra/` (Traefik, Keycloak, PostgreSQL, Redis, RabbitMQ)
- Script de arranque: `./up.sh` (no usar `docker compose up` directamente)
- Paquetes compartidos en `../infra/packages/` (symlink en `../packages/`)

## Accesos locales (vía Traefik)
- Frontend: http://maya-dms.localhost (puerto directo: 5174)
- Backend API: http://maya-dms-api.localhost/api/v1 (puerto directo: 8001)
- Keycloak: http://keycloak.localhost
- Traefik: http://localhost:8888

## Patrón FDW
- En `local/testing`: las migraciones FDW crean tablas stub normales
- En `producción`: configuran `postgres_fdw` real contra `maya_auth` DB

## JWT / JWKS
- JWKS validado contra Keycloak: `http://maya_auth_keycloak:8080/realms/maya/protocol/openid-connect/certs`
- Audience: `maya-dms-dashboard`
- Issuer: `http://keycloak.localhost/realms/maya`

## Paquetes compartidos
- `maya-shared-auth-laravel`: middleware JWT/JWKS (Composer path)
- `maya-shared-auth-react`: hooks/componentes Keycloak auth (npm file:)

## Runbook del ecosistema
Ver `../infra/RUNBOOK.md` para orden de arranque, URLs y verificación.
