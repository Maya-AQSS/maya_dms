# Configuración provisional FDW en desarrollo

## Contexto

La tarea consiste en habilitar la extensión `postgres_fdw` y configurar acceso **solo lectura** a la tabla de usuarios del dashboard corporativo mediante:

- `FOREIGN SERVER`  
- `USER MAPPING`  
- `FOREIGN TABLE users_fdw`  
- `VIEW users`  

La migración de Laravel (`create_users_foreign_table.php`) crea estos objetos automáticamente al ejecutar `./up.sh` o `make migrate`.

---

## Problema identificado

1. El script `infra/docker/postgres/init-databases.sh` ya crea la extensión `postgres_fdw` en `maya_dms_db` con el superusuario `maya`.  

2. Sin embargo, el usuario de aplicación (`maya_dms_user`) **no tiene privilegio `USAGE`** sobre el foreign data wrapper, por lo que no puede crear `FOREIGN SERVER` ni `USER MAPPING`.  

3. Como resultado, la migración falla al ejecutarse con `maya_dms_user`:
SQLSTATE[42501]: Insufficient privilege: 7 ERROR: permission denied for foreign-data wrapper postgres_fdw


---

## Decisión provisional

Para permitir que **todo el equipo pueda ejecutar `./up.sh` sin errores**, se opta por otorgar privilegios de superusuario a `maya_dms_user` **únicamente en desarrollo**:

```sql
ALTER USER maya_dms_user WITH SUPERUSER;
```

Esto permite que la migración cree los objetos FDW automáticamente sin requerir intervención de un DBA.

---
## Instrucciones para el equipo

### Primera vez (o después de docker volume prune)
1. Clonar el repo de infra al mismo nivel que maya_dms/:

``
Desktop/desarrollo/
├── infra/          ← repo de infraestructura
└── maya_dms/       ← este repo
``

2. Levantar todo desde la raíz del proyecto:
``
./up.sh
``
Esto arranca infra, backend, frontend y ejecuta migraciones automáticamente. La migración FDW fallará en este punto por falta de permisos.

3. Otorgar privilegios (una sola vez por volumen de PostgreSQL):

``
docker exec -it maya_infra_postgres psql -U maya -d maya_dms_db -c "ALTER USER maya_dms_user WITH SUPERUSER;"
``

4. Re-ejecutar las migraciones: ``make migrate``

O alternativamente:

``docker exec maya_dms_backend php artisan migrate --force``

5. Verificar que los objetos FDW existen:

``docker exec -it maya_infra_postgres psql -U maya_dms_user -d maya_dms_db -c "\des+"
docker exec -it maya_infra_postgres psql -U maya_dms_user -d maya_dms_db -c "SELECT * FROM users LIMIT 5;"``

---
### Si ya tienes el entorno corriendo
1. Solo necesitas el paso 3 (grant) y paso 4 (migrate).

2. Después de ./up.sh --build o rebuild: 
El grant persiste mientras no se elimine el volumen de PostgreSQL. Si hiciste docker volume prune o docker compose down -v, repite desde el paso 2.