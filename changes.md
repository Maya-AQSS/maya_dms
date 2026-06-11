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

## Decisiones pendientes

Ninguna. Las 4 decisiones (6.3, 3.9, 4.1, 6.6) fueron aprobadas por el usuario
el 2026-06-11 y aplicadas — ver entradas al final. Única igualación descartada:
`destroy` de Template (el frontend usa la señal 204-física vs 200+Resource-archivada).

## Asimetrías — estado final (revisado 2026-06-11 tras 2ª ronda de unificación)

UNIFICADAS en esta ronda:
- ~~renderHtmlForVersion solo documents~~ → plantillas ya renderizan/descargan PDF por versión histórica (commit 20543021).
- ~~DocumentPdfService::generate persistente~~ → eliminado el almacenamiento físico; PDFs siempre bajo demanda (commit 4f817bba).
- ~~Broadcast realtime solo documents~~ → el único hueco real era template.rejected; cerrado (commit a416f45b). published/affects_my_document ya emitían en ambos; validation_requested no emite en ninguno (simétrico).

PRESERVADAS (con veredicto definitivo):
- `startNewRevisionCycle` (D-12): NO unificable — análisis post-fases 4/5 midió 33% de solape real: el guard es común pero la acción delega en servicios sin interfaz común (TemplatePublishingService::transitionStatus → templateRepository->update + TemplateStateChanged, vs DocumentStateService::transition → mergeHeadWorkingCopy + DocumentStateChanged) y los atributos reseteados difieren (created_by vs owner_id+created_by). Extraerlo sería ~2 líneas comunes envueltas en ~20 de plumbing.
- `DocumentMigrationBlockDiffer/PayloadResolver`: concepto doc-only sin espejo.
- `backfillStructuralFields`: deuda de DATOS, no de código — eliminar cuando no queden snapshots pre-fix (ver procedimiento en la entrada de cierre).
- `store()/clone()` con blocks inline solo en documents: lo exige el wizard de creación de documentos (necesita los bloques al instante para editar); el wizard de plantillas crea sus bloques en pasos posteriores.
- `destroy` 204 vs 200+Resource: el frontend usa la señal (archivada vs física).

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

## [BARRIDO FINAL] Validación de membresía de equipo vía TeamReadRepository::isMember

- **Fecha**: 2026-06-11
- **Severidad**: LOW
- **Qué cambió**: la validación inline `DB::table('team_members')->where(...)` de
  Store/UpdateTemplateRequest se delega a `TeamReadRepository::isMember`, cuyos
  helpers manejan el cast UUID de pgsql (`whereTeamIdMatches`/`whereUserIdMatches`).
  Misma semántica de pertenencia; más robusto ante el id-space del FDW.
- **Endpoint(s) afectado(s)**: POST/PUT de templates con team_id.
- **Impacto en cliente**: ninguno esperado (mismo resultado de validación).
- **Decidido por**: barrido final de capa.

## [CIERRE 2026-06-11] Estado de ejecución del plan

- **Fases ejecutadas**: 0–6 (la 6.3 y la igualación de 3.9/6.6 NO se tocaron — pendientes de decisión, ver tabla arriba).
- **Fase 7 (patrón PATCH UpdateTemplateDto)**: NO ejecutada — marcada opcional en el plan, riesgo ALTO, separable en rama propia.
- **6.2**: TemplateReviewService conserva retorno `Template` documentado como excepción B4 (el controller adjunta can_clone vía setAttribute antes del DTO readonly).
- **Verificación global**: Unit 453/453, Feature 285/285 (baseline 358 Unit/261 Feature — +119 tests nuevos, 0 regresiones); pint limpio en archivos del refactor; grep de arquitectura limpio (Eloquent/DB solo en Repositories y Models; única excepción documentada BlockRenderSupport).


## [FASE 6.3] PublishDocumentRequest autoriza (igualado a Template) — APROBADO

- **Fecha**: 2026-06-11
- **Severidad**: CRITICAL (cambio de comportamiento de seguridad)
- **Qué cambió**: `authorize()` pasaba de `return true` a
  `$this->user()->can('publish', $this->resolveDocument())` — doble guardia
  (Request + controller), patrón idéntico a PublishTemplateRequest.
- **Endpoint(s)**: POST /api/v1/documents/{id}/publish
- **Impacto en cliente**: usuarios sin permiso reciben ahora 403 ANTES de la
  validación de campos (antes: la validación de changelog corría primero y el
  403 llegaba del controller). Mismo resultado final para el caso no autorizado.
- **Decidido por**: usuario (aprobación explícita).

## [FASE 3.9] Validaciones de listado igualadas — APROBADO

