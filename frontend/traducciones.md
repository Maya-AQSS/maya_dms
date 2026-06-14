# Auditoría i18n — maya_dms (frontend)

## Resumen
- Archivos revisados: 208
- Archivos con strings sin traducir: 33
- Total de hallazgos: 168
- Paridad de locales (es/en/va): INCOMPLETA — al locale `va` le faltan 7 claves (6 en `common:versionChangelog.*` + 1 `documents:sendForReviewTitle`); `es`/`en` con paridad total
- Severidad global: high

## Hallazgos por archivo

### src/features/processes/components/ProcessFormModal.tsx
(no usa useTranslation — feature de procesos sin internacionalizar)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 124 | "Cerrar" (aria-label) | common:actions.close |
| 149 | "Ej. PE01" (placeholder) | nuevo: processes:form.codePlaceholder |
| 162 | "Nombre completo del proceso" (placeholder) | nuevo: processes:form.namePlaceholder |
| 175 | "Etiqueta corta" (placeholder) | nuevo: processes:form.aliasPlaceholder |
| 182 | "Descripción" (label) | common:fields.description |
| 186 | "Descripción opcional" (placeholder) | nuevo: processes:form.descriptionPlaceholder |
| 192 | "Proceso padre" (label) | nuevo: processes:form.parent |
| 194 | "Sin padre (proceso raíz)" (option) | nuevo: processes:form.noParent |
| 204 | "Color" (label) | nuevo: processes:form.color |
| 240 | "Icono" (label) | nuevo: processes:form.icon |

### src/features/processes/pages/ProcessShowPage.tsx
(usa useTranslation parcialmente; quedan literales en el formulario)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 237 | "Proceso" (title PageTitle) | nuevo: processes:detail.title |
| 327 | "Nombre del proceso" (placeholder visual) | nuevo: processes:form.namePlaceholder |
| 348 | "Ej. PE01" (placeholder) | nuevo: processes:form.codePlaceholder |
| 363 | "Nombre completo" (placeholder) | nuevo: processes:form.namePlaceholder |
| 376 | "Etiqueta corta" (placeholder) | nuevo: processes:form.aliasPlaceholder |
| 386 | "Proceso padre" (label) | nuevo: processes:form.parent |
| 389 | "Sin padre (proceso raíz)" (option) | nuevo: processes:form.noParent |
| 406 | "Proceso raíz — sin padre" (span) | nuevo: processes:form.rootNoParent |
| 414 | "Color" (label) | nuevo: processes:form.color |
| 453 | "Sin color" (span) | nuevo: processes:form.noColor |
| 462 | "Icono" (label) | nuevo: processes:form.icon |
| 520 | "Sin icono" (span) | nuevo: processes:form.noIcon |
| 528 | "Descripción" (label) | common:fields.description |
| 533 | "Descripción opcional" (placeholder) | nuevo: processes:form.descriptionPlaceholder |

### src/features/processes/pages/ProcessesManagePage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 17 | "Gestión de Procesos" (title) | nuevo: processes:manage.title |
| 18 | "Catálogo de procesos del sistema" (subtitle) | nuevo: processes:manage.subtitle |

### src/features/processes/components/ProcessesTable.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 175 | "Código, nombre o alias…" (placeholder) | nuevo: processes:table.searchPlaceholder |
| 188 | "Todos" (option) | common:filters.all |
| 189 | "Solo raíz (sin padre)" (option) | nuevo: processes:table.onlyRoot |

### src/features/templates/cover/CoverDesignEditor.tsx
(no usa useTranslation — editor de portada sin internacionalizar)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 130 | "Inspector" (h3) | nuevo: templates:cover.inspector |
| 131 | "Selecciona un elemento del lienzo para editar sus propiedades, o añade uno nuevo." (p) | nuevo: templates:cover.inspectorEmpty |
| 170 | "Posición y tamaño (mm)" (h4) | nuevo: templates:cover.positionSize |
| 180 | "Propiedades" (h4) | nuevo: templates:cover.properties |
| 210 | "Corto (01/01/2026)" (option) | nuevo: templates:cover.dateShort |
| 211 | "Largo (1 de enero de 2026)" (option) | nuevo: templates:cover.dateLong |
| 221 | "Página N" (option) | nuevo: templates:cover.pageN |
| 222 | "Página N de M" (option) | nuevo: templates:cover.pageNofM |
| 269 | "Preview" (alt img) | common:preview |
| 285 | "Subiendo…" (p) | common:uploading |
| 292 | "Contener" (option) | nuevo: templates:cover.fitContain |
| 293 | "Cubrir (recorte)" (option) | nuevo: templates:cover.fitCover |
| 294 | "Estirar" (option) | nuevo: templates:cover.fitFill |
| 322 | "Izquierda" (option) | common:align.left |
| 323 | "Centro" (option) | common:align.center |
| 324 | "Derecha" (option) | common:align.right |
| 329 | "Normal" (option) | nuevo: templates:cover.weightNormal |
| 330 | "Negrita" (option) | nuevo: templates:cover.weightBold |

