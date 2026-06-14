# Auditoría lang/i18n — maya_dms (backend)

## Estado de infraestructura
- Directorio lang/: **NO EXISTE — crítico**. No hay `backend/lang/`, ni `backend/resources/lang/`, ni `{es,en,va}/`. El proyecto carece de toda infraestructura de traducción backend.
- Helper de traducción en uso: **no** — 0 archivos usan `__()`, `trans()`, `@lang` ni `Lang::get()` en todo `app/`.

## Resumen
- Archivos revisados: 415 (todos los `.php` bajo `app/`) + `routes/api.php`
- Archivos con strings sin traducir: 26 (25 bajo `app/` + `routes/api.php`)
- Total de hallazgos: 113 strings hardcodeados de cara al usuario (mensajes de validación, autorización, errores de negocio, notificaciones, correos)
- Paridad de locales (es/en/va): **N/A** — no existe ningún catálogo de idioma que comparar. Todos los textos están en español incrustados en código; `va` y `en` no existen.
- Severidad global: **high** (infraestructura ausente + alta densidad de strings, todos en un único idioma)

## Hallazgos por archivo

### routes/api.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 157 | "Los snapshots de plantilla son de solo inserción (append-only)." | abort(403) | snapshots.template_append_only |
| 230 | "Los snapshots de documento son de solo inserción (append-only)." | abort(403) | snapshots.document_append_only |

### app/Http/Controllers/Api/UserController.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 35 | "No tienes permiso para buscar usuarios." | abort(403) | users.search.forbidden |
| 69 | "No tienes permiso para buscar validadores de plantilla." | abort(403) | users.search.template_reviewers_forbidden |
| 105 | "No tienes permiso para buscar validadores de documento." | abort(403) | users.search.document_reviewers_forbidden |
| 134 | "No tienes permiso para buscar candidatos a propietario." | abort(403) | users.search.owner_candidates_forbidden |

### app/Http/Controllers/Api/CommentController.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 81 | "Los comentarios están cerrados para este recurso." | abort(422) | comments.closed |
| 151 | "El bloque indicado no es válido para este recurso." | abort(422) | comments.invalid_block |
| 229 | "Tipo de recurso de comentario no soportado." | abort(422) | comments.unsupported_resource |

### app/Http/Controllers/Api/TemplateBlockBulkController.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 73 | "No tienes acceso a uno o más bloques solicitados." | abort(403) | template_blocks.bulk_forbidden |

### app/Http/Controllers/Api/DocumentOptionsController.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 51 | "No hay plantillas publicadas disponibles para este módulo." | response()->json(['message'=>...]) | documents.options.no_templates |

### app/Http/Controllers/Api/TemplateReviewersController.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 39 | "Revisores de plantilla sincronizados correctamente." | response message | template_reviewers.synced |
| 54 | "Validadores de documento sincronizados correctamente." | response message | document_reviewers.synced |

### app/Http/Concerns/ValidatesOptionalProcessContext.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 20 | "El contexto de proceso no coincide con el recurso." | abort(403) | process.context_mismatch |

### app/Http/Requests/Concerns/ValidatesSubmissionChangelog.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 34-35 | "El changelog es obligatorio al enviar a validación." | messages() | validation.changelog.required_submit |

### app/Http/Requests/Documents/PublishDocumentRequest.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 47-48 | "El changelog es obligatorio al publicar un documento." | messages() | validation.changelog.required_publish_document |

### app/Http/Requests/Documents/RejectDocumentReviewRequest.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 59 | "Debes indicar un motivo para el rechazo o dejar un comentario en algún bloque del documento." | messages() | validation.rejection_reason.required |
| 60 | "El motivo del rechazo debe tener al menos :min caracteres." | messages() | validation.rejection_reason.min |
| 61 | "El motivo del rechazo no puede superar :max caracteres." | messages() | validation.rejection_reason.max |
| 71 | "motivo del rechazo" | attributes() | validation.attributes.rejection_reason |

