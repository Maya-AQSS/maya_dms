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

# UID/GID locales para evitar archivos root-owned en bind mounts (ej. frontend/node_modules).
# Permite override explícito vía entorno si fuese necesario.
export LOCAL_UID="${LOCAL_UID:-$(id -u)}"
export LOCAL_GID="${LOCAL_GID:-$(id -g)}"
info "Usando LOCAL_UID=$LOCAL_UID LOCAL_GID=$LOCAL_GID para docker compose"

upsert_env_var() {
  local file="$1"
  local key="$2"
  local value="$3"
  local tmp

  if [[ ! -f "$file" ]]; then
    return 1
  fi

  tmp="$(mktemp)"
  if ! awk -v key="$key" -v value="$value" '
    BEGIN { updated=0 }
    index($0, key "=") == 1 { print key "=" value; updated=1; next }
    { print }
    END { if (!updated) print key "=" value }
  ' "$file" > "$tmp"; then
    rm -f "$tmp"
    return 1
  fi

  if ! mv "$tmp" "$file"; then
    rm -f "$tmp"
    return 1
  fi
}

# ─── Cargar .env ─────────────────────────────────────────────────────────────
if [[ ! -f .env ]]; then
    warn ".env no encontrado — copiando desde .env.example"
    cp .env.example .env
fi
set -a; source .env; set +a

# backend/.env para artisan local e IDE (symlink al .env raíz)
if [[ ! -e backend/.env ]]; then
    ln -sf ../.env backend/.env
    info "backend/.env → .env (symlink para desarrollo local)"
fi

# ─── Detectar APP_KEY vacío en root .env (re-runs con .env pre-existente) ────
NEED_KEY_GENERATE=false
if [[ -z "${APP_KEY:-}" ]]; then
    warn "APP_KEY vacío en .env — se generará automáticamente"
    NEED_KEY_GENERATE=true
fi

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

# ─── Preparar frontend/.env ──────────────────────────────────────────────────
# El .env raíz se monta en el contenedor como /app/.env (docker-compose.yml).
# Para desarrollo local sin Docker (npm run dev) necesitamos frontend/.env con
# las mismas vars VITE_*. Lo generamos automáticamente desde los valores del .env raíz.
if [[ ! -f frontend/.env ]] || [[ ! -s frontend/.env ]]; then
    info "Generando frontend/.env desde variables VITE_* del .env raíz..."
    touch frontend/.env
fi

upsert_env_var frontend/.env VITE_API_URL        "${VITE_API_URL:-http://maya-dms-api.localhost/api/v1}"
upsert_env_var frontend/.env VITE_KEYCLOAK_URL   "${VITE_KEYCLOAK_URL:-http://keycloak.localhost}"
upsert_env_var frontend/.env VITE_KEYCLOAK_REALM "${VITE_KEYCLOAK_REALM:-maya}"
upsert_env_var frontend/.env VITE_KEYCLOAK_CLIENT_ID "${VITE_KEYCLOAK_CLIENT_ID:-maya-dms-dashboard}"
upsert_env_var frontend/.env VITE_REVERB_APP_KEY "${REVERB_APP_KEY:-}"
upsert_env_var frontend/.env VITE_REVERB_HOST    "${VITE_REVERB_HOST:-maya-dms-api.localhost}"
upsert_env_var frontend/.env VITE_REVERB_PORT    "${VITE_REVERB_PORT:-8082}"

# ─── Levantar servicios ──────────────────────────────────────────────────────
info "Levantando servicios..."
docker compose up -d ${EXTRA_FLAGS[@]+"${EXTRA_FLAGS[@]}"}

# ─── Generar APP_KEY si es .env nuevo ─────────────────────────────────────────
if [[ "$NEED_KEY_GENERATE" == true ]]; then
    info "Generando APP_KEY..."
    KEY_SYNCED=false
    for i in $(seq 1 60); do
      if docker exec maya_dms_backend test -f /var/www/html/vendor/autoload.php 2>/dev/null; then
        NEW_KEY=$(docker exec maya_dms_backend php artisan key:generate --show 2>/dev/null || true)
        if [[ -n "$NEW_KEY" && "$NEW_KEY" == base64:* ]]; then
          if ! upsert_env_var .env APP_KEY "$NEW_KEY"; then
            warn "No se pudo actualizar APP_KEY en .env"
            break
          fi

          docker compose up -d backend > /dev/null
          KEY_SYNCED=true
          success "APP_KEY generada y escrita en .env."
        else
          warn "APP_KEY inválida o vacía desde artisan key:generate --show."
        fi
        break
      fi
      sleep 2
    done

    if [[ "$KEY_SYNCED" != true ]]; then
      warn "No se pudo sincronizar APP_KEY automáticamente."
    fi
