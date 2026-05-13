# Maya DMS — Contexto del proyecto

## Qué es este proyecto
Sistema de gestión documental (Document Management System) del ecosistema Maya (CEEDCV).
- **Backend**: Laravel 13 / PHP 8.4
- **Frontend**: React 19 + Vite + TypeScript
- **IdP**: Keycloak 24 (realm `maya`)
- **BD**: PostgreSQL 17
- **Colas**: RabbitMQ (Laravel Horizon)

## Infraestructura
- Reverse proxy: **Traefik latest**
- Red Docker compartida: `maya_network`
- Script de arranque: `./up.sh` (no usar `docker compose up` directamente)

## Accesos locales (vía Traefik)
- Frontend:  http://dms.maya.test
- Backend:   http://dms-api.maya.test/api/v1
- Keycloak:  http://keycloak.maya.test
- Traefik:   http://localhost:8888/dashboard/

## Paquetes compartidos
- `maya-shared-auth-laravel`: middleware JWT/JWKS (Composer path en `../infra/packages/`)
- `maya-shared-auth-react`: hooks/componentes Keycloak auth (npm file: en `../infra/packages/`)
- Symlink `../packages → ../infra/packages` para compatibilidad

## Guías importantes
- `../maya_authorization/docs/src/new-app-guide.md` — requisitos para nuevas apps
- `../maya_authorization/docs/src/architecture.md` — arquitectura del sistema
- `../infra/RUNBOOK.md` — runbook del ecosistema (arranque, URLs, verificación)
