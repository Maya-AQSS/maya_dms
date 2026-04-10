# 01 — Arquitectura de Autenticación y Scopes JWT

**Fase:** 1 — Autenticación y Control de Acceso  
**Última actualización:** 2026-04-10  
**Features relacionadas:** F-00.4, F-01.1, F-02.1

---

## 1. Visión General

Maya DMS no tiene pantalla de login propia. La autenticación está **100% delegada a Keycloak** (realm `maya`). El usuario llega a la aplicación ya con un JWT firmado, emitido por el dashboard corporativo o inyectado directamente en la URL de redirección.

```
Dashboard corporativo
        │
        │  Redirect ?access_token=<JWT>
        ▼
  React SPA (frontend)
        │  bootstrapSessionToken() → localStorage
        │  Authorization: Bearer <JWT>
        ▼
  Laravel API (JwtMiddleware)
        │  Valida RS256 contra JWKS de Keycloak
        │  Verifica iss, aud, exp
        ▼
  Request autenticada con jwt_user en $request->attributes
```

---

## 2. Flujo del Token en el Frontend

### 2.1 Bootstrap de sesión (`src/lib/sessionToken.ts`)

`bootstrapSessionToken()` se ejecuta **sincrónicamente antes de que React monte** (`main.tsx`). Busca el token en este orden de prioridad:

1. Query string: `?access_token=`, `?token=`, `?jwt=`
2. Hash fragment: `#access_token=`, `#token=`, `#jwt=`
3. `localStorage` (si ya fue persistido en sesión anterior)
4. Variable de entorno `VITE_DEV_ACCESS_TOKEN` (solo en `import.meta.env.DEV`)

Una vez encontrado, lo persiste en `localStorage['access_token']` y limpia el parámetro de la URL para no dejar el token expuesto en la barra de direcciones.

### 2.2 Adjuntar el token en cada petición (`src/api/http.ts`)

```typescript
const token = getStoredAccessToken(); // lee localStorage
if (token) {
  headers.Authorization = `Bearer ${token}`;
}
```

Si `getStoredAccessToken()` devuelve `null` (token no disponible o no persistido), la petición se envía sin cabecera `Authorization` y el backend responde **401 "Missing Authorization header"**.

---

## 3. Validación en el Backend (`JwtMiddleware.php`)

### 3.1 Cadena de validación

| Paso | Qué valida | Error si falla |
|------|-----------|----------------|
| Extracción | Cabecera `Authorization: Bearer <token>` presente | 401 Missing Authorization header |
| Estructura | JWT con 3 partes separadas por `.` | 401 Malformed JWT |
| `kid` | Campo `kid` presente en el header del JWT | 401 JWT missing kid header |
| Clave pública | `kid` resolvible en JWKS de Keycloak (cacheado en Redis TTL 1h) | 401 invalid_token |
| Firma | RS256 válida contra la clave pública del `kid` | 401 invalid_token |
| `iss` | Igual a `config('auth.jwt_issuer')` → `JWT_ISSUER` en `.env` | 401 invalid_token |
| `aud` | Igual a `config('auth.jwt_audience')` → `JWT_AUDIENCE` en `.env` | 401 invalid_token |
| `exp` | Token no expirado (`StrictValidAt`) | 401 invalid_token |

### 3.2 Claims extraídos al objeto `jwt_user`

Tras la validación, el middleware construye el perfil de sesión y lo cachea en Redis (`jwt_user:{sub}`, TTL 15 min):

| Claim JWT | Campo en `jwt_user` | Fallback |
|-----------|-------------------|---------|
| `sub` | `id` | — (obligatorio) |
| `email` | `email` | `null` |
| `name` | `name` | `null` |
| `department` / `departamento` | `department` | `null` |
| `organization_id` / `org_id` | `organization_id` | `null` |
| `realm_access.roles` | `roles` | `[]` |
| `scope` | `scope` | `''` |

Acceso en controladores: `$request->attributes->get('jwt_user')` o `Auth::user()` (guard `api` registrado en `AppServiceProvider`).

---

## 4. Configuración de Variables de Entorno

### Backend (`backend/.env`)

```dotenv
JWKS_URL=http://maya_auth_keycloak:8080/realms/maya/protocol/openid-connect/certs
JWT_ISSUER=http://keycloak.localhost/realms/maya
JWT_AUDIENCE=maya-dms-backend
```