### app/Http/Requests/Templates/PublishTemplateRequest.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 60-61 | "El changelog es obligatorio al publicar una plantilla." | messages() | validation.changelog.required_publish_template |

### app/Http/Requests/TemplateBlocks/BulkUpdateTemplateBlockRequest.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 29 | "Se requiere al menos un ID de bloque." | messages() | validation.block_ids.required |
| 30 | "Cada ID de bloque debe ser un UUID válido." | messages() | validation.block_ids.uuid |
| 31 | "Debes enviar block_state para actualizar." | messages() | validation.block_state.required |
| 32 | "El estado del bloque debe ser uno de: ... Valor recibido: :input." | messages() (con interpolación) | validation.block_state.in |

### app/Http/Requests/* (autorización — throw new AuthorizationException)
| Línea | String hardcodeado | Archivo | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 21 | "No puedes eliminar este documento." | DestroyDocumentRequest | auth.document.delete_forbidden |
| 22 | "No puedes actualizar este documento." | UpdateDocumentRequest | auth.document.update_forbidden |
| 22 | "No puedes abrir una nueva versión de este documento." | StartNewDocumentRevisionRequest | auth.document.new_revision_forbidden |
| 25 | "No puedes migrar la plantilla de este documento." | ApplyTemplateMigrationRequest | auth.document.migrate_forbidden |
| 22/24 | "Se requiere permiso para revisar este documento." | ApproveDocumentReviewRequest / RejectDocumentReviewRequest | auth.document.review_required |
| 23 | "Se requiere permiso para actualizar bloques de este documento." | UpdateDocumentBlockRequest | auth.document.block_update_required |
| 20/30 | "Se requiere permiso document.index para listar documentos." | IndexDocumentRequest / ListDocumentsRequest | auth.document.index_required |
| 44 | "Se requiere permiso document.create para crear documentos." | StoreDocumentRequest | auth.document.create_required |
| 26 | "Se requiere permiso para crear bloques en esta plantilla." | StoreTemplateBlockRequest | auth.template_block.create_required |
| 27 | "Se requiere permiso para actualizar bloques de esta plantilla." | UpdateTemplateBlockRequest | auth.template_block.update_required |
| 22 | "Se requiere permiso para reordenar bloques de esta plantilla." | ReorderTemplateBlocksRequest | auth.template_block.reorder_required |
| 36 | "Se requiere permiso para comentar en este recurso." | StoreCommentRequest | auth.comment.create_required |
| 24 | "Se requiere permiso para asignar revisores de plantilla." | SyncTemplateUsersRequest | auth.template.assign_reviewers_required |
| 24 | "No puedes asignar validadores de documento en esta plantilla." | SyncTemplateDocumentReviewersRequest | auth.template.assign_doc_reviewers_forbidden |
| 22 | "No puedes abrir una nueva versión de esta plantilla." | StartNewTemplateRevisionRequest | auth.template.new_revision_forbidden |
| 23 | "Se requiere permiso." | IndexTemplateRequest | auth.template.index_required |
| 33 | "Se requiere permiso para listar plantillas." | ListTemplatesRequest | auth.template.list_required |
| 20 | "Se requiere permiso process.index." | IndexProcessRequest | auth.process.index_required |
| 20 | "Se requiere permiso process.show." | ShowProcessRequest | auth.process.show_required |

### app/Models/DocumentVersion.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 53 | "Los snapshots de documento son inmutables." | throw AuthorizationException | snapshots.document_immutable |
| 57 | "No se pueden eliminar snapshots de documento." | throw AuthorizationException | snapshots.document_no_delete |

### app/Models/EntityVersion.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 58 | "Las versiones de snapshot inmutables no se pueden modificar." | throw AuthorizationException | snapshots.version_no_modify |
| 64 | "Las versiones de snapshot inmutables no se pueden eliminar." | throw AuthorizationException | snapshots.version_no_delete |