### src/features/templates/cover/CoverFillEditor.tsx
(no usa useTranslation)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 107 | "Campos de la portada" (h3) | nuevo: templates:cover.fillFields |
| 115 | "Esta portada no tiene campos rellenables." (p) | nuevo: templates:cover.fillEmpty |
| 137 | "El documento no es editable en su estado actual." (p) | nuevo: templates:cover.notEditable |

### src/features/templates/cover/CoverRegionPreview.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 93 | "Imagen" (span placeholder) | nuevo: templates:cover.imagePlaceholder |

### src/features/themes/components/ThemeBlockInspector.tsx
(usa useTranslation parcialmente; quedan literales en labels/opciones)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 38-41 | "X (mm)" / "Y (mm)" / "Ancho (mm)" / "Alto (mm)" (NumField label) | nuevo: themes:inspector.x/y/width/height |
| 50 | "Propiedades" (h4) | nuevo: themes:inspector.properties |
| 61 | "Inspector" (h3) | nuevo: themes:inspector.title |
| 88 | "Contenido" (FieldLabel) | nuevo: themes:inspector.content |
| 95 | "Tamaño (pt)" (NumField label) | nuevo: themes:inspector.sizePt |
| 97 | "Color" (FieldLabel) | nuevo: themes:inspector.color |
| 113 | "ID de theme no disponible" (p) | nuevo: themes:inspector.noThemeId |
| 119 | "Formato" (FieldLabel) | nuevo: themes:inspector.format |
| 132 | "Formato" (FieldLabel) | nuevo: themes:inspector.format |
| 134-135 | "Corto (01/01/2026)" / "Largo (1 de enero de 2026)" (option) | nuevo: themes:inspector.dateShort/dateLong |
| 145 | "Etiqueta visible en el editor" (FieldLabel) | nuevo: themes:inspector.editorLabel |
| 154 | "Sin propiedades editables." (p) | nuevo: themes:inspector.noEditableProps |
| 192-194 | "Izquierda" / "Centro" / "Derecha" (option) | common:align.left/center/right |
| 247 | "Vista previa" (FieldLabel) | common:preview |
| 249 | "Preview" (alt img) | common:preview |
| 255 | "Subir imagen" (FieldLabel) | nuevo: themes:inspector.uploadImage |
| 271 | "O usar URL" (FieldLabel) | nuevo: themes:inspector.orUseUrl |
| 299 | "Texto alternativo" (FieldLabel) | nuevo: themes:inspector.altText |
| 304 | "Opacidad (0–1)" (NumField label) | nuevo: themes:inspector.opacity |
| 313 | "Rotación (grados)" (NumField label) | nuevo: themes:inspector.rotation |
| 321 | "Ajuste de imagen" (FieldLabel) | nuevo: themes:inspector.imageFit |
| 323-325 | "Contener (espacio vacío)" / "Cubrir (recorte)" / "Estirar" (option) | nuevo: themes:inspector.fitContain/fitCover/fitStretch |

### src/features/themes/components/ThemeVerificationStep.tsx
(no usa useTranslation)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 60 | "Verificación" (h3) | nuevo: themes:verification.title |
| 69 | "Nombre" (dt) | common:fields.name |
| 73 | "Estado" (dt) | common:fields.status |
| 77 | "Bloques" (dt) | nuevo: themes:fields.blocks |

### src/features/themes/components/ThemeGridEditor.tsx
(usa useTranslation parcialmente)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 156 | "Editor de layout" (strong) | nuevo: themes:layout.editorTitle |
| 259 | "Guardando…" (span) | common:saving |
| 261 | "Cambios pendientes" (span) | common:status.pendingChanges |
| 263 | "Guardado" (span) | common:status.saved |

