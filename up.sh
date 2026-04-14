#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# up.sh — Script de arranque de Maya DMS
#
# Uso:
#   ./up.sh            Arranca todos los servicios
#   ./up.sh --build    Fuerza rebuild de imágenes
#   ./up.sh down       Para todos los servicios
#   ./up.sh logs       Sigue los logs de todos los servicios
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ─── Colores ─────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info()    { echo -e "${CYAN}[maya-dms]${NC} $*"; }
success() { echo -e "${GREEN}[maya-dms]${NC} $*"; }
warn()    { echo -e "${YELLOW}[maya-dms]${NC} $*"; }

# ─── Cargar .env ─────────────────────────────────────────────────────────────
if [[ ! -f .env ]]; then
    warn ".env no encontrado — copiando desde .env.example"
    cp .env.example .env
fi
set -a; source .env; set +a

# ─── Subcomandos rápidos ──────────────────────────────────────────────────────
case "${1:-}" in
    down)
        info "Parando todos los servicios..."
        docker compose down
        exit 0
        ;;
    logs)
        docker compose logs -f "${@:2}"
        exit 0
        ;;
    ps|status)
        docker compose ps
        exit 0
        ;;
esac

# ─── Verificar y levantar infra compartida ───────────────────────────────────
# Por defecto busca en ../infra (repo hermano). Puedes sobreescribir con:
#   MAYA_INFRA_DIR=/ruta/absoluta/a/infra ./up.sh
INFRA_SCRIPT="${MAYA_INFRA_DIR:-$SCRIPT_DIR/../maya_infra}/ensure-running.sh"
if [[ -f "$INFRA_SCRIPT" ]]; then
    bash "$INFRA_SCRIPT"
else
    warn "Script de infra no encontrado en: $INFRA_SCRIPT"
    warn "Clona el repo de infra al mismo nivel o define MAYA_INFRA_DIR=/ruta/a/infra"
    exit 1
fi

# ─── Flags extra ─────────────────────────────────────────────────────────────
EXTRA_FLAGS=()
[[ "${1:-}" == "--build" ]] && EXTRA_FLAGS+=("--build")

# ─── Preparar backend/.env ────────────────────────────────────────────────────
if [[ ! -f backend/.env ]]; then
    warn "backend/.env no encontrado — creando desde .env.example"
    cp backend/.env.example backend/.env
    NEED_KEY_GENERATE=true
else
    NEED_KEY_GENERATE=false
fi

# ─── Levantar servicios ──────────────────────────────────────────────────────
info "Levantando servicios..."
docker compose up -d ${EXTRA_FLAGS[@]+"${EXTRA_FLAGS[@]}"}

# ─── Generar APP_KEY si es .env nuevo ─────────────────────────────────────────
if [[ "$NEED_KEY_GENERATE" == true ]]; then
    info "Generando APP_KEY..."
    for i in $(seq 1 10); do
      if docker exec maya_dms_backend php -v > /dev/null 2>&1; then
        docker exec maya_dms_backend php artisan key:generate --force
        success "APP_KEY generada."
        break
      fi
      sleep 2
    done
fi

# ─── Migraciones automáticas ──────────────────────────────────────────────────
BACKEND_CONTAINER="maya_dms_backend"
DB_READY=false

# 1) Esperar a que el contenedor responda
info "Esperando a que el backend esté listo..."
for i in $(seq 1 20); do
  if docker exec "$BACKEND_CONTAINER" php -v > /dev/null 2>&1; then
    break
  fi
  sleep 2
done

# 1b) Esperar a que composer install termine (el entrypoint lo ejecuta en background)
info "Esperando a que composer install termine..."
for i in $(seq 1 30); do
  if docker exec "$BACKEND_CONTAINER" test -f /var/www/html/vendor/autoload.php > /dev/null 2>&1; then
    break
  fi
  if (( i % 5 == 0 )); then
    info "  … composer install en curso ($((i * 3))s/90s)"
  fi
  sleep 3
done