- **Fecha**: 2026-06-11
- **Severidad**: HIGH
- **Qué cambió**:
  - ListDocumentsRequest: `process_id` añade `exists:processes,id`;
    `template_id` añade `exists:templates,id` (igualado a la política de
    Templates, que ya validaba process_id/team_id con exists).
  - ListTemplatesRequest: `study_type_id/study_id/module_id` pasan de
    `string|max:255` a `uuid` (igualado a Documents).
- **Endpoint(s)**: GET /api/v1/documents, GET /api/v1/templates
- **Impacto en cliente**: filtrar por un id inexistente o con formato no-UUID
  devuelve ahora 422 en vez de 200 con lista vacía. El frontend usa pickers de
  valores reales → sin impacto esperado.
- **Decidido por**: usuario.

## [FASE 4.1] Timeout WeasyPrint unificado a 60s — APROBADO

- **Fecha**: 2026-06-11
- **Severidad**: MEDIUM
- **Qué cambió**: ThemePdfService pasa de 30s a 60s (template y document ya
  usaban 60s). PDFs de muestra de temas complejos dejan de poder morir a los 30s.
- **Endpoint(s)**: GET /api/v1/themes/{id}/sample-pdf (y preview PDF de temas)
- **Impacto en cliente**: solo positivo (más margen); latencia máxima del
  endpoint sube de 30 a 60s en el caso patológico.
- **Decidido por**: usuario.

## [FASE 6.6] Wire format y defaults igualados (modo seguro) — APROBADO

- **Fecha**: 2026-06-11
- **Severidad**: MEDIUM
- **Qué cambió**:
  - `sortBy` default de Documents: `created_at` → `updated_at` (igualado a
    Templates; request y DocumentFilterDto). Solo afecta a llamadas API SIN
    `sort_by` explícito — el frontend siempre lo envía (defaultSort en ambos hooks).
  - DocumentStateController submit/publish/delegate devuelven `DocumentResource`
    directo (wrap estándar de Laravel) en vez de `response()->json(['data'=>...])`
    manual. JSON byte-idéntico verificado (sin withoutWrapping global, sin with()
    en el Resource). startNewVersion/applyTemplateMigration/destroyVersion siguen
    manuales porque añaden `blocks` al payload (asimetría por diseño).
  - `destroy`: NO igualado — el frontend distingue 204 (borrado físico) de
    200+Resource (archivada por documentos vinculados) en deleteTemplate();
    igualar destruiría esa señal. Documentado como asimetría funcional.
- **Endpoint(s)**: GET /api/v1/documents (orden por defecto); POST submit/publish/delegate de documents (mecanismo interno, sin cambio observable).
- **Decidido por**: usuario ("igualar del modo más seguro").


## [UNIF 2ª RONDA] PDFs de documentos sin persistencia — APROBADO

- **Fecha**: 2026-06-11 · **Severidad**: HIGH (API)
- **Qué cambió**: eliminado el flujo async de export (job GenerateDocumentPdf,
  cache de estado, POST /documents/{id}/export-pdf, GET /documents/{id}/export-status
  y variantes por versión). La descarga GET /documents/{id}/pdf genera bajo demanda.
- **Impacto en cliente**: el frontend ya descargaba síncrono (nunca llamaba a
  export-pdf/status) → sin impacto. Consumidores API externos de esos 2 endpoints
  (si existieran) reciben 404. Auditoría DocumentDownloaded intacta.
- **Limpieza futura**: PDFs huérfanos en storage/app/private/documents/.

## [UNIF 2ª RONDA] PDF por versión histórica de plantillas (feature espejo)

- **Fecha**: 2026-06-11 · **Severidad**: MEDIUM (solo añade)
- **Qué cambió**: GET /api/v1/templates/{id}/versions/{v}/pdf (gate viewHistory)
  + TemplateRenderService::renderHtmlForVersion + evento TemplateDownloaded +
  botón PDF por versión en el panel de historial de plantillas.

## [UNIF 2ª RONDA] Broadcast realtime en template.rejected

- **Fecha**: 2026-06-11 · **Severidad**: LOW
- **Qué cambió**: el creador de la plantilla recibe ahora notificación realtime
  al rechazarse la revisión (igual que document.rejected). Solo añade canal.

## [FIX] Portada: clipping de texto y métricas de fuente preview⇄PDF

- **Fecha**: 2026-06-11 · **Severidad**: HIGH (render visible)
- **Qué cambió**: (1) las cajas de TEXTO de portada ya no amputan glifos
  (overflow visible; las de imagen siguen recortando), también en el editor
  (CoverRegionPreview); (2) en preview_mode el render emite @font-face con
  data: URI de los MISMOS TTF que resuelve WeasyPrint (ThemeFontResolver) →
  el navegador deja de caer a Arial/Liberation y el salto de línea coincide.
- **Verificación**: layout WeasyPrint post-fix sin clipping; T3 = 8 páginas,
  las 3 iniciales (portada/blanco/índice) son bloques deliberados del seeder,
  sin páginas en blanco extra.
