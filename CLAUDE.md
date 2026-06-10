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
Provienen del mono-repo `Maya-AQSS/maya_platform` y se distribuyen como
paquetes públicos estándar:

- Backend: `ceedcv-maya/shared-*-laravel` (Packagist, `^0.2`)
- Frontend: `@ceedcv-maya/shared-*-react` (npm registry, `^0.2.0`)
- Dev override local: copiar `backend/composer.local.dist.json` → `composer.local.json` (gitignored)
- Doc completa: ver PM05 en `DOCUMENTATION/docs/src/desarrollo/`

## Testing (backend)
La suite corre contra **Postgres real** en la BD `maya_dms_test` (NO sqlite): los repos usan
SQL específico de Postgres (`GREATEST`, operadores `jsonb`). Tests con **Pest** (no el runner
phpunit directo).

```bash
# Dentro del contenedor backend del slot (p. ej. maya-<slot>-dms-backend-1):
./vendor/bin/pest --no-coverage                 # toda la suite
./vendor/bin/pest --no-coverage tests/Feature/HealthCheckTest.php
# o desde el host:
make test
```

- **Portable entre slots**: `phpunit.xml` y `tests/bootstrap.php` toman `DB_HOST`/`DB_PORT`
  del entorno que ya exporta el contenedor del slot (p. ej. `maya-<slot>-postgres`); no hay
  que parchear el host a mano. Sólo se fuerzan los valores críticos de aislamiento:
  `DB_DATABASE=maya_dms_test` (distinta de la de runtime `maya_dms_db`) y el usuario
  `maya` (superusuario, dueño-agnóstico para `migrate:fresh`/`RefreshDatabase`).
- Usar `pest --no-coverage`: `artisan test` con pcov puede agotar memoria (**OOM**). Si la
  cobertura OOMea, correr `tests/Unit` y `tests/Feature` por separado.

## Guías importantes
- `../maya_authorization/docs/src/new-app-guide.md` — requisitos para nuevas apps
- `../maya_authorization/docs/src/architecture.md` — arquitectura del sistema
- `../infra/RUNBOOK.md` — runbook del ecosistema (arranque, URLs, verificación)
