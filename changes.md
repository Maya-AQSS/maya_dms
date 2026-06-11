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

## [FASE 4.1] Formato unificado del mensaje de error de WeasyPrint (solo logs)

- **Fecha**: 2026-06-11
- **Severidad**: MEDIUM (solo observable en logs internos)
- **Qué cambió**: los 3 mensajes de RuntimeException por fallo de WeasyPrint
  ("WeasyPrint falló al generar el PDF de la plantilla/del theme/para documento {id}")
  se normalizan a "WeasyPrint falló al generar el PDF {contexto}: {stderr}" con
  contexto por caller ("de la plantilla {id}", "de muestra del theme {id}",
  "para documento {id}"). Los timeouts NO se igualan (template/document 60s, theme 30s).
- **Por qué**: extracción de `Support/WeasyPrintRunner` compartido por los 3 PdfServices.
- **Endpoint(s) afectado(s)**: ninguno en respuesta (RuntimeException → 500 genérico; el mensaje no llega al cliente).
- **Impacto en cliente**: ninguno. Solo cambia el texto en logs para triage.
- **Decidido por**: plan Fase 4.1 (timeouts preservados por decisión explícita).

## [FASE 4.5] Divergencia de normalización académica preservada vía parámetro

- **Fecha**: 2026-06-11
- **Severidad**: N/A (sin cambio de comportamiento — registro de asimetría parametrizada)
- **Qué cambió**: nada funcional. La lógica duplicada de nulificación de scope
  académico se unificó en `Support/AcademicScopeNormalizer` con flag
  `strictTemplateIds`: Template (true) SIEMPRE escribe `study_type_id/study_id/module_id`
  del template (incluso null); Document (false) solo los escribe cuando el valor
  del template no es null. Ambos comportamientos previos se conservan tal cual.
- **Por qué**: la divergencia es real en el código original; igualarla cambiaría
  qué campos se pisan en updates de documentos. Queda parametrizada y documentada.
- **Decidido por**: plan Fase 4.5 (no igualar a ciegas).
