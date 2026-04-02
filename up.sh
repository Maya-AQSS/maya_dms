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
INFRA_SCRIPT="${MAYA_INFRA_DIR:-$SCRIPT_DIR/../infra}/ensure-running.sh"
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

# ─── Levantar servicios ──────────────────────────────────────────────────────
info "Levantando servicios..."
docker compose up -d "${EXTRA_FLAGS[@]}"

# ─── Migraciones automáticas ──────────────────────────────────────────────────
info "Comprobando estado de la base de datos..."
RETRIES=15
until docker exec maya_dms_backend php artisan migrate:status > /dev/null 2>&1; do
  RETRIES=$((RETRIES - 1))
  if [[ $RETRIES -eq 0 ]]; then
    warn "Backend aún no conecta con la BD — omitiendo migraciones automáticas."
    break
  fi
  sleep 2
done

if [[ $RETRIES -gt 0 ]]; then
  PENDING=$(docker exec maya_dms_backend php artisan migrate:status 2>&1 | grep -c "Pending" || true)
  TOTAL=$(docker exec maya_dms_backend php artisan migrate:status 2>&1 | grep -cE "Ran|Pending" || true)

  if [[ "$TOTAL" -eq 0 ]] || [[ "$TOTAL" -eq "$PENDING" ]]; then
    info "Base de datos vacía — ejecutando migraciones y seeds..."
    docker exec maya_dms_backend php artisan migrate --seed --force
    success "Migraciones y seeds aplicados."
  elif [[ "$PENDING" -gt 0 ]]; then
    info "${PENDING} migraciones pendientes — ejecutando migrate..."
    docker exec maya_dms_backend php artisan migrate --force
    success "Migraciones aplicadas."
  else
    success "Base de datos al día — nada que migrar."
  fi
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
