.PHONY: install up down restart logs shell-backend shell-frontend migrate seed test lint reverb

# ─── Setup inicial ────────────────────────────────────────────
install:
	@echo ">>> Construyendo imágenes..."
	docker compose build
	@echo ">>> Levantando servicios..."
	docker compose up -d
	@echo ">>> Esperando PostgreSQL..."
	docker compose exec postgres pg_isready -U $${DB_USERNAME:-maya_dms_user} -d $${DB_DATABASE:-maya_dms_db} 2>/dev/null || sleep 5
	@echo ">>> Generando APP_KEY..."
	docker compose exec backend php artisan key:generate --force
	@echo ">>> Ejecutando migraciones..."
	docker compose exec backend php artisan migrate --force
	@echo ">>> Ejecutando seeders..."
	docker compose exec backend php artisan db:seed --force
	@echo ">>> Instalando dependencias frontend..."
	docker compose exec frontend npm install
	@echo ""
	@echo "✓ Maya DMS listo en:"
	@echo "  Frontend → http://maya-dms.localhost"
	@echo "  API      → http://maya-dms-api.localhost"
	@echo "  RabbitMQ → http://localhost:15672"
	@echo "  Backend  → http://localhost:8001"

# ─── Ciclo de vida ────────────────────────────────────────────
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

# ─── Shells ───────────────────────────────────────────────────
shell-backend:
	docker compose exec backend bash

shell-frontend:
	docker compose exec frontend sh

# ─── Base de datos ────────────────────────────────────────────
migrate:
	docker compose exec backend php artisan migrate

migrate-fresh:
	docker compose exec backend php artisan migrate:fresh --seed

seed:
	docker compose exec backend php artisan db:seed

# ─── Tests ────────────────────────────────────────────────────
test:
	docker compose exec backend php artisan test

test-backend:
	docker compose exec backend php artisan test --coverage --min=80

test-frontend:
	docker compose exec frontend npm run test

# ─── Linting ─────────────────────────────────────────────────
lint:
	docker compose exec backend ./vendor/bin/pint
	docker compose exec frontend npx biome check --write ./src

# ─── Horizon (RabbitMQ queue monitor) ────────────────────────
horizon:
	docker compose exec backend php artisan horizon

horizon-status:
	docker compose exec backend php artisan horizon:status

# ─── Reverb (WebSocket server) ────────────────────────────────
reverb:
	docker compose exec backend php artisan reverb:start --host=0.0.0.0 --port=8080

# ─── Utilidades ───────────────────────────────────────────────
key-generate:
	docker compose exec backend php artisan key:generate --force

route-list:
	docker compose exec backend php artisan route:list --path=api
