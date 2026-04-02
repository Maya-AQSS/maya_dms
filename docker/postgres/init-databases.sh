#!/usr/bin/env bash
set -e

DB_USER="${POSTGRES_USER:-maya_dms_user}"
DB_MAIN="${POSTGRES_DB:-maya_dms_db}"

echo ">>> [init] Habilitando extensiones en ${DB_MAIN}"
psql -v ON_ERROR_STOP=1 --username "$DB_USER" --dbname "$DB_MAIN" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
    CREATE EXTENSION IF NOT EXISTS postgres_fdw;
EOSQL

echo ">>> [init] Completado."