### src/features/themes/pages/ThemeShowPage.tsx
(usa useTranslation parcialmente)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 42 / 53 | "Tema" (title PageTitle) | themes:title |
| 133 | "Tema · Identidad visual reutilizable" (subtitle) | nuevo: themes:detail.subtitle |
| 162 | "Nombre" (span label) | common:fields.name |
| 166 | "Descripción" (span label) | common:fields.description |
| 169 | "Sin descripción" (span) | common:fields.noDescription |
| 174 | "Estado" (span label) | common:fields.status |
| 182 | "Bloques" (span label) | nuevo: themes:fields.blocks |
| 186 | "Paleta" (span label) | nuevo: themes:fields.palette |

### src/features/themes/pages/ThemesListPage.tsx
(usa useTranslation parcialmente)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 146 | "Nombre" (FilterField label) | common:fields.name |
| 149 | "Buscar por nombre…" (placeholder) | common:filters.searchByName |
| 156 | "Estado" (FilterField label) | common:fields.status |

### src/features/themes/components/ThemeWizardStepIdentity.tsx
(usa useTranslation parcialmente)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 222 | "Sans-serif" (optgroup label) | nuevo: themes:fonts.sansSerif |
| 229 | "Serif" (optgroup label) | nuevo: themes:fonts.serif |
| 236 | "Monoespacio" (optgroup label) | nuevo: themes:fonts.monospace |
| 244 | "Personalizada (legacy)" (optgroup label) | nuevo: themes:fonts.customLegacy |

### src/features/themes/pages/ThemeEditPage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 13 | "Cargando theme…" (p) | common:loading |

### src/features/documents/components/DocumentWizard.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 1197 | "No se puede crear un documento sin plantilla." (throw Error) | nuevo: documents:errors.noTemplate |
| 1199 / 1270 | "La plantilla seleccionada no tiene proceso asociado." (throw Error) | nuevo: documents:errors.templateNoProcess |
| 1314 / 2852 | "El documento aún se está cargando. Espera un momento…" (setFormError) | nuevo: documents:errors.stillLoading |
| 1409 | "Después no se podrá seguir editando como borrador." (p) | nuevo: documents:wizard.submitWarning |
| 1694 | "Nombre" (FieldLabel) | common:fields.name |
| 1770 | "Equipo (opcional, exclusivo con contexto académico)" (FieldLabel) | nuevo: documents:wizard.teamOptional |
| 1794 | "Tipo de Estudio" (FieldLabel) | nuevo: documents:fields.studyType |
| 1809 | "No tienes tipos de estudio asignados, contacta…" (option) | nuevo: documents:wizard.noStudyTypes |
| 1825 | "Estudio" (FieldLabel) | nuevo: documents:fields.study |
| 1879 | "Actual:" (span) | nuevo: documents:wizard.current |
| 1897 | "Buscar nuevo propietario…" (placeholder) | nuevo: documents:wizard.searchOwner |
| 1903 | "Escribe al menos 2 caracteres para buscar." (p) | common:search.minChars |
| 1905 | "Buscando…" (p) | common:searching |
| 1953/2284/2654 | "Guardando…" (span) | common:saving |
| 1955/2286/2656 | "Error al guardar" (span) | common:errors.saveFailed |
| 1965 | "Vista:" (span) | nuevo: documents:wizard.view |
| 2170-2171 | "Cerrar descripción" (aria-label/title) | nuevo: documents:wizard.closeDescription |
| 2235 | "No hay bloques." (p) | common:noBlocks |
| 2322 | "Comentarios de revisión" (title) | nuevo: templates:reviewComments |
| 2324 | "Comentarios" (span) | common:comments |
| 2430 | "Guardando cambios..." (span) | common:savingChanges |
| 2464 | "Sin contenido en este bloque." (p) | common:noBlockContent |
| 2534 | "Propiedades" (p) | nuevo: common:properties |
| 2613 | "Este documento no tiene bloques." (p) | documents:noBlocks |
| 2695 | "Este bloque no tiene contenido." (span) | common:noBlockContent |
| 2721 | "Debes rellenar todos los bloques editables antes de continuar…" (p) | nuevo: documents:wizard.fillBlocksFirst |
| 2753 | "¿Eliminar este bloque?" (title) | common:confirm.deleteBlock |
| 2813 | "Confirmar guardado" (title) | nuevo: documents:wizard.confirmSave |
| 2845 | "Sin validadores configurados" (title) | nuevo: documents:wizard.noValidators |