### app/Services/DocumentBlockService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 134/502 | "Solo se pueden editar bloques de documentos en borrador o rechazados." | throw AuthorizationException | document_block.edit_state_forbidden |
| 154 | "Este bloque está bloqueado y no admite edición." | throw AuthorizationException | document_block.locked |
| 516 | "Solo se pueden eliminar bloques opcionales." | throw AuthorizationException | document_block.delete_optional_only |
| 240 | "Debes completar todos los bloques editables antes de enviar a revisión." | ValidationException | validation.blocks.complete_editable |
| 284 | "Debes editar todos los bloques modificables antes de enviar a revisión." | ValidationException | validation.blocks.edit_modifiable |

### app/Services/DocumentService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 166 | "La versión publicada no existe o no pertenece a esta plantilla." | ValidationException | validation.template_version.invalid |
| 173 | "La plantilla no tiene versiones publicadas; no se puede crear un documento." | ValidationException | validation.template.no_published |
| 181 | "La versión de plantilla no contiene bloques." | ValidationException | validation.template.no_blocks |
| 425 | "Solo se pueden editar metadatos de documentos en borrador o rechazados." | ValidationException | validation.document.edit_state |
| 463 | "No se puede eliminar un documento publicado sin versión de trabajo activa." | ValidationException | validation.document.delete_published |
| 537 | "El módulo no tiene plantillas publicadas disponibles." | ValidationException | validation.module.no_templates |
| 552 | "La versión seleccionada no está disponible para el módulo." | ValidationException | validation.template_version.unavailable |
| 559 | "Debe seleccionar una plantilla cuando existen varias opciones." | ValidationException | validation.template.select_required |
| 565 | "El proceso no corresponde a la plantilla seleccionada para el módulo." | ValidationException | validation.process.mismatch |
| 572 | "El módulo no existe." | ValidationException | validation.module.not_found |
| 800 | "Solo un documento publicado puede pasar a borrador para una nueva versión." | ValidationException | validation.document.new_version_state |
| 822 | "El documento debe estar en borrador (nueva versión) para migrar de plantilla." | ValidationException | validation.document.migrate_state |
| 833 | "La versión de plantilla destino no existe o no es publicada." | ValidationException | validation.migrate.target_invalid |
| 840 | "La versión de plantilla destino debe ser más reciente que la actual." | ValidationException | validation.migrate.target_older |
| 848 | "La versión de plantilla destino no contiene bloques." | ValidationException | validation.migrate.target_no_blocks |
| 1026 | "El título del documento es obligatorio." | ValidationException | validation.document.title_required |
| 1032 | "La fecha de entrega del documento es obligatoria." | ValidationException | validation.document.deadline_required |
| 1145 | "Solo los documentos en borrador o rechazados pueden enviarse a revisión." | ValidationException | validation.document.submit_state |
| 1470 | "Solo se puede publicar un documento en borrador o en revisión." | ValidationException | validation.document.publish_state |
| 1478 | "El documento tiene validadores asignados. Debe completar la revisión para publicarse." | ValidationException | validation.document.reviews_pending |
| 1555 | "Solo el titular puede delegar la titularidad del documento." | throw AuthorizationException | auth.document.delegate_owner_only |
| 1560 | "El nuevo titular debe ser distinto del actual." | ValidationException | validation.document.new_owner_distinct |

### app/Services/DocumentReviewService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 63/232 | "Las revisiones solo aplican a documentos en revisión." | ValidationException | validation.review.only_in_review |
| 70/239 | "Revisión no encontrada." | throw ModelNotFoundException | review.not_found |
| 74/243 | "No eres el revisor asignado a esta etapa." | throw AuthorizationException | auth.review.not_assigned |
| 79/248 | "Esta revisión ya fue procesada." | ValidationException | validation.review.already_processed |
| 298 | "En revisión secuencial, solo puede actuar la etapa pendiente más baja." | ValidationException | validation.review.sequential_order |
| 164 | "Nueva solicitud de revisión" | notificación (title) | notifications.review_request.title |
| 165 | 'El documento "..." requiere tu revisión' | notificación (body, con interpolación) | notifications.review_request.body |

