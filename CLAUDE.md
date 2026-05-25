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
- Frontend:  https://dms.maya.test
- Backend:   https://dms-api.maya.test/api/v1
- Keycloak:  https://keycloak.maya.test
- Traefik:   http://localhost:8888/dashboard/

## Paquetes compartidos
Provienen del mono-repo `Maya-AQSS/maya_platform` y se distribuyen vía repos split
(read-only) por paquete. Los servicios los consumen con Composer VCS y npm github:.

- Backend: `maya/shared-*-laravel` (Composer, `https://github.com/Maya-AQSS/shared-*-laravel`, `^0.1`)
- Frontend: `@maya/shared-*-react` (npm, `github:Maya-AQSS/shared-*-react#v0.1.0`)
- Dev override local: copiar `backend/composer.local.dist.json` → `composer.local.json` (gitignored)
- Doc completa: ver PM05 en `DOCUMENTATION/docs/src/desarrollo/`

## Guías importantes
- `../maya_authorization/docs/src/new-app-guide.md` — requisitos para nuevas apps
- `../maya_authorization/docs/src/architecture.md` — arquitectura del sistema
- `../infra/RUNBOOK.md` — runbook del ecosistema (arranque, URLs, verificación)