### src/features/documents/components/ContinuousDocumentEditor.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 76 | "Guardando…" (span) | common:saving |
| 83 | "Error al guardar" (span) | common:errors.saveFailed |
| 249 | "Ver descripción / instrucciones del bloque" (title) | nuevo: documents:editor.viewBlockDescription |
| 254 | "Descripción" (span) | common:fields.description |

### src/pages/DocumentPreviewPage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 161 | "Identificador de documento no válido." (setError) | nuevo: documents:errors.invalidId |
| 1045 / 1333 | "Este bloque no tiene descripción." (p) | common:noBlockDescription |
| 1095 / 1342 | "Cargando documento…" (p) | common:loadingDocument |
| 1109 | "Este documento no tiene bloques." (p) | documents:noBlocks |
| 1161 / 1422 | "Ver cambios" (span) | common:viewChanges |
| 1179 / 1438 | "Info" (span) | common:info |
| 1197 / 1453 | "Mensajes" (span) | common:messages |
| 1210 / 1465 | "Sin contenido." (p) | common:noContent |
| 1224 | "Se registrará tu aprobación. Si eres el último validador…" (description) | nuevo: documents:approve.description |
| 1225 | "Aprobar" (confirmLabel) | common:actions.approve |
| 1476 | "Este documento no tiene bloques." (emptyMessage) | documents:noBlocks |
| 1499 | "¿Eliminar este documento?" (title) | nuevo: documents:confirm.deleteTitle |
| 1500 | "Estás a punto de eliminar este elemento. Esta acción es irreversible…" (description) | nuevo: common:confirm.deleteIrreversible |
| 1501 | "Eliminar" (confirmLabel) | common:actions.delete |
| 1502 | "Cancelar" (cancelLabel) | common:actions.cancel |
| 1512 | "Se creará un nuevo borrador editable…" (description) | nuevo: documents:newVersion.description |
| 1513 | "Crear nueva versión" (confirmLabel) | nuevo: documents:newVersion.confirm |
| 1527 | "Entendido" (confirmLabel) | common:actions.understood |
| 1548 | "Descartar versión" (confirmLabel) | nuevo: documents:discardVersion.confirm |

### src/pages/TemplatePreviewPage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 163 | "Identificador de plantilla no válido." (setError) | nuevo: templates:errors.invalidId |
| 179 | "La versión seleccionada no pertenece a esta plantilla." (setError) | nuevo: templates:errors.versionMismatch |
| 547 | "Genera y descarga el PDF de la plantilla" (title) | nuevo: templates:preview.pdfTooltip |
| 574 | "Crea una plantilla nueva e independiente…" (title) | nuevo: templates:preview.newTemplateTooltip |
| 681 | "Este bloque no tiene descripción." (p) | common:noBlockDescription |
| 693 | "Cargando plantilla…" (p) | common:loadingTemplate |
| 753 | "Info" (span) | common:info |
| 770 | "Mensajes" (span) | common:messages |
| 813 | "Se creará un nuevo borrador editable a partir de la plantilla…" (description) | nuevo: templates:newVersion.description |
| 814 | "Crear nueva versión" (confirmLabel) | nuevo: templates:newVersion.confirm |
| 828 | "Entendido" (confirmLabel) | common:actions.understood |
| 836 | "¿Eliminar esta plantilla?" (title) | nuevo: templates:confirm.deleteTitle |
| 837 | "Estás a punto de eliminar este elemento…" (description) | common:confirm.deleteIrreversible |
| 838 | "Eliminar" (confirmLabel) | common:actions.delete |
| 839 / 851 | "Cancelar" (cancelLabel) | common:actions.cancel |
| 849 | "Se descartarán los cambios en borrador/en revisión…" (description) | nuevo: templates:discardVersion.description |
| 850 | "Descartar versión" (confirmLabel) | nuevo: templates:discardVersion.confirm |