### app/Services/CommentService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 80 | "Tipo de recurso no permitido para comentarios." | ValidationException | validation.comment.resource_not_allowed |
| 86 | "El bloque debe incluir tipo e identificador juntos." | ValidationException | validation.comment.block_type_id |
| 232 | "El bloque debe ser de tipo plantilla." | ValidationException | validation.comment.block_template |
| 240 | "El bloque no pertenece a la plantilla indicada." | ValidationException | validation.comment.block_not_template |
| 250 | "El bloque debe ser de tipo documento." | ValidationException | validation.comment.block_document |
| 258 | "El bloque no pertenece al documento indicado." | ValidationException | validation.comment.block_not_document |
| 279 | "El comentario padre no existe." | ValidationException | validation.comment.parent_not_found |
| 285 | "El comentario padre no está disponible." | ValidationException | validation.comment.parent_unavailable |
| 295 | "El comentario padre debe pertenecer al mismo recurso y versión." | ValidationException | validation.comment.parent_same_resource |
| 304 | "El comentario padre debe pertenecer al mismo bloque." | ValidationException | validation.comment.parent_same_block |

### app/Services/TemplateContextResolver.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 55-275 (22 strings) | Mensajes de validación de contexto académico (equipo/estudio/módulo/tipo de estudio) | ValidationException | validation.template_context.* |

### app/Services/TemplateService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 462 | "No se puede eliminar una plantilla publicada sin versión de trabajo activa." | ValidationException | validation.template.delete_published |
| 526 | "Solo una plantilla publicada puede pasar a borrador para una nueva versión." | ValidationException | validation.template.new_version_state |
| 1064 | "El nombre de la plantilla es obligatorio." | ValidationException | validation.template.name_required |
| 1070 | "La fecha de entrega de la plantilla es obligatoria." | ValidationException | validation.template.deadline_required |
| 1079 | "La visibilidad de la plantilla es obligatoria." | ValidationException | validation.template.visibility_required |

### app/Services/TemplateReviewService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 56-347 (13 strings) | Mensajes de estado/revisión de plantilla (enviar/rechazar/aprobar, revisores, bloques) | ValidationException | validation.template_review.* |
| 378 | "Nueva solicitud de revisión de plantilla" | notificación (title) | notifications.template_review.title |
| 379 | 'La plantilla "..." requiere tu revisión' | notificación (body, con interpolación) | notifications.template_review.body |

### app/Services/TemplateBlockService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 84 | "Se requiere al menos un ID de bloque." | ValidationException | validation.block_ids.required |
| 92 | "Uno o más bloques no existen." | ValidationException | validation.block_ids.not_found |
| 106 | "Debes enviar al menos un bloque para reordenar." | ValidationException | validation.block_ids.reorder_required |
| 112 | "La lista de bloques no puede contener IDs duplicados." | ValidationException | validation.block_ids.duplicate |
| 123 | "Debes enviar todos los bloques de la plantilla." | ValidationException | validation.block_ids.all_required |
| 133 | "La lista enviada no coincide con los bloques reales de la plantilla." | ValidationException | validation.block_ids.mismatch |

### app/Services/TemplatePublishingService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 94 | "Solo se puede publicar una plantilla en borrador o en revisión." | ValidationException | validation.template_publish.state |
| 106 | "La plantilla debe tener al menos un bloque antes de publicarse." | ValidationException | validation.template_publish.min_blocks |
| 115 | "La plantilla debe tener al menos un bloque editable o modificable." | ValidationException | validation.template_publish.editable_block |
| 135 | "Los bloques bloqueados no pueden estar vacíos." | ValidationException | validation.template_publish.locked_not_empty |