# 2) Esperar conexión con la BD (PDO directo — sin bootstrap de Laravel)
info "Esperando conexión con la base de datos..."
for i in $(seq 1 40); do
  DB_ERR=$(docker exec "$BACKEND_CONTAINER" php -r '
    try {
      $h = getenv("DB_HOST") ?: "maya_infra_postgres";
      $p = getenv("DB_PORT") ?: "5432";
      $d = getenv("DB_DATABASE");
      $u = getenv("DB_USERNAME");
      $w = getenv("DB_PASSWORD");
      new PDO("pgsql:host=$h;port=$p;dbname=$d", $u, $w, [PDO::ATTR_TIMEOUT => 3]);
    } catch (Exception $e) {
      fwrite(STDERR, $e->getMessage());
      exit(1);
    }' 2>&1 >/dev/null) && { DB_READY=true; break; }
  if (( i % 10 == 0 )); then
    info "  … esperando BD ($((i * 3))s/120s): $DB_ERR"
  fi
  sleep 3
done

# 3) Ejecutar migraciones si la BD está accesible
if [[ "$DB_READY" == true ]]; then
  SEED_MODE="${DB_SEED_MODE:-if-empty}" # always | if-empty | never

  database_has_data() {
    local has_data
    has_data=$(docker exec "$BACKEND_CONTAINER" php -r '
      try {
        $h = getenv("DB_HOST") ?: "maya_infra_postgres";
        $p = getenv("DB_PORT") ?: "5432";
        $d = getenv("DB_DATABASE");
        $u = getenv("DB_USERNAME");
        $w = getenv("DB_PASSWORD");
        $pdo = new PDO("pgsql:host=$h;port=$p;dbname=$d", $u, $w, [PDO::ATTR_TIMEOUT => 3]);

        $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = ''public'' AND tablename NOT IN (''migrations'', ''failed_jobs'', ''jobs'', ''job_batches'', ''cache'', ''cache_locks'', ''password_reset_tokens'', ''sessions'')")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
          if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            continue;
          }

          $stmt = $pdo->query("SELECT 1 FROM \"{$table}\" LIMIT 1");
          if ($stmt && $stmt->fetchColumn() !== false) {
            echo "1";
            exit(0);
          }
        }

        echo "0";
      } catch (Exception $e) {
        exit(2);
      }')

    if [[ "$?" -ne 0 ]]; then
      warn "No se pudo verificar el estado de la BD — se omiten seeds por seguridad."
      return 2
    fi

    [[ "$has_data" == "1" ]]
  }

  info "Aplicando migraciones..."
  docker exec "$BACKEND_CONTAINER" php artisan migrate --force
  success "Migraciones aplicadas/verificadas."

  SHOULD_SEED=false
  case "$SEED_MODE" in
    always)
      SHOULD_SEED=true
      ;;
    never)
      SHOULD_SEED=false
      ;;
    if-empty)
      if database_has_data; then
        info "DB con datos detectados — no se ejecutan seeds (DB_SEED_MODE=if-empty)."
      else
        if [[ "$?" -eq 2 ]]; then
          SHOULD_SEED=false
        else
          SHOULD_SEED=true
        fi
      fi
      ;;
    *)
      warn "DB_SEED_MODE inválido ('$SEED_MODE'). Usando 'if-empty'."
      if database_has_data; then
        info "DB con datos detectados — no se ejecutan seeds (DB_SEED_MODE=if-empty)."
      else
        if [[ "$?" -eq 2 ]]; then
          SHOULD_SEED=false
        else
          SHOULD_SEED=true
        fi
      fi
      ;;
  esac

  if [[ "$SHOULD_SEED" == true ]]; then
    info "Ejecutando seeders (DB_SEED_MODE=$SEED_MODE)..."
    docker exec "$BACKEND_CONTAINER" php artisan db:seed --force
    success "Seeders aplicados."
  else
    success "Seeders omitidos (DB_SEED_MODE=$SEED_MODE)."
  fi
else
  warn "No se pudo conectar con la BD — omitiendo migraciones automáticas."
  warn "Ejecuta manualmente: docker exec $BACKEND_CONTAINER php artisan migrate --seed --force"
fi

# ─── URLs de acceso ───────────────────────────────────────────────────────────
echo ""
success "Sistema listo. Accesos disponibles:"
echo -e "  ${GREEN}Frontend:${NC}         http://maya-dms.localhost"
echo -e "  ${GREEN}Backend API:${NC}      http://maya-dms-api.localhost/api/v1"
echo -e "  ${GREEN}Keycloak:${NC}         http://keycloak.localhost"
echo -e "  ${GREEN}Traefik dashboard:${NC} http://localhost:8888"
echo ""
echo -e "  ${YELLOW}Acceso directo (sin Traefik):${NC}"
echo -e "    Backend:   http://localhost:${BACKEND_PORT:-8001}"
echo -e "    Frontend:  http://localhost:${FRONTEND_PORT:-5174}"
echo -e "    RabbitMQ:  http://rabbitmq.localhost  (gestionado por infra/)"
echo ""
