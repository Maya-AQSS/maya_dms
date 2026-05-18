# 04 - Versionado y Clonacion (Plantillas y Documentos)

## Resumen Ejecutivo

Se establece un modelo unificado de versionado y clonacion para `Template` y `Document` con estas reglas base:

- **Versionar**: mantiene el mismo `template_id` / `document_id` y crea una nueva version.
- **Clonar**: crea un nuevo `template_id` / `document_id` copiando contenido y configuracion.
- `Document` hereda de `Template` el `process_id` y la visibilidad/contexto, con posibilidad de **bajar nivel solo en visibilidad academica**.
- Al publicar una version se guarda un **snapshot completo inmutable** para auditoria.
- Durante edicion/versionado se permite modelo **delta** (solo cambios), con reconstruccion determinista.

---

## 1. Reglas funcionales cerradas

### 1.1 Versionado vs clonacion

- **Versionado**
  - Misma entidad logica (`template_id` o `document_id` sin cambiar).
  - Nueva version derivada de la ultima version publicada.
  - Inicia ciclo de estado: `draft -> in_review -> published`.
  - En `draft` es editable y reconfigurable, sin permitir cambios de `process_id`.

- **Clonacion**
  - Nueva entidad logica (nuevo ID).
  - Copia completa de propiedades, bloques y validadores/revisores.
  - Estado inicial `draft` (editable y reconfigurable), sin permitir cambios de `process_id`.

### 1.2 Herencia de proceso y visibilidad

- En `Template` y `Document` se conserva:
  - `process_id`
  - `visibility_level`
  - `study_type_id`, `study_id`, `module_id` (si aplica)
  - `team_id` (si aplica)

- En `Document`:
  - `process_id` es **fijo** y heredado de la plantilla (no editable).
  - Si la visibilidad de origen es academica, se permite **bajar nivel**:
    - `study_type -> study -> module`
  - No se permite bajar/subir a `team` desde una visibilidad academica.
  - Si plantilla es `team`, el documento hereda `team_id` y visibilidad `team`.

---

## 2. Modelo de versiones (polimorfico)

Se define una tabla de versiones polimorfica para representar versiones de plantillas y documentos.

### 2.1 Estructura base propuesta

- `id` (uuid)
- `versionable_type` (`Template` | `Document`)
- `versionable_id` (uuid del template/document)
- `version_number` (int incremental por entidad)
- `base_version_id` (nullable, referencia a la version origen)
- `change_set` (json, solo campos cambiados)
- `status` (`draft|in_review|published|rejected|archived` segun dominio)
- `created_by`
- `published_by` (nullable)
- `published_at` (nullable)
- `changelog` (nullable)
- `snapshot_data` (json nullable; obligatorio al publicar)
- `is_snapshot_immutable` (bool, true para snapshots de publicacion)
- `created_at`, `updated_at`

### 2.2 Delta durante trabajo + snapshot al publicar

- **Durante edicion**: se persiste `change_set` respecto a `base_version_id`.
- **Al publicar**:
  - se genera `snapshot_data` completo de la entidad publicada,
  - `is_snapshot_immutable = true`,
  - se usa para auditoria y lectura historica exacta.

---

## 3. Reconstruccion de una version

Para obtener el estado efectivo de una version:

1. Resolver cadena `base_version_id` hasta raiz.
2. Aplicar `change_set` en orden desde la base hacia la version objetivo.
3. Si la version objetivo tiene `snapshot_data` (publicada), ese snapshot prevalece como fuente canonica historica.

Reglas:

- La reconstruccion debe ser determinista.
- No se permite ciclo en cadena de bases.
- Si falta una base requerida, la version se considera inconsistente y debe fallar en lectura con error controlado.

---

## 4. Compatibilidad y migracion progresiva

Actualmente existen `template_versions` y `document_versions`. La migracion sera progresiva:

1. Introducir tabla polimorfica sin eliminar tablas actuales.
2. Doble escritura controlada (feature flag) durante transicion.
3. Migrar historico a la tabla polimorfica.
4. Cambiar lecturas a la nueva tabla.
5. Retirar tablas legacy cuando se valide consistencia.

Principio de seguridad:

- No se rompe auditoria historica.
- Toda version publicada debe seguir siendo consultable con snapshot completo.

---

## 5. Criterios de aceptacion funcional

- Versionar no crea nuevo `template_id`/`document_id`.
- Clonar si crea nuevo `template_id`/`document_id`.
- Documento no permite editar `process_id`.
- Bajar nivel academico permitido solo `study_type -> study -> module`.
- Flujos `team` heredan `team_id` sin conversion a academico.
- Toda publicacion genera snapshot completo inmutable.
- Se puede reconstruir cualquier version publicada sin depender del estado actual.