### app/Services/TemplateReviewerAssignmentService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 88 | "La lista de validadores de documento contiene IDs de usuario duplicados." | ValidationException | validation.reviewers.duplicate_ids |
| 44/54/125/159 | (otros mensajes de validación de asignación de revisores) | ValidationException | validation.reviewers.* |

### app/Services/EntityVersionLifecycleService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 32 | "Solo se puede publicar una versión en borrador o en revisión." | ValidationException | validation.version.publish_state |
| 38 | "La versión ya tiene un snapshot inmutable publicado." | ValidationException | validation.version.already_snapshot |
| 70 | "El número de versión debe ser mayor o igual a 1." | ValidationException | validation.version.number_min |
| 122 | "El snapshot de publicación es obligatorio." | ValidationException | validation.version.snapshot_required |

### app/Services/EntityVersionReconstructionService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 56/61/64/76 | "Cadena de versiones inválida: ..." / "Versión inválida sin identificador." | throw RuntimeException | (borderline — ver nota) |

### app/Services/DocumentShareService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 30/64 | "Solo el titular puede gestionar colaboradores." | abort(403) | auth.share.owner_only |
| 35 | "No puedes compartir el documento contigo mismo." | ValidationException | validation.share.self |
| 41 | "El titular ya tiene acceso completo al documento." | ValidationException | validation.share.owner_has_access |

### app/Services/DocumentMigrationPayloadResolver.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 43 | "El documento origen no está anclado a una versión publicada de plantilla." | ValidationException | validation.migrate.source_not_anchored |
| 49 | "No existe una versión de plantilla más reciente que la del documento origen." | ValidationException | validation.migrate.no_newer_version |

### app/Services/CoverImageService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 34 | "El archivo no es una imagen válida (PNG, JPG o WebP)." | ValidationException | validation.cover.invalid_image |

### app/Services/ThemeImageService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 35 | "La URL no es válida." | ValidationException | validation.theme_image.url_invalid |
| 40 | "Solo se permiten URLs http/https." | ValidationException | validation.theme_image.url_scheme |
| 49 | "No se puede acceder a esta URL." | ValidationException | validation.theme_image.url_unreachable |
| 54/59 | "No se pueden descargar recursos de redes privadas." | ValidationException | validation.theme_image.private_network |
| 67 | "No se pudo descargar la imagen." | ValidationException | validation.theme_image.download_failed |
| 73 | "El archivo no es una imagen válida." | ValidationException | validation.theme_image.not_image |
| 79 | "La imagen es demasiado grande (máximo 10MB)." | ValidationException | validation.theme_image.too_large |

### app/Services/ThemeService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 39 | "Theme no encontrado." | throw NotFoundHttpException | theme.not_found |
| 101 | "Un theme de sistema no se puede eliminar." | throw ConflictHttpException | theme.system_no_delete |

### app/Services/ThemeRenderService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 33 | "Previsualización del tema" | render subject | theme.preview_subject |
| 84 | "Theme no encontrado." | throw NotFoundHttpException | theme.not_found |

### app/Services/TemplateRenderService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 46/135 | "Template no encontrado." | throw NotFoundHttpException | template.not_found |
| 141 | "Versión no encontrada para esta plantilla." | throw NotFoundHttpException | template.version_not_found |

### app/Services/MediaService.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 54/117 | "Token de media inválido." | throw \Exception (expuesto al cliente) | media.invalid_token |

### app/Support/VersionSubmissionChangelog.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 33 | "El changelog es obligatorio al enviar a validación." | ValidationException | validation.changelog.required_submit |
| 39 | "El changelog no puede superar ... caracteres." | ValidationException | validation.changelog.max |

### app/Support/AcademicScopeNormalizer.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 70-169 | ValidationException con mensajes inyectados (`$ctx->onModule*`, `$ctx->onStudy*`) | Los textos reales provienen de TemplateContextResolver | validation.template_context.* |

### app/Support/WorkingRevisionConflictResolver.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 54 | "Working revision in progress." | response message (en INGLÉS — inconsistencia de idioma) | working_revision.in_progress |