### src/pages/TemplateReviewPage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 32 | "No tienes permisos de validación sobre esta plantilla." (setError) | nuevo: templates:errors.noValidationPermission |
| 36 | "No tienes permiso para revisar plantillas." (setError) | nuevo: templates:errors.noReviewPermission |

### src/pages/DocumentValidationPage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 21 | "Identificador de documento no válido." (p) | nuevo: documents:errors.invalidId |
| 34 | "Cargando permisos…" (p) | common:loadingPermissions |

### src/pages/DocumentEditorPage.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 24 | "Identificador de documento o plantilla no válido." (p) | nuevo: documents:errors.invalidIdOrTemplate |

### src/pages/NuevaProgramacionSelectorPage.tsx
(usa useTranslation parcialmente)
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 205 | "No hay plantillas utilizables para crear documentos con los filtros actuales." (emptyMessage) | nuevo: documents:selector.empty |
| 225 | "Nombre" (FilterField label) | common:fields.name |
| 235 | "Visibilidad" (FilterField label) | templates:fields.visibility |
| 244 | "Autor" (FilterField label) | nuevo: common:fields.author |
| 254 | "Publicadas desde" (FilterField label) | nuevo: documents:selector.publishedSince |

### src/features/templates/components/WizardStep2Blocks.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 747 | "Guardando…" (span) | common:saving |
| 749 | "Error al guardar" (span) | common:errors.saveFailed |
| 842 | "Selecciona un bloque para editar" (p) | nuevo: templates:wizard.selectBlock |
| 885 | "Comentarios de revisión" (title) | nuevo: templates:reviewComments |
| 887 | "Comentarios" (span) | common:comments |
| 923-924 | "Duplicar" (title/span) | common:actions.duplicate |
| 986 | "Nombre del bloque" (FieldLabel) | nuevo: templates:wizard.blockName |
| 1001 | "Estado" (FieldLabel) | common:fields.status |
| 1019 | "Tipo de bloque" (FieldLabel) | nuevo: templates:wizard.blockType |
| 1071 | "Tema del bloque" (FieldLabel) | nuevo: templates:wizard.blockTheme |
| 1080 | "Tema por defecto de la plantilla" (option) | nuevo: templates:wizard.defaultTheme |
| 1183 | "Guardando cambios..." (span) | common:savingChanges |
| 1273 | "¿Eliminar bloque?" (title) | common:confirm.deleteBlock |

### src/features/templates/components/WizardStep3Users.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 190 | "Sin validadores asignados." (p) | nuevo: templates:validators.empty |
| 220 | "Estás a punto de eliminar este validador de la plantilla." (p) | nuevo: templates:validators.removeConfirm |
| 289 | "Cargando usuarios…" (p) | common:loadingUsers |
| 296 | "Escribe al menos 2 caracteres para buscar." (p) | common:search.minChars |
| 302 | "No se encontraron usuarios con permiso de revisión." (p) | nuevo: templates:validators.noUsersFound |

### src/features/templates/components/WizardStep4Summary.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 130 / 161 | "Sin validadores asignados." (p) | nuevo: templates:validators.empty |
| 204 | "Aún no se han añadido bloques." (p) | nuevo: templates:wizard.noBlocksYet |

### src/features/templates/components/WizardStep1Properties.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 355 | "Propietario" (FieldLabel) | nuevo: common:fields.owner |
| 381 | "Buscar usuario por nombre…" (placeholder) | nuevo: common:search.userByName |
| 391 | "Escribe al menos 2 caracteres para buscar." (p) | common:search.minChars |
| 393 | "Buscando…" (p) | common:searching |

### src/features/templates/components/TemplateReviewView.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 518 | "Esta plantilla no tiene bloques configurados." (p) | nuevo: templates:review.noBlocks |
| 563 | "Info" (span) | common:info |
| 586 | "Mensajes" (span) | common:messages |
| 612 | "Ver cambios" (span) | common:viewChanges |
| 622 | "Bloque sin contenido." (p) | common:noBlockContent |
| 642 | "Comentarios obligatorios" (title) | nuevo: templates:review.requiredComments |

### src/features/templates/components/TemplatePreviewModal.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 105 | "Este bloque no tiene descripción." (p) | common:noBlockDescription |
| 158 | "Info" (span) | common:info |

