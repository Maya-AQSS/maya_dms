#!/usr/bin/env bash
# up.sh — Arranque de Maya DMS. Ver maya_infra/scripts/up-common.sh.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

SERVICE_LABEL="maya-dms"
BACKEND_CONTAINER="maya_dms_backend"
FRONTEND_URL="http://maya-dms.maya.test"
BACKEND_API_URL="http://maya-dms-api.maya.test/api/v1"
DEFAULT_BACKEND_PORT="8001"
DEFAULT_FRONTEND_PORT="5174"
SKIP_TABLES_SUFFIX_SOURCE=true

setup_frontend_env() {
    upsert_env_var frontend/.env VITE_API_URL            "${VITE_API_URL:-http://maya-dms-api.maya.test/api/v1}"
    upsert_env_var frontend/.env VITE_KEYCLOAK_URL       "${VITE_KEYCLOAK_URL:-http://keycloak.maya.test}"
    upsert_env_var frontend/.env VITE_KEYCLOAK_REALM     "${VITE_KEYCLOAK_REALM:-maya}"
    upsert_env_var frontend/.env VITE_KEYCLOAK_CLIENT_ID "${VITE_KEYCLOAK_CLIENT_ID:-maya-dms-dashboard}"
    upsert_env_var frontend/.env VITE_REVERB_APP_KEY     "${REVERB_APP_KEY:-}"
    upsert_env_var frontend/.env VITE_REVERB_HOST        "${VITE_REVERB_HOST:-maya-dms-api.maya.test}"
    upsert_env_var frontend/.env VITE_REVERB_PORT        "${VITE_REVERB_PORT:-8082}"
}

post_key_generate_hook() {
    if [[ -z "${REVERB_APP_KEY:-}" ]]; then
        info "Generando Reverb keys..."
        NEW_REVERB_KEY=$(openssl rand -base64 24 | tr -d '=+/' | head -c 32)
        NEW_REVERB_SECRET=$(openssl rand -base64 32 | tr -d '=+/' | head -c 40)
        upsert_env_var .env REVERB_APP_KEY    "$NEW_REVERB_KEY"    \
            && upsert_env_var .env REVERB_APP_SECRET "$NEW_REVERB_SECRET" \
            && upsert_env_var .env VITE_REVERB_APP_KEY "$NEW_REVERB_KEY" \
            && success "Reverb keys generadas y sincronizadas." \
            || warn "No se pudieron sincronizar las Reverb keys."
        set -a; source .env; set +a
    fi
}

extra_direct_urls() {
    echo -e "    RabbitMQ:  http://rabbitmq.localhost  (gestionado por infra/)"
}

# shellcheck source=../maya_infra/scripts/up-common.sh
source "${MAYA_INFRA_DIR:-"$SCRIPT_DIR/../maya_infra"}/scripts/up-common.sh"