> **Nota:** `JWKS_URL` usa el nombre de host interno Docker (`maya_auth_keycloak`) porque el backend resuelve la URL dentro de la red `maya_network`. `JWT_ISSUER` usa el nombre público (`keycloak.localhost`) porque ese es el valor que Keycloak escribe en el claim `iss` del token.

### Frontend (`frontend/.env`)

```dotenv
VITE_API_URL=http://maya-dms-api.localhost/api/v1
VITE_DEV_ACCESS_TOKEN=<JWT válido para desarrollo>
```

---

## 5. Configuración de Keycloak para Desarrollo

> **ADVERTENCIA — causa frecuente de 401 en desarrollo**
>
> El valor por defecto de Keycloak para el _Access Token Lifespan_ es **5 minutos**. En este proyecto el realm está configurado con **1 hora**, pero aun así el token almacenado en `VITE_DEV_ACCESS_TOKEN` expirará. Un token expirado provoca 401 `invalid_token` en cada petición porque `JwtMiddleware` valida `exp` estrictamente.
>
> **`VITE_DEV_ACCESS_TOKEN` no se renueva automáticamente.** El frontend no implementa lógica de refresh token. Si el token expira, todas las peticiones API fallan con 401 hasta que se actualice el `.env`.

### 5.1 Solución recomendada: extender el TTL en Keycloak

Ampliar el _Access Token Lifespan_ del realm `maya` a **8 horas** (duración de una jornada de desarrollo) evita tener que regenerar el token constantemente.

Pasos en la consola de Keycloak (`http://keycloak.localhost`):

1. Realm `maya` → **Realm settings** → pestaña **Tokens**
2. Campo **Access Token Lifespan** → cambiar a `8 Hours`
3. Guardar

Con esta configuración, los tokens emitidos durante el día de trabajo no expirarán mientras dura la sesión de desarrollo.

### 5.2 Obtener un nuevo token cuando el actual expira

Si el token ya expiró, obtener uno nuevo con:

```bash
curl -s -X POST http://keycloak.localhost/realms/maya/protocol/openid-connect/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'client_id=maya-dms-frontend' \
  -d 'username=superadmin' \
  -d 'password=<PASSWORD>' \
  -d 'grant_type=password' \
  | jq -r '.access_token'
```

Pegar el resultado como valor de `VITE_DEV_ACCESS_TOKEN` en `frontend/.env` y reiniciar el servidor de desarrollo (`make up` o `npm run dev`).

> El token debe limpiarse de `localStorage` del navegador después de actualizar el `.env`, ya que el frontend usa el valor en caché: DevTools → Application → Local Storage → eliminar la clave `access_token`.

---

## 6. Diagnóstico de 401

| Síntoma en el log del backend | Causa | Solución |
|-------------------------------|-------|---------|
| `Missing Authorization header` | `localStorage['access_token']` vacío; `VITE_DEV_ACCESS_TOKEN` no está definido o no se cargó | Verificar que `bootstrapSessionToken()` se ejecutó y que el `.env` tiene el token |
| `invalid_token` + razón `exp` | Token expirado | Extender TTL en Keycloak (sección 5.1) o regenerar token (sección 5.2) |
| `invalid_token` + razón firma | Token de otro realm o entorno | Verificar que `JWKS_URL`, `JWT_ISSUER` y `JWT_AUDIENCE` en `.env` corresponden al realm activo |
| `JWT missing kid header` | Token mal formado o generado con algoritmo simétrico | Verificar que Keycloak usa RS256 en la configuración del cliente |

---

## 7. Scopes del Token

El JWT emitido por Keycloak en este realm incluye los scopes `email profile`. No se usa el scope `offline_access` (refresh token de larga duración) porque:

- Maya DMS no implementa renovación automática de tokens en el frontend.
- El modelo de sesión es stateless por diseño (Zero Trust): cada petición se valida independientemente.
- En producción, el usuario llegará siempre desde el dashboard corporativo con un token fresco.

Si en el futuro se requiere renovación automática, deberá implementarse en `http.ts` usando el `refresh_token` del claim `offline_access` y un interceptor de fetch que detecte 401 y renueve antes de reintentar.