### src/features/templates/components/TemplateHierarchyFields.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 83 | "Tipo de Estudio" (FieldLabel) | templates:fields.studyType |
| 90 / 107 / 124 / 141 | "Todos" (option) | common:filters.all |
| 100 | "Estudio" (FieldLabel) | templates:fields.study |
| 134 | "Equipo" (FieldLabel) | templates:fields.team |

### src/features/templates/components/BlockCommentsCard.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 105 | "Comentario eliminado" (p) | nuevo: common:comments.deleted |
| 314 | "Editar" (aria-label) | common:actions.edit |
| 329 | "Eliminar" (aria-label) | common:actions.delete |
| 346 | "¿Eliminar este comentario?" (span) | nuevo: common:comments.deleteConfirm |

### src/features/templates/components/IndexBlockEditor.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 94 | "Índice automático" (h3) | nuevo: templates:index.autoTitle |

### src/features/templates/components/DocxBlockSplitter/ChunkListColumn.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 42 | "Un bloque por cada H1" (title) | nuevo: templates:docx.splitH1 |
| 45 | "Un bloque por cada H1/H2" (title) | nuevo: templates:docx.splitH1H2 |
| 64 | "Asignar a…" (option) | nuevo: templates:docx.assignTo |

### src/features/templates/components/DocxBlockSplitter/TargetBlockPanel.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 55 | "Eliminar bloque" (aria-label) | common:actions.deleteBlock |
| 61 | "Sin elementos — asígnale alguno." (p) | nuevo: templates:docx.noElements |

### src/features/templates/components/DocxBlockSplitter/Header.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 19 | "Cerrar" (aria-label) | common:actions.close |

### src/features/templates/components/DocxBlockSplitter.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 85 | "El documento no contiene contenido importable." (setError) | nuevo: templates:docx.noImportable |
| 95 | "No se pudo leer el archivo. ¿Es un .docx válido?" (setError) | nuevo: templates:docx.readError |
| 223 | "Falló la creación de bloques. Revisa los que se hayan creado y reintenta." (setError) | nuevo: templates:docx.createError |

### src/features/templates/components/DocxBlockSplitter/constants.ts
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 16 | "Encabezado" (label) | nuevo: templates:docx.kind.heading |
| 18 | "Lista" (label) | nuevo: templates:docx.kind.list |
| 19 | "Tabla" (label) | nuevo: templates:docx.kind.table |
| 20 | "Figura" (label) | nuevo: templates:docx.kind.figure |
| 21 | "Cita" (label) | nuevo: templates:docx.kind.blockquote |
| 23 | "Separador" (label) | nuevo: templates:docx.kind.horizontalRule |
| 24 | "Elemento" (label) | nuevo: templates:docx.kind.other |

### src/features/templates/constants.ts
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 4-9 | "Personal"/"Global"/"Tipo de Estudio"/"Estudio"/"Módulo"/"Equipo" (visibility labels) | templates:visibility.* |
| 13-18 | "Todos"/"Borrador"/"En revisión"/"Rechazada"/"Publicada"/"Archivada" (status labels) | templates:status.* |
| 23-24 | "Todos"/"Solo favoritos" (favorites filter labels) | nuevo: templates:favoritesFilter.* |
| Nota | Son arrays de constantes estáticas; requieren convertir a función que reciba `t` o resolver en el punto de render | — |

### src/features/themes/themeBlocks.ts
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 27 | "Contenido del documento" (label) | nuevo: themes:blocks.documentContent |
| 29 | "Aquí se carga el cuerpo del documento" (defaultProps.label) | nuevo: themes:blocks.bodyPlaceholder |
| 33 | "Texto" (label) | nuevo: themes:blocks.text |
| 39 | "Imagen" (label) | nuevo: themes:blocks.image |
| 45 | "Nº de página" (label) | nuevo: themes:blocks.pageNumber |
| 51 | "Fecha" (label) | nuevo: themes:blocks.date |

### src/components/CascadeFilters.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 136 | "Todos los tipos" (option) | nuevo: common:filters.allTypes |
| 160 | "Todos los estudios" (option) | nuevo: common:filters.allStudies |

### src/components/DocumentsContent.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 449 | "Documentos" (title) | documents:title |
| 559 | "Cargando vista previa…" (p) | common:loadingPreview |

### src/components/FavoriteInlineMark.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 7 | "En favoritos" (title) | nuevo: common:favorites.marked |
| 8 | "En favoritos" (aria-label) | nuevo: common:favorites.marked |

### src/components/canvas/AbsoluteCanvas.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 131 | "Redimensionar" (title) | nuevo: common:actions.resize |

### src/features/blocks-ui/BlockListItem.tsx
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 101 | "Mensajes sin leer" (title) | nuevo: common:unreadMessages |
| 107 | "Obligatorio — este bloque debe rellenarse" (title) | nuevo: common:requiredBlock |

### src/features/templates/hooks/useTemplateBlocks.ts
| Línea | String hardcodeado | Clave i18n sugerida |
|------|--------------------|---------------------|
| 54 | "No tienes permiso para listar bloques (block.index)." (setError) | nuevo: templates:errors.noListBlocksPermission |
| 76 | "No tienes permiso para crear bloques (block.create)." (throw Error) | nuevo: templates:errors.noCreateBlockPermission |
| 88 | "No tienes permiso para actualizar bloques (block.update)." (throw Error) | nuevo: templates:errors.noUpdateBlockPermission |
| 99 | "No tienes permiso para eliminar bloques (block.delete)." (throw Error) | nuevo: templates:errors.noDeleteBlockPermission |
| 143 | "No tienes permiso para reordenar bloques (block.update)." (setError) | nuevo: templates:errors.noReorderBlockPermission |

## Archivos revisados sin incidencias

Internacionalizados correctamente con t()/useTranslation o sin texto de usuario (lógica/tipos/utilidades/clientes API):