fi

# ─── Generar Reverb keys si están vacías ─────────────────────────────────────
if [[ -z "${REVERB_APP_KEY:-}" ]]; then
    info "Generando Reverb keys..."
    NEW_REVERB_KEY=$(openssl rand -base64 24 | tr -d '=+/' | head -c 32)
    NEW_REVERB_SECRET=$(openssl rand -base64 32 | tr -d '=+/' | head -c 40)
    upsert_env_var .env REVERB_APP_KEY "$NEW_REVERB_KEY" \
        && upsert_env_var .env REVERB_APP_SECRET "$NEW_REVERB_SECRET" \
        && upsert_env_var .env VITE_REVERB_APP_KEY "$NEW_REVERB_KEY" \
        && success "Reverb keys generadas y sincronizadas." \
        || warn "No se pudieron sincronizar las Reverb keys."
    # Recargar .env para que el resto del script vea los nuevos valores
    set -a; source .env; set +a
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

# Fix vendor ownership para IDE / artisan local (el entrypoint corre como root)
docker exec "$BACKEND_CONTAINER" chown -R "${LOCAL_UID}:${LOCAL_GID}" /var/www/html/vendor 2>/dev/null || true

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
    has_data=$(docker exec "$BACKEND_CONTAINER" php -r "
      try {
        \$h = getenv('DB_HOST') ?: 'maya_infra_postgres';
        \$p = getenv('DB_PORT') ?: '5432';
        \$d = getenv('DB_DATABASE');
        \$u = getenv('DB_USERNAME');
        \$w = getenv('DB_PASSWORD');
        \$pdo = new PDO(\"pgsql:host=\$h;port=\$p;dbname=\$d\", \$u, \$w, [PDO::ATTR_TIMEOUT => 3]);
        \$skip = ['migrations','failed_jobs','jobs','job_batches','cache','cache_locks','password_reset_tokens','sessions'];
        \$tables = \$pdo->query(\"SELECT tablename FROM pg_tables WHERE schemaname = 'public'\")->fetchAll(PDO::FETCH_COLUMN);
        foreach (\$tables as \$table) {
          if (in_array(\$table, \$skip) || str_ends_with(\$table, '_source') || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', \$table)) continue;
          \$stmt = \$pdo->query(\"SELECT 1 FROM \\\"\$table\\\" LIMIT 1\");
          if (\$stmt && \$stmt->fetchColumn() !== false) { echo '1'; exit(0); }
        }
        echo '0';
      } catch (Exception \$e) { exit(2); }
    " 2>/dev/null)
    local rc=$?
    if [[ $rc -ne 0 ]]; then
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
    if-empty|*)
      [[ "$SEED_MODE" == "if-empty" ]] || warn "DB_SEED_MODE inválido ('$SEED_MODE'). Usando 'if-empty'."
      database_has_data && seed_rc=0 || seed_rc=$?
      if [[ $seed_rc -eq 0 ]]; then
        info "DB con datos detectados — no se ejecutan seeds (DB_SEED_MODE=if-empty)."
      elif [[ $seed_rc -eq 2 ]]; then
        SHOULD_SEED=false
      else
        SHOULD_SEED=true
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
echo -e "  ${GREEN}Frontend:${NC}         http://maya_dms.localhost"
echo -e "  ${GREEN}Backend API:${NC}      http://maya_dms_api.localhost/api/v1"
echo -e "  ${GREEN}Keycloak:${NC}         http://keycloak.localhost"
echo -e "  ${GREEN}Traefik dashboard:${NC} http://localhost:8888"
echo ""
echo -e "  ${YELLOW}Acceso directo (sin Traefik):${NC}"
echo -e "    Backend:   http://localhost:${BACKEND_PORT:-8001}"
echo -e "    Frontend:  http://localhost:${FRONTEND_PORT:-5174}"
echo -e "    RabbitMQ:  http://rabbitmq.localhost  (gestionado por infra/)"
echo ""
