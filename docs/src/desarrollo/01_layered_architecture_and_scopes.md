# 01 - Arquitectura por Capas y Global Scopes

## Resumen Ejecutivo

Para garantizar la segregación de datos rigurosa entre tenants (usuarios/grupos) y prevenir vulnerabilidades del tipo Insecure Direct Object Reference (IDOR), se ha implementado un sistema de **Global Scopes** sobre los modelos de dominio centrales (`Document`, `Template`, `Group`, `Comment`). Para soportar esto y conformar estrictamente una Arquitectura por Capas, se ha determinado desterrar el uso genérico de *Implicit Route Model Binding* en la Capa de Presentación a favor de inyecciones a través de los **Contratos de Servicio** (Services Layer).

---

## 1. Prohibición de Route Model Binding en Controladores MVC/API

**Contexto:**
El binding de rutas implícito de Laravel (`public function show(Document $document)`) inyecta directamente un modelo Eloquent desde el router conectando la capa HTTP con la Base de datos sin un paso intermedio.

**Decisión:**
A partir de ahora los controladores NO deben recibir entes de tipo `App\Models\...` en sus firmas. Únicamente deben recibir primitives (ej. UUID `string $id`) correspondientes a las llaves primarias en su petición.
La recuperación del modelo y la comprobación de acceso se delega obligatoriamente al Servicio apropiado mediante su Contrato:

```php
// ❌ INCORRECTO: Viola las capas saltando directo al ORM
public function show(Document $document) 
{
    // ...
}

// ✅ CORRECTO: Respeta la inyección de dependencias y los Global Scopes embebidos
public function show(string $id) 
{
    $document = $this->documentService->findOrFail($id);
    // ...
}
```

**Motivación:**
Asegura que **toda** la lógica de negocio (incluyendo la resolución en base de datos) transite por la capa `Service`. Facilita simulaciones de testing unitario puro sobre controladores y adecúa el sistema para futuros crecimientos que puedan reemplazar Eloquent internamente. Además, el Service es responsable de lanzar `ModelNotFoundException` que terminará siendo transformada en HTTP 404 por el gestor de excepciones globales.

---

## 2. Aislamiento estricto de Datos (< 0.5ms latencia agregada)

**Decisión de rendimiento:**
Para el aislamiento en dominios anidados (ej. `Comment`), se emplean sentencias subqueries nativas estructuradas mediante `whereExists(...)` más `DB::raw()`, rechazando totalmente los helpers tradicionales como `whereHas()`.

**Motivación:**
Dado el requisito técnico de mantener un impacto de overhead residual por debajo de 0.5 ms por query, `whereHas` genera el arranque del modelo instanciando y resolviendo el closure a nivel software en PHP antes de compilar la Query. Empujando `whereExists` inyectamos la cláusula WHERE estática a nivel de compilador SQL logrando tiempos idénticos a joins puros beneficiándose de todos los índices sobre Foreign Keys.

## 3. Fallbacks de Seguridad 404

Los Scopes garantizan que las validaciones son embebidas en el query. Un actor sin los permisos `created_by` / `owner_id` o no registrado como partícipe intentando acceder a un Objeto arrojará 404 por defecto. Esta fue una elección explícita para no confirmar la existencia de recursos inaccesibles de otros clientes mediante HTTP 403 Forbidden.

---

## 4. Verificación y Testing de Aislamiento

**Instrucción de ejecución:**
Para validar que los Global Scopes están funcionando correctamente sin interferencias del entorno local (como errores de permisos en logs), utiliza el siguiente comando:

```bash
LOG_CHANNEL=null php artisan test tests/Feature/GlobalScopesIsolationTest.php
```

**Racional:**
1. **Aislamiento de Entorno**: El uso de `LOG_CHANNEL=null` evita fallos por `Permission Denied` al intentar escribir en `storage/logs/laravel.log`, lo cual ocurre frecuentemente en entornos de desarrollo heterogéneos (Docker vs Host).
2. **Reset de Estado**: En los tests de aislamiento, es mandatorio llamar a `auth()->forgetUser()` entre peticiones de diferentes usuarios para limpiar el estado cacheado del Guard y garantizar que cada petición se evalúe de forma independiente.
3. **Fail-Closed Verification**: Se incluyen tests específicos (`test_unauthenticated_user_returns_nothing`) para asegurar que si el middleware de autenticación es omitido, el Global Scope bloquea los datos preventivamente.