- Todos los `src/api/*.ts` (academicHierarchy, blobDownload, blocks, comments, dashboard, documents, favorites, http, media, newVersion, paginatedList, processes, templates, themes, users) — clientes API, sin texto UI
- Todos los `src/types/*.ts` (blocks, documents, hierarchy, processes, templates, themes, users) — tipos puros
- `src/App.tsx`, `src/main.tsx`, `src/permissions.ts`, `src/auth/oidcAdapter.ts`
- `src/lib/*` (dataTablePageSize, noMatchId)
- `src/utils/*` (academicContextSearch, blockComments, formatCalendarDate, normalizeForSearch, templateBlockDescription, tiptapHeadings, versionableEntityActions) — utilidades sin texto UI
- `src/utils/workingRevisionMessages.ts` — correctamente internacionalizado (recibe `t` como parámetro)
- `src/components/layout/*` (navItems usa t() con defaultValue; index, processIcons, ProcessesDrawer)
- `src/components/ChangelogHtmlContent.tsx`, `versionChangelogHtml.ts`, `VersionChangelogModal.tsx`, `VersionComparePanel.tsx`, `VersionHistoryPanel.tsx`, `FavoriteButton.tsx`, `ErrorBoundaryWrapper.tsx`, `wizard/WizardShell.tsx`
- `src/components/canvas/canvasModel.ts`, `CanvasRuler.tsx`, `useCanvasInteraction.ts`
- `src/features/dashboard/**` (hooks, pages/DashboardPage, widgets, registry) — usan t()
- `src/features/documents/components/*` no listados arriba (BlockChangesPanel, DiffLines, DocumentBlockHistoryPanel, DocumentDiffModal, DocumentDiffPanel, DocumentMigrationStep, DocumentsTable, DocumentWizardSubviews, IndexFillEditor, MigrationBlockItem, PagedThemedPreview, PaperBlocksArticle, PaperPreviewLayout, SequentialValidatorBadge, StructuralBlockPreview) — usan t() o sin texto UI
- `src/features/documents/hooks/*`, `lib/*`, `schemas/*`, `types.ts`, `index.ts` — lógica pura
- `src/features/comments/commentCache.ts`, `src/features/notifications/hooks/index.ts`
- `src/features/hierarchy/**` — contexto/hooks/selectores (mensajes `throw new Error` son errores de programación de desarrollador, no de cara al usuario)
- `src/features/processes/components/ColorBadge.tsx`, `ProcessesTable.tsx` (parcial — ver hallazgos), `hooks/useServerProcessesTable.ts`, `utils/formatError.ts`, `pages/ProcessShowPage.tsx` (parcial)
- `src/features/templates/**` no listados arriba (blockSources, blockUiState, clientTemplatePagination, AddBlockMenu, BlockContentHtml, BlockEditorTabs, BlockNoteEditorPanel, MayaEditorPanel, TemplateCard, TemplateEditor, TemplatesContent, TemplatesTable, TemplatesTableBoundary, TemplateWizard, hooks/*, lib/*, schemas/*, templateFormUtils, templateListNavigation) — usan t() o sin texto UI
- `src/features/themes/**` no listados arriba (ThemeA4Preview, ThemeBlockPreview, ThemeMiniPreview, ThemeWizard, hooks/*, pages/ThemeNewPage, ThemeLayoutPage, pageSizes, themeBlocks parcial)
- `src/features/user-profile/**`, `src/features/versioning/hooks/useNewVersionFlow.ts`
- `src/features/blocks-ui/BlockListItem.tsx` (parcial — ver hallazgos)
- `src/features/templates/cover/coverModel.ts`
- `src/features/templates/components/DocxBlockSplitter/*` no listados (ChunkListItem, FileUploadSection, Footer, groupingUtils, ReadyContentPanel, selectionUtils, types)
- `src/hooks/*` (useFavoritesIds, useMediaQuery, useProcesses, useServerNuevaProgramacionTable)
- `src/pages/*` no listados arriba (DashboardPage, PlaceholderPage, ProcesosPage, TemplateEditPage, TemplateNewPage, TemplatesPage, index.ts)
- `src/test/**` — infraestructura de test (shims/fixtures), fuera de alcance funcional

## Gaps de paridad de locales

Claves presentes en `es` y `en` pero AUSENTES en `va`:

namespace **common** (faltan 6):
- `versionChangelog.hint`
- `versionChangelog.label`
- `versionChangelog.maxLength`
- `versionChangelog.placeholder`
- `versionChangelog.readOnlyTitle`
- `versionChangelog.required`

namespace **documents** (falta 1):
- `sendForReviewTitle`

Namespaces con paridad total es/en/va: `auth` (2), `nav` (3), `templates` (107), `themes` (52). El resto de `common` (68/74) y `documents` (157/158) están alineados salvo las claves listadas.

## Recomendaciones

1. PRIORIDAD ALTA — Internacionalizar el feature de **Procesos** completo (`ProcessFormModal`, `ProcessShowPage`, `ProcessesManagePage`, `ProcessesTable`): no existe namespace `processes` y la mayoría de literales no tienen `useTranslation`. Crear `processes.json` en es/en/va y añadirlo a `resources.ts`.
2. PRIORIDAD ALTA — Internacionalizar el feature de **Portada (cover)** (`CoverDesignEditor`, `CoverFillEditor`, `CoverRegionPreview`): 0% i18n.
3. PRIORIDAD ALTA — Cerrar paridad de `va`: añadir las 6 claves `common:versionChangelog.*` y `documents:sendForReviewTitle` al locale valenciano (actualmente el usuario `va` verá fallback en inglés/español o la clave cruda).
4. PRIORIDAD MEDIA — Centralizar literales repetidos en `common`: "Guardando…", "Error al guardar", "Cancelar", "Eliminar", "Entendido", "Info", "Mensajes", "Ver cambios", "Crear nueva versión", "Sin contenido", "Este bloque no tiene descripción/contenido" aparecen en 5+ componentes. Crear claves comunes y reutilizar.
5. PRIORIDAD MEDIA — Convertir los arrays de constantes estáticas con `label` (`templates/constants.ts`, `DocxBlockSplitter/constants.ts`, `themes/themeBlocks.ts`) a funciones `buildOptions(t)` o resolver el `label` vía `t()` en el punto de render; hoy emiten español fijo en cualquier idioma.
6. PRIORIDAD MEDIA — Extraer a claves i18n los textos de los `ConfirmDialog` (description/confirmLabel/cancelLabel) en `DocumentPreviewPage` y `TemplatePreviewPage`.
7. PRIORIDAD MEDIA — Internacionalizar mensajes de error visibles de `useTemplateBlocks.ts` y los `setError` de `TemplateReviewPage`, `DocumentEditorPage`, `DocumentValidationPage` (permisos/identificadores inválidos): son de cara al usuario.
8. PRIORIDAD BAJA — Revisar `aria-label`/`title` de iconos (`FavoriteInlineMark`, `AbsoluteCanvas`, `BlockListItem`, botones Cerrar/Editar/Eliminar) por accesibilidad multiidioma.
