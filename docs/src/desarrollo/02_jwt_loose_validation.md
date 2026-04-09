# JWT: uso de `LooseValidAt` en lugar de `StrictValidAt`

## Decisión

El middleware `JwtMiddleware` usa `LooseValidAt` (librería `lcobucci/jwt`)
como constraint de validación temporal en lugar de `StrictValidAt`.

---

## Por qué

`StrictValidAt` exige que el token JWT incluya el claim `nbf` (Not Before).
Keycloak no emite `nbf` en sus access tokens por defecto — es un claim
opcional según la especificación RFC 7519.

Durante la integración con Keycloak local se detectó que todos los tokens
reales eran rechazados con 401 y el error: The token violates some mandatory constraints: "Not Before" claim missing

## Qué valida `LooseValidAt`

`LooseValidAt` sigue aplicando las validaciones temporales esenciales:

| Claim | Validación |
|-------|-----------|
| `exp` | El token no ha expirado ✅ |
| `iat` | El token fue emitido en el pasado ✅ |
| `nbf` | Solo se valida **si está presente** en el token |

No se pierde seguridad relevante: un token expirado sigue siendo rechazado.

## Alternativa descartada

Añadir un protocol mapper en Keycloak que inyecte `nbf: 0` hardcodeado
en todos los tokens. Se descartó porque es un valor semánticamente
incorrecto (`nbf: 0` significa "válido desde el epoch Unix") y acopla
la configuración del backend a un quirk de configuración de Keycloak.

---

## Configuración del entorno local para desarrollo

Esta sección documenta los pasos necesarios para que un desarrollador
pueda verificar el endpoint `/api/v1/hierarchy` en local desde cero.

> Las credenciales de Keycloak (admin, usuarios de prueba) son conocidas
> por el equipo. Los comandos usan `<ADMIN_PASSWORD>` y `<USER_PASSWORD>`
> como placeholders — sustitúyelos por los valores correspondientes.

### Requisitos previos

- Stack levantado: `docker compose up -d`
- Keycloak accesible en `http://keycloak.localhost`
- Acceso admin a Keycloak

### 1. Configurar Keycloak

El realm `maya` debe tener un usuario funcional y el client
`maya-dms-frontend` debe incluir el audience `maya-dms-backend`
en los tokens. Si el entorno es nuevo, seguir estos pasos.

> Si ya existe un usuario operativo en el realm `maya` y el mapper
> de audience ya está configurado, saltar directamente al paso 2.

#### 1.1 Crear el usuario de prueba

```bash
# Autenticarse como admin
docker exec -it maya_auth_keycloak /opt/keycloak/bin/kcadm.sh config credentials \
  --server http://localhost:8080 --realm master \
  --user admin --password <ADMIN_PASSWORD>

# Crear usuario
docker exec -it maya_auth_keycloak /opt/keycloak/bin/kcadm.sh create users \
  -r maya \
  -s username=docente_dev \
  -s email=docente_dev@maya.local \
  -s emailVerified=true \
  -s enabled=true \
  -s 'requiredActions=[]'
```

Obtener el ID del usuario creado:

```bash
docker exec -it maya_auth_keycloak /opt/keycloak/bin/kcadm.sh get users \
  -r maya --query username=docente_dev \
  | python3 -c "import sys,json; print(json.load(sys.stdin)[0]['id'].strip())"
```

Establecer contraseña **permanente** via API REST:

```bash
ADMIN_TOKEN=$(curl -s -X POST \
  "http://keycloak.localhost/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "client_id=admin-cli" \
  -d "username=admin" \
  -d "password=<ADMIN_PASSWORD>" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'].strip())")

curl -s -X PUT \
  "http://keycloak.localhost/admin/realms/maya/users/<USER_ID>/reset-password" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"password","value":"<USER_PASSWORD>","temporary":false}'
```

> ⚠️ **Importante:** no usar `kcadm.sh set-password` para establecer
> la contraseña. En la versión de Keycloak de este proyecto, ese comando
> siempre crea la contraseña como temporal, lo que provoca el error
> `Account is not fully set up` al intentar obtener el token. Usar
> siempre la API REST con `temporary: false` explícito.

#### 1.2 Añadir el audience mapper al client `maya-dms-frontend`

El backend valida el claim `aud: maya-dms-backend`. Sin este mapper,
todos los tokens son rechazados con `not allowed to be used by this audience`.

```bash
# Obtener el ID del client maya-dms-frontend
CLIENT_ID=$(curl -s \
  "http://keycloak.localhost/admin/realms/maya/clients?clientId=maya-dms-frontend" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)[0]['id'].strip())")

# Añadir el mapper
curl -s -X POST \
  "http://keycloak.localhost/admin/realms/maya/clients/$CLIENT_ID/protocol-mappers/models" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "maya-dms-backend-audience",
    "protocol": "openid-connect",
    "protocolMapper": "oidc-audience-mapper",
    "config": {
      "included.client.audience": "maya-dms-backend",
      "id.token.claim": "false",
      "access.token.claim": "true"
    }
  }'
```

> Este paso solo hay que hacerlo una vez por entorno. Si el mapper
> ya existe, Keycloak devolverá un error 409 que se puede ignorar.

### 2. Obtener un token válido y configurar el frontend

Los tokens de Keycloak expiran en **1 hora**. Repetir este paso cada
vez que el token expire.

```bash
# Obtener token fresco
TOKEN=$(curl -s -X POST \
  "http://keycloak.localhost/realms/maya/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "client_id=maya-dms-frontend" \
  -d "username=<USERNAME>" \
  -d "password=<USER_PASSWORD>" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'].strip())")

# Escribirlo en el .env del frontend
sed -i "s|^VITE_DEV_ACCESS_TOKEN=.*|VITE_DEV_ACCESS_TOKEN=$TOKEN|" frontend/.env
```

### 3. Levantar el frontend

El volumen `./frontend:/app` del `docker-compose.yml` monta la carpeta
local sobre el contenedor en tiempo de ejecución, por lo que Vite lee
el `.env` directamente del disco sin necesidad de reconstruir la imagen
al cambiar el token. Solo reconstruir si es la primera vez o si han
cambiado dependencias:

```bash
docker compose build frontend && docker compose up -d frontend
```

### 4. Limpiar el storage del navegador

El frontend cachea el token en `sessionStorage`. Si se ha actualizado
el token en `.env`, hay que limpiar el storage antes de recargar para
que el frontend lo relea. Ejecutar en la consola del navegador (F12):

```javascript
sessionStorage.clear();
localStorage.clear();
location.reload();
```

> ⚠️ Este paso es necesario cada vez que se renueve el token. Sin él,
> el navegador seguirá usando el token expirado aunque el `.env` tenga
> uno nuevo.

### 5. Verificar que todo funciona

```bash
# El backend debe devolver 200 en menos de 300ms
curl -s -o /dev/null -w "HTTP %{http_code} | %{time_total}s\n" \
  -H "Authorization: Bearer $TOKEN" \
  http://localhost:8001/api/v1/hierarchy
```

En el navegador, abrir DevTools → Network → filtrar por `hierarchy`:
- Debe aparecer **una sola petición** con status **200** ✅
- Al navegar entre secciones, **no debe aparecer ninguna petición adicional** ✅

---

## Referencias

- [RFC 7519 — JWT Claims](https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.5)
- [lcobucci/jwt — LooseValidAt](https://lcobucci-jwt.readthedocs.io/en/latest/validating-tokens/)
- Keycloak issue tracker: `nbf` no se emite por defecto en access tokens