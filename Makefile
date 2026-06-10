.PHONY: install up down restart logs shell-backend shell-frontend migrate seed test lint reverb pdf-poc pdf-a11y-check

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
# La suite corre contra Postgres REAL en la BD `maya_dms_test` (NO sqlite): DMS usa SQL
# específico de Postgres (GREATEST, operadores jsonb) en los repos. El host/puerto de la BD
# se toman del entorno del slot y la BD de tests se aísla de la de runtime — todo lo resuelven
# `phpunit.xml` + `tests/bootstrap.php`, sin fijar nada a mano (portable entre slots).
# Usamos `pest --no-coverage` porque `artisan test` con pcov puede agotar memoria (OOM).
test:
	docker compose exec backend ./vendor/bin/pest --no-coverage

# Con cobertura: pcov es pesado; si OOMea, correr Unit y Feature por separado.
test-backend:
	docker compose exec backend ./vendor/bin/pest --coverage --min=80

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

# ─── PDF (Phase 0 POC + Phase 4 a11y CI) ──────────────────────
# Genera un PDF/UA POC y lo deja en backend/storage/app/poc/document.pdf.
# Requiere que la imagen backend incluya el binario weasyprint (ver backend/Dockerfile).
pdf-poc:
	docker compose exec backend php artisan pdf:poc
	@echo ">>> PDF: backend/storage/app/poc/document.pdf"

# Valida los PDFs fixture contra el perfil PDF/UA-1 usando verapdf en container.
# Falla si algún PDF no cumple. Pensado para CI (Phase 4).
pdf-a11y-check:
	@if [ ! -f backend/storage/app/poc/document.pdf ]; then \
		echo "✗ Falta storage/app/poc/document.pdf — ejecuta 'make pdf-poc' primero"; exit 1; \
	fi
	docker run --rm -v "$(PWD)/backend/storage/app:/in" verapdf/cli --profile ua1 /in/poc/document.pdf
