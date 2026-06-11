# changes.md — refactor/unify-template-document

Registro de cambios funcionales (no puramente estructurales) introducidos al
unificar los dominios Templates y Documents. Cada entrada documenta un cambio
de comportamiento observable: timeout, validación, autorización, wire format,
defaults de sort, códigos HTTP.

Los refactors que preservan comportamiento (extracción de helpers, paso por
capa Service/Repo sin cambiar la respuesta) NO se registran aquí.

## Formato de entrada

```markdown
## [FASE X.Y] <título corto del cambio>

- **Fecha**: YYYY-MM-DD
- **Severidad**: CRITICAL | HIGH | MEDIUM
- **Qué cambió**: comportamiento antes → después
- **Por qué**: razón de la igualación (clase compartida / decisión de seguridad)
- **Endpoint(s) afectado(s)**: método HTTP + ruta
- **Impacto en cliente**: ¿lo percibe el frontend o un consumidor API?
- **Decidido por**: agente/usuario que aprobó el cambio
```

---

## Decisiones pendientes (requieren aprobación explícita antes de tocar)

| Fase | Divergencia actual | Opciones |
|---|---|---|
| 6.3 | `PublishDocumentRequest::authorize()` devuelve `true` (la autorización solo se ejerce en el controller); `PublishTemplateRequest` sí autoriza con `can('publish')` | (a) igualar a Template (doble guardia — **CRITICAL**, cambio de seguridad) · (b) dejar y documentar |
| 3.9 | `process_id` sin `exists:processes,id` en Documents; `study_type_id/study_id/module_id` `string` (Templates) vs `uuid` (Documents) | igualar validaciones (HIGH) o preservar |
| 4.1 | Timeout WeasyPrint: 60s (template) vs 30s (theme) | unificar valor al extraer `WeasyPrintRunner` |
| 6.6 | `sortBy` default `updated_at` (templates) vs `created_at` (documents); respuestas `Resource::response()` vs `response()->json(['data'=>...])` manual; `destroy` 204 vs Resource | igualar o preservar por endpoint |

## Asimetrías preservadas (decisión de plan — NO se unifican)

- `startNewRevisionCycle` (D-12): fuentes de snapshot divergentes entre dominios; riesgo ALTO.
- `DocumentRenderService::renderHtmlForVersion`: las plantillas no renderizan histórico por versión.
- `DocumentPdfService::generate`: persiste PDF a disco; los de template/theme son efímeros.
- `DocumentMigrationBlockDiffer` / `DocumentMigrationPayloadResolver`: la migración entre versiones de plantilla es concepto exclusivo de documentos.
- `TemplateVersionBlockLayerResolver::backfillStructuralFields`: corrección de snapshots legacy de plantilla.
- Broadcast realtime (`BroadcastNotificationCreated`) solo en documentos: queda como flag condicional en `ReviewValidationNotifier`.
- `store()/clone()` de documents devuelve blocks inline y el de templates no: asimetría correcta por diseño.

---

<!-- Entradas de cambios a partir de aquí, en orden cronológico -->
