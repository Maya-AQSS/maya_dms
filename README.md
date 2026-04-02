# Maya DMS

Módulo de gestión de **Documentos y Programaciones Didácticas** para centros educativos. Versionado ISO 9001, flujos de revisión y exportación oficial. Sub-app del ecosistema Maya (CEEDCV).

- **Laravel 13** como backend API
- **React 19 + Vite** como frontend
- **PostgreSQL 17** con postgres_fdw para federar usuarios
- **Redis** para caché
- **RabbitMQ** para colas (Laravel Horizon)
- **Laravel Reverb** para WebSockets

## Prerequisitos

- Docker Engine 20.10+
- Docker Compose v2+
- Make
- Repo `infra/` clonado al mismo nivel que este proyecto (Traefik + Keycloak compartidos)

## Infraestructura compartida

Keycloak y Traefik **no** están en este proyecto — viven en el repo `infra/`, compartido por todo el ecosistema Maya. El script `up.sh` los levanta automáticamente si no están corriendo.

### Clonar infra

Clona el repo de infra **al mismo nivel** que este proyecto:

```bash
git clone <url-repo-infra> ../infra
```

Resultado esperado:

```text
~/desarrollo/
├── infra/               ← repo infra
├── maya_authorization/
├── maya-dms/            ← este proyecto
└── [futuros proyectos]/
```

Si tienes infra en otra ubicación, usa la variable de entorno:

```bash
MAYA_INFRA_DIR=/ruta/absoluta/a/infra ./up.sh
```

Si quieres levantar la infra de forma independiente:

```bash
cd ../infra
cp .env.example .env   # solo la primera vez
docker compose up -d
```

## Instalación

### 1. Clonar y copiar variables de entorno

```bash
git clone <repository-url>
cd maya-dms
cp .env.example .env
cp backend/.env.example backend/.env
```

### 2. Configurar variables de entorno

Editar `.env` y `backend/.env`. Valores clave:

```env
DB_HOST=postgres             # nombre del servicio Docker, NO 127.0.0.1
REDIS_HOST=redis
RABBITMQ_HOST=rabbitmq
JWKS_URL=http://keycloak:8080/realms/maya/protocol/openid-connect/certs
JWT_ISSUER=http://keycloak.localhost/realms/maya
```

Generar las claves de Reverb (WebSocket) después del primer arranque:

```bash
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan reverb:install
```

### 3. Setup completo desde cero

```bash
make install
```

Este comando: construye imágenes, levanta servicios (infra incluida), genera `APP_KEY`, ejecuta migraciones, seeders e instala dependencias del frontend.

O paso a paso:

```bash
./up.sh --build              # construye y levanta (verifica infra automáticamente)
make migrate                 # migraciones
make seed                    # datos iniciales (opcional)
```

## Arranque diario

```bash
./up.sh                      # o: make up
```

El script verifica que la infra compartida (Traefik + Keycloak) esté corriendo y la levanta si falta antes de iniciar los servicios propios.

Para parar:

```bash
./up.sh down                 # o: make down
```

## URLs de acceso

| Servicio | URL (vía Traefik) | URL directa |
| --- | --- | --- |
| Frontend | <http://maya-dms.localhost> | <http://localhost:5174> |
| Backend API | <http://maya-dms-api.localhost/api/v1> | <http://localhost:8001/api/v1> |
| Keycloak | <http://keycloak.localhost> | <http://localhost:8080> |
| Traefik dashboard | <http://localhost:8888> | — |
| RabbitMQ Management | — | <http://localhost:15673> |
| PostgreSQL | — | localhost:5433 |
| Redis | — | localhost:6380 |

### Credenciales por defecto

| Servicio | Usuario | Contraseña |
| --- | --- | --- |
| Keycloak Admin | `admin` | `admin` |
| PostgreSQL | `maya_dms_user` | `secret` |
| Redis | — | `secret` |
| RabbitMQ | `guest` | `guest` |

## Comandos útiles

```bash
# Ciclo de vida
make up                      # Levantar servicios
make down                    # Parar servicios
make restart                 # Reiniciar contenedores
make logs                    # Seguir todos los logs

# Base de datos
make migrate                 # Ejecutar migraciones
make migrate-fresh           # Reset BD + migraciones + seeders
make seed                    # Ejecutar seeders

# Backend
make shell-backend           # Shell en el contenedor backend
make key-generate            # Generar APP_KEY
make route-list              # Listar rutas API

# Colas y WebSockets
make horizon                 # Arrancar Horizon manualmente
make horizon-status          # Estado de las colas
make reverb                  # Arrancar Reverb manualmente

# Tests y calidad
make test                    # Tests Pest
make test-backend            # Tests con cobertura mínima 80%
make test-frontend           # Tests Vitest
make lint                    # Linting PHP (Pint) + JS (Biome)

# Frontend
make shell-frontend          # Shell en el contenedor frontend
```

## Acceso remoto vía SSH

```bash
ssh -L 80:localhost:80 \
    -L 8888:localhost:8888 \
    usuario@servidor-remoto
```

Con el puerto 80 redirigido, todos los subdominios `*.localhost` funcionan igual que en local.

## Solución de problemas

### Infra no arranca / red maya_network no existe

```bash
cd ../infra && docker compose up -d
```

### Error de versión de PostgreSQL (unhealthy)

```bash
./up.sh down
docker volume rm maya-dms_postgres_data
./up.sh
make migrate
```

### Backend no conecta a la BD o RabbitMQ

Verificar `backend/.env`:

```env
DB_HOST=postgres       # NO 127.0.0.1
REDIS_HOST=redis
RABBITMQ_HOST=rabbitmq
```

Luego:

```bash
docker compose exec backend php artisan config:clear
docker compose restart backend
```

### Reset completo

```bash
./up.sh down
docker volume rm maya-dms_postgres_data maya-dms_redis_data maya-dms_rabbitmq_data
./up.sh --build
make migrate
```

## Arquitectura

```text
infra/
  Traefik (:80, :8888) ──── enruta *.localhost
  Keycloak (:8080)      ──── IdP compartido del ecosistema

maya-dms/
  Frontend (:5174) ──→ Backend (:8001) ──→ PostgreSQL (:5433)
                              ↓
                         Redis (:6380)
                              ↓
                       RabbitMQ (:5673)
                         Horizon (colas)
                         Reverb (:8082, WebSockets)
```

Todos los servicios comparten la red Docker `maya_network`.

## Stack tecnológico

| Capa | Tecnología | Versión |
| --- | --- | --- |
| Backend API | Laravel | 13 |
| Runtime | PHP | 8.4 |
| Base de datos | PostgreSQL | 17 |
| Caché | Redis | 7 |
| Colas | RabbitMQ + Horizon | 3.13 |
| WebSockets | Laravel Reverb | latest |
| Auth IdP | Keycloak | 24 (en infra/) |
| Frontend | React | 19 |
| Build Tool | Vite | latest |
| Testing Backend | Pest | latest |
| Testing Frontend | Vitest | latest |
| Infraestructura | Docker Compose | v2+ |

---

**Licencia**: MIT — **Mantenido por**: CEEDCV