## Archivos revisados sin incidencias
(Resumen por capa — 415 archivos `.php` revisados, 389 sin strings de cara al usuario hardcodeados)
- **app/DTOs/** (55 archivos) — solo datos tipados, sin texto de usuario.
- **app/Http/Resources/** (todos) — formato de respuesta, sin literales de mensaje.
- **app/Models/** (37 de 39) — limpios salvo DocumentVersion.php y EntityVersion.php (listados arriba).
- **app/Repositories/** (46 archivos) — solo SQL/Eloquent, sin texto de usuario.
- **app/Events/** (19), **app/Listeners/** (1), **app/Observers/** (15), **app/Policies/** (8), **app/Enums/** (4), **app/Providers/** (1), **app/Constants/** (1) — sin strings de cara al usuario.
- **app/Console/Commands/** (4 archivos: EvaluateNotificationRulesCommand, PdfPocCommand, RepairMarkdownBlocks, SeedRuleData) — su salida es CLI de operador, no traducible (fuera de alcance i18n de usuario final).
- **app/Notifications/Rules/** (3 archivos: ScheduledNotificationRule, ValidationDeadlineApproachingRule, PendingValidationsThresholdRule) — son evaluadores/contratos; el texto de notificación se construye en los Services (DocumentReviewService, TemplateReviewService), ya listados.
- **app/Exceptions/** (1) — sin literales de mensaje de usuario.
- La mayoría de **app/Services/** y **app/Http/Controllers/** sin texto (lógica pura o delegación).

## Notas sobre exclusiones (no marcados como hallazgo)
- `Log::info/error/warning/debug` — diagnóstico interno, no se traduce.
- `EntityVersionReconstructionService.php` (RuntimeException "Cadena de versiones inválida...") — borderline: son invariantes técnicas de integridad de datos que no deberían llegar al usuario en operación normal. Incluido como referencia, prioridad baja.
- Cadenas técnicas no expuestas (`block_state`, claves de array, slugs de permiso) — ignoradas.

## Recomendaciones
1. **(CRÍTICO) Crear infraestructura lang/.** Generar `backend/lang/{es,en,va}/` con al menos `validation.php`, `auth.php` y un `messages.php` de dominio. Sin esto, no hay forma de traducir nada. El proyecto declara soporte trilingüe (es/en/va) en sus convenciones pero el backend no lo implementa en absoluto.
2. **(HIGH) Migrar mensajes de validación de Services a claves `__()`.** El grueso de los 113 hallazgos son `ValidationException::withMessages` en la capa de Services (DocumentService 22, TemplateContextResolver 22, CommentService 10, TemplateReviewService 15). Centralizar en `lang/{locale}/validation.php` bajo namespaces de dominio.
3. **(HIGH) Migrar mensajes de autorización.** Los ~25 `throw new AuthorizationException('...')` y `abort(403, '...')` en FormRequests, Controllers y Models deben pasar a `__('auth.*')`.
4. **(MEDIUM) Traducir notificaciones/correos.** DocumentReviewService:164-165 y TemplateReviewService:378-379 (títulos/cuerpos de notificación) y ThemeRenderService:33 (subject) deben resolverse contra el locale del destinatario, no en español fijo.
5. **(MEDIUM) Corregir inconsistencia de idioma.** `WorkingRevisionConflictResolver.php:54` devuelve "Working revision in progress." en inglés mientras todo lo demás está en español — evidencia de la ausencia de un catálogo único.
6. **(MEDIUM) Establecer `APP_LOCALE`/`APP_FALLBACK_LOCALE` y resolución de locale por petición** (header `Accept-Language` o preferencia de usuario del perfil Maya) en un middleware, para que `__()` resuelva al idioma correcto.
7. **(LOW) Definir paridad y test de cobertura de claves.** Una vez creado lang/, añadir un test que verifique que `es`, `en` y `va` tienen el mismo conjunto de claves (paridad), hoy imposible porque no existe ninguno.
