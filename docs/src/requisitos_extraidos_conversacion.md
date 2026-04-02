# Requisitos Extraídos — Conversación de Discovery Arquitectónico

> **Fuente:** `docs/src/0_conversacion_completa.md`
> **Fecha de la sesión:** 2026-03-25
> **Participantes:** Cliente (Product Owner) + Arquitecto IA (Full-Stack / Ciberseguridad)
> **Fecha de extracción:** 2026-03-30

---

## Requisitos Funcionales (RF)

### Entidades Core

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-001 | CRUD de Plantillas | Se deben poder crear, clonar, editar y eliminar plantillas. |
| RF-CONV-002 | Composición de Plantillas por Bloques | Cada plantilla está compuesta de bloques o secciones. |
| RF-CONV-003 | Estados de sección en Plantilla | ~~Las secciones pueden estar abiertas (editables) o bloqueadas (texto normativo fijo).~~ **[MODIFICADO por D-01]** 3 estados confirmados: **Editable** (sin restricciones, incluye borrado), **Modificable** (editable pero no borrable, alerta al revisor con texto original para comparar), **Bloqueado** (no modificable). Los bloques son **editables por defecto** (D-14). El creador define el estado en el editor de 2 paneles. |
| RF-CONV-004 | Metadatos de sección | Cada sección almacena información relevante (descripción, explicación de cómo rellenarla) y su fecha de creación. |
| RF-CONV-005 | Creación de Documentos desde Plantilla | De cada plantilla publicada se pueden crear Documentos (Programaciones Didácticas). |
| RF-CONV-006 | Secciones bloqueadas en Documento | Si un docente desbloquea una sección bloqueada, el sistema marca el documento como "requiere revisión". |
| RF-CONV-007 | Secciones abiertas en Documento | Las secciones abiertas requieren que el docente las actualice/rellene. |
| RF-CONV-008 | Añadir secciones en Documento | En el documento, el docente puede crear y añadir más secciones de las que venían en la plantilla (ej. n filas de tabla, bloques customizados predefinidos). |
| RF-CONV-009 | Bloque vacío rellenable | La plantilla puede contener bloques vacíos que el docente debe rellenar con 1 a n secciones. |

### Versionado

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-010 | Estados de Plantilla | Las plantillas tienen modo Borrador o Publicado. |
| RF-CONV-011 | Borrador único de Plantilla | Solo se puede crear un único borrador de una plantilla a la vez. Al publicar, el borrador desaparece y la plantilla incrementa su versión. |
| RF-CONV-012 | Clonación de secciones al crear borrador | Cada vez que se crea un borrador, se clonan todas las secciones con el contenido de la publicación anterior. Se editan hasta la siguiente publicación. |
| RF-CONV-013 | Patrón Snapshot (no deltas) | El versionado se realiza mediante clonación completa de registros en BD (Snapshot), no almacenamiento de diferencias o Event Sourcing. |
| RF-CONV-014 | Changelog obligatorio al publicar | Cada publicación de nueva versión exige un "mensaje de commit" (descripción corta de los cambios realizados), al estilo Git. |
| RF-CONV-015 | Historial de versiones como Timeline | El historial de versiones se muestra como una línea temporal vertical con fecha, autor y resumen descriptivo por cada versión. |

### Ciclo de Vida del Documento (Máquina de Estados)

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-016 | Estado Borrador | El documento es editable por el autor. |
| RF-CONV-017 | Estado En Revisión | El documento queda bloqueado para el autor. El revisor lo lee y añade feedback. |
| RF-CONV-018 | Estado Publicado | Versión oficial, inmutable y vigente. La versión anterior se conserva en el historial. |
| RF-CONV-019 | Transiciones de estado | Borrador → En Revisión → Publicado. Si se rechaza, vuelve a Borrador. **[NOTA D-33]** Si la plantilla define publicación manual, se añade estado intermedio: Borrador → En Revisión → **Validado** → Publicado. En estado "Validado", solo el creador puede pulsar "Publicar". Si publicación automática (default), se mantiene el flujo de 3 estados. |
| RF-CONV-020 | ~~Revisión siempre obligatoria~~ | ~~Todo documento debe ser revisado antes de publicarse.~~ **[MODIFICADO por D-05]** La revisión es **opcional**. Si se asigna al menos un revisor → flujo normal (Borrador → Revisión → Publicado). Si **no se asigna revisor** → el creador puede publicar directamente (skip Revisión). Aplica tanto a documentos como a plantillas. |
| RF-CONV-021 | Revisión basada en permisos | La persona con el permiso de aprobar bloques de dicho template será quien pueda aprobar el cambio. |

### Jerarquía Académica

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-022 | Estructura de 3 niveles | Tipo de Estudio (FP, Bachillerato) → Estudio (DAW, Comercio) → Módulo (DWES, Inglés). |
| RF-CONV-023 | Agrupación de Plantillas | Las plantillas se agrupan por Tipo de Estudio o por Estudio. |
| RF-CONV-024 | Documento por Módulo | Cada profesor crea un documento para cada módulo que imparte. |
| RF-CONV-025 | Relación Polimórfica | La pertenencia de una plantilla es polimórfica (`templateable_id` + `templateable_type`) para soportar futuros niveles (Departamento, Centro, etc.). |

### Migración de Versiones

> **⚠️ NOTA [D-26/D-39]:** La migración se difiere a futuras versiones. Los requisitos RF-CONV-026 a RF-CONV-030 se mantienen documentados pero **no se implementan en el MVP**. **[RESUELTO por D-39]** En el MVP, el documento sigue funcionando con la versión de plantilla original. Se muestra un banner informativo al usuario si hay nueva versión disponible. No se bloquea ni se fuerza migración.

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-026 | Bloqueo por nueva versión de plantilla | Si un borrador está en curso (Plantilla v1.0) y se publica la v2.0, el borrador se bloquea y exige migración. |
| RF-CONV-027 | Inyección automática aditiva | La migración inyecta las secciones nuevas de la v2.0 automáticamente. No destruye datos previos del usuario; lo obsoleto se marca como `archived_due_to_migration`. |
| RF-CONV-028 | UUID inmutable por bloque | Cada sección de la plantilla tiene un UUID que persiste entre versiones. La migración compara UUIDs de la v1.0 y v2.0 para detectar cambios. |
| RF-CONV-029 | Banner informativo de migración | No usar palabras como "Bloqueado" o "Error". Usar un banner azul informativo explicando la actualización y ofreciendo un clic para adaptar el documento. |
| RF-CONV-030 | Resumen de cambios pre-migración | Antes de ejecutar la migración, mostrar un resumen: "Se añadirá 1 nueva sección obligatoria: [nombre]". |

### Editor de Bloques

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-031 | Editor tipo Notion (BlockNote) | El editor usa un componente estilo Notion (BlockNote o similar). No es HTML WYSIWYG. |
| RF-CONV-032 | Almacenamiento JSONB | El contenido se almacena como JSON/JSONB estructurado (array de objetos bloque). No HTML. |
| RF-CONV-033 | Comando "/" para añadir bloques | Al escribir `/`, se despliega un menú con opciones: "Añadir Texto", "Añadir Tabla de Criterios", etc. |
| RF-CONV-034 | Tipos de bloque múltiples | Se soportan tablas, imágenes, texto, bloques customizados (ej. tabla con columnas predefinidas y celdas a rellenar). |
| RF-CONV-035 | Bloques customizados a prueba de errores | Las tablas predefinidas tienen celdas de cabecera bloqueadas (fondo gris + candado). Solo las celdas vacías permiten edición. |

### Dashboard

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-036 | Dashboard unificado por acción | Panel único con "Tarjetas de Acción" mezclando tareas propias y revisiones. No hay pestañas separadas. |
| RF-CONV-037 | Tarjetas con badges semánticos | 🔴 Urgente/Rechazado (arriba), 🟠 Pendiente de revisión (coordinadores), 🔵 Borrador (trabajos en curso). |
| RF-CONV-038 | Tarjetas resumen + Tablas filtrables | El dashboard muestra tarjetas resumen ("3 borradores"). Al hacer clic → tabla de datos filtrable. |
| RF-CONV-039 | Aside colapsable con jerarquía | ~~Barra lateral izquierda con árbol: Tipos de Estudio → Estudios. Colapsable.~~ **[MODIFICADO por D-12/RF-DEPT-033/034]** Un único layout con aside dinámico. En el MVP, el aside solo muestra el enlace a "Programaciones". Los filtros de Tipo de Estudio → Estudio → Módulo se implementan como **selectores en cascada en la parte superior del contenido**, no como árbol jerárquico en el aside. Los datos de jerarquía se cargan una vez al inicio (reactivos desde el cliente). |
| RF-CONV-040 | Filtrado por permisos del usuario | El profesor solo ve los tipos de estudio y estudios que le corresponden. Dirección ve todo. |
| RF-CONV-041 | Prevención de duplicados | Si ya existe un borrador o documento publicado para ese año y módulo, no mostrar botón "Crear" sino "Continuar Borrador" o "Ver Programación Actual". |

### Flujo de Creación Contextual (Poka-yoke)

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-042 | Creación contextual | El profesor navega a su módulo en el aside, ve un botón "Crear Programación 2026/2027" que auto-selecciona la plantilla correcta sin formularios intermedios. |
| RF-CONV-043 | Resolución de plantilla en backend | Laravel recibe el `module_id`, busca la plantilla publicada activa correspondiente y clona su estructura JSON para crear el borrador. |

### Sistema de Comentarios y Revisión

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-044 | Comentarios anclados por bloque (estilo Google Docs) | El revisor hace clic en un bloque específico y añade un comentario anclado a ese bloque por UUID. No comentario general. |
| RF-CONV-045 | Tabla separada de comentarios | Los comentarios se almacenan en tabla `block_comments` (no dentro del JSON del documento). Campos: `document_id`, `block_uuid`, texto, estado. |
| RF-CONV-046 | Soft Delete de comentarios | Los comentarios nunca se borran físicamente. Pasan a estado "Resuelto" (Soft Delete) para auditoría ISO. |
| RF-CONV-047 | Panel lateral contextual (Drawer) | Al hacer clic en la burbuja de comentario, se desliza un panel lateral derecho mostrando el hilo. |
| RF-CONV-048 | Resolución a un clic | Botón "Marcar como Resuelto" para ocultar la burbuja y limpiar la interfaz. |

### Exportación PDF

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-049 | Generación PDF asíncrona (Jobs) | Al publicarse un documento, Laravel lanza un Job en segundo plano para generar el PDF. No se genera on-the-fly. |
| RF-CONV-050 | Descarga de PDF oficial | Botón "Descargar PDF Oficial" en React. El PDF ya está pre-generado y se sirve en milisegundos. |
| RF-CONV-051 | Maquetación automática del PDF | El PDF incluye logotipos oficiales, colores corporativos, tipografía exacta. Sin opciones de configuración para el usuario. |
| RF-CONV-052 | Sello de autenticidad (QR/Hash) | El PDF inyecta en cada página: número de versión, fecha, autor/revisor, y un Código QR o Hash alfanumérico verificable. **[NOTA: D-25/RF-DEPT-054]** QR y cabecera corporativa se dejan para fase posterior al MVP. Motor PDF: Puppeteer/Chromium headless. |

### Herencia de Plantillas

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-053 | Creación de plantillas a partir de otras | Las plantillas pueden crearse basándose en otras, heredando sus bloques. |
| RF-CONV-054 | Herencia por Snapshot (no viva) | Al crear Plantilla B basada en A, se hace clonación estática + se guarda `parent_template_id = A`. No hay herencia viva en cascada. |
| RF-CONV-055 | Notificación de actualización de padre | Si la Plantilla A (padre) saca nueva versión, las hijas reciben notificación para integrar cambios voluntariamente. |
| RF-CONV-056 | Niveles de visibilidad | Plantillas: Oficiales del Centro (con check azul), De mi Departamento, Mis Plantillas Propias. |
| RF-CONV-057 | Sello `is_official` | Solo usuarios con rol específico (Quality_Manager o Jefatura) pueden marcar plantillas como oficiales a nivel de Tipo de Estudio. |
| RF-CONV-058 | Galería de inicio "Duplicar y Adaptar" | Al crear plantilla, galería visual con 3 pestañas (Oficiales, Departamento, Personales) y botón "Duplicar y Adaptar". |

### Migración Anual (Cambio de Curso)

> **⚠️ NOTA [D-26]:** La migración side-by-side se difiere a futuras versiones. Los requisitos RF-CONV-059 a RF-CONV-064 se mantienen documentados pero **no se implementan en el MVP**.

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-059 | Pizarra en blanco dirigida | Al cambiar de plantilla (no nueva versión), se crea documento nuevo con la nueva plantilla. Los datos del año anterior NO se inyectan automáticamente en la nueva estructura. |
| RF-CONV-060 | Metadato de origen | El nuevo documento guarda `cloned_from_legacy_document_id` para trazabilidad. |
| RF-CONV-061 | Pantalla dividida Side-by-side | Documento antiguo (solo lectura) a la izquierda, nuevo documento (editable) a la derecha. |
| RF-CONV-062 | Drag & Drop entre documentos | El profesor puede arrastrar bloques del documento antiguo a los bloques no bloqueados del nuevo documento. |
| RF-CONV-063 | Botón alternativo para táctiles | Además del D&D, cada bloque del documento antiguo tiene un botón ➡️ que lo "inyecta" automáticamente en el primer hueco disponible del documento nuevo. |
| RF-CONV-064 | Aceptar y crear borrador | Al confirmar la migración visual, se crea un draft donde el docente puede seguir editando cada bloque. |

### Notificaciones

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-065 | Notificaciones a cola | Las notificaciones se envían a una cola de eventos (`Event::dispatch()`). **[RESUELTO por D-24]** Cola: RabbitMQ. La app solo emite eventos; NO muestra notificaciones in-app. La visualización corresponde a herramienta externa. |
| RF-CONV-066 | Payload seguro | El mensaje de la cola solo contiene metadatos anónimos (`event`, `document_id`, `user_id`, `timestamp`). Nunca contenido del documento. |
| RF-CONV-067 | Log de evento encolado | Se registra un log de "Evento de Notificación Encolado" para auditoría ISO (prueba de que el sistema emitió el aviso). |

### Autenticación y Usuarios

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-068 | SSO por token JWT | Los usuarios llegan desde un dashboard principal con un token JWT. No hay pantalla de registro ni gestión de contraseñas en la app. |
| RF-CONV-069 | Recuperación de sesión expirada | Si el token expira mientras el profesor edita, se guarda el progreso localmente y se muestra un modal para renovar sesión sin perder trabajo. |

### Búsqueda

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-CONV-070 | Búsqueda solo por filtros estáticos | MVP con filtros en tabla: Año, Estudio, Estado. No Full-Text Search dentro del contenido JSON. |

---

## Requisitos No Funcionales (RNF)

| ID | Requisito | Detalle |
|----|-----------|---------|
| RNF-CONV-001 | Cumplimiento ISO 9001 | Toda acción de publicación/aprobación genera registro de auditoría inmutable con estampa de tiempo del servidor. |
| RNF-CONV-002 | Audit Trail inmutable | Registro de quién desbloqueó una sección, cuándo (reloj del servidor), y valores antes/después. |
| RNF-CONV-003 | RBAC estricto en API | ~~Control de acceso basado en roles en el backend. Permisos granulares.~~ **[MODIFICADO por D-23, RESUELTO por D-31]** En el MVP no se implementa RBAC completo por roles. Se implementan **políticas por usuario (no por rol)**. Desde el día 1 se aplica la validación SoD mínima: `creator_id ≠ reviewer_id` para toda validación/revisión. RBAC completo con roles granulares se difiere a **V2**. |
| RNF-CONV-004 | Bloqueo a nivel de API en Revisión | Cuando el documento está "En Revisión", Laravel rechaza cualquier `PUT/PATCH` del docente. |
| RNF-CONV-005 | Prevención IDOR | El endpoint de dashboard no recibe `user_id` del cliente. Se lee del token de sesión. Filtros Global Scopes obligatorios. |
| RNF-CONV-006 | Respuesta HTTP < 50ms | Las notificaciones y PDFs se procesan en colas para que las peticiones HTTP respondan rápidamente. |
| RNF-CONV-007 | Paginación de tablas | Endpoint paginado (15 en 15). React solo pide lo necesario. |
| RNF-CONV-008 | Sanitización anti-XSS | Al recibir JSON, Laravel valida estructura y bloquea inyección de scripts maliciosos. |
| RNF-CONV-009 | URLs firmadas para archivos privados | Imágenes y adjuntos en almacenamiento privado. Laravel genera URLs temporales firmadas. Sesión inválida → 403. |
| RNF-CONV-010 | Validación de firma JWT (Zero Trust) | Laravel valida la firma criptográfica del token JWT. No confía en tokens no verificados. |
| RNF-CONV-011 | FDW Solo Lectura | La conexión FDW usa un usuario PostgreSQL con permisos estrictamente de Solo Lectura (SELECT). |
| RNF-CONV-012 | Minimización de JOINs FDW | No hacer JOINs complejos hacia tabla externa. Apoyarse en caché/token para datos de usuario. |
| RNF-CONV-013 | Prevención DoS en búsquedas | Se descarta Full-Text Search en JSON por riesgo de sobrecarga. Solo filtros estáticos indexados. |
| RNF-CONV-014 | UX tipo Notion / Google Docs | Interfaz limpia, sin jerga técnica. Secciones bloqueadas con fondo distinto + icono candado. |
| RNF-CONV-015 | Carga cognitiva cero en dashboard | El dashboard muestra solo los módulos que imparte el profesor. No requiere navegar toda la jerarquía. |
| RNF-CONV-016 | Feedback visual de estados (Stepper) | ⚪ Borrador (Gris) → 🟡 En Revisión (Amarillo) → 🟢 Publicado (Verde). |
| RNF-CONV-017 | Validación backend de bloques bloqueados | Al migrar/crear, Laravel cruza el JSON recibido con el esquema de la plantilla original. Si detecta alteración de bloque bloqueado → 422. |

---

## Decisiones Arquitectónicas (DA)

| ID | Decisión | Justificación |
|----|----------|---------------|
| DA-CONV-001 | Stack: React + Laravel + PostgreSQL | Stack inamovible definido por el cliente. |
| DA-CONV-002 | Versionado por BD, no por Git | Git requeriría `shell_exec`, archivos temporales y comunicación con servicio externo. BD es más simple, rápida y auditable. |
| DA-CONV-003 | Patrón Snapshot (clonación de registros) | Cada publicación clona todos los bloques. Consultas simples: `SELECT * WHERE version_id = X`. |
| DA-CONV-004 | Relaciones Polimórficas para pertenencia | `templateable_id` + `templateable_type` para flexibilidad futura. |
| DA-CONV-005 | Máquina de Estados (State Machine) | No usar `if (status == 'en_revision')`. Usar patrón State Machine con paquete Laravel. |
| DA-CONV-006 | JSON/JSONB para contenido de bloques | Permite validación estricta, prevención XSS, facilita generación de PDFs. |
| DA-CONV-007 | Editor BlockNote (hijo de TipTap) | Experiencia tipo Notion, genera JSON, soporta D&D, gratuito. |
| DA-CONV-008 | Endpoint BFF para Dashboard | Un único endpoint `GET /api/dashboard` con Eager Loading devuelve todas las tarjetas. |
| DA-CONV-009 | DTOs para lista unificada de tarjetas | API devuelve lista unificada con `action_type` por tarjeta. React renderiza componente adaptativo. |
| DA-CONV-010 | Colas/Jobs para PDF y Notificaciones | Procesos asíncronos: `DocumentRejected`, `DocumentPublished` → Event → Listener → Queue. |
| DA-CONV-011 | FDW para tabla de usuarios | Tabla de usuarios en BD central consultada vía Foreign Data Wrapper. Sin duplicar datos. |
| DA-CONV-012 | Eloquent + FDW transparente | Modelo `User` de Laravel apunta a tabla foránea como si fuera local. |
| DA-CONV-013 | Versiones publicadas Append-Only | Filas de versiones publicadas son de solo lectura + inserción. Nunca actualización ni borrado. |
| DA-CONV-014 | Herencia de plantillas por clonación, no viva | Evita consultas recursivas y sobreescritura accidental. |

---

## Reglas de Negocio (RN)

| ID | Regla | Detalle |
|----|-------|---------|
| RN-CONV-001 | Solo se crean documentos con plantillas publicadas | No se puede crear un documento a partir de una plantilla en borrador. |
| RN-CONV-002 | Segregación de Funciones (SoD) | El `user_id` creador jamás puede ser el `reviewer_id` aprobador del mismo documento, incluso si tiene rol de Súper-Administrador. |
| RN-CONV-003 | Desbloqueo de sección → Requiere revisión | Cualquier desbloqueo de sección bloqueada marca el documento para revisión obligatoria. |
| RN-CONV-004 | ~~Migración forzosa por nueva versión de plantilla~~ | ~~Si la plantilla base se actualiza, el borrador del docente se bloquea hasta que migre.~~ **[MODIFICADO por D-26/D-39]** En el MVP **NO hay migración forzosa**. El documento sigue funcionando con la versión de plantilla con la que se creó, ignorando nuevas versiones. Se muestra un **banner informativo** al usuario indicando que hay una nueva versión de plantilla disponible. No se bloquea ni se obliga a migrar. |
| RN-CONV-005 | Migración estrictamente aditiva | La migración nunca destruye datos del usuario. Lo obsoleto se marca como `archived`. |
| RN-CONV-006 | Clonar ≠ Nueva versión | Clonar = plantilla nueva independiente (sin `parent_id`). Nueva versión = misma plantilla con referencia al padre. |
| RN-CONV-007 | Cambio de plantilla ≠ Nueva versión | Si se cambia a una plantilla diferente (no versión), se crea documento nuevo desde cero con migración visual Side-by-side. |
| RN-CONV-008 | PDF como artefacto oficial | El PDF es el artefacto final exigido por inspección educativa. |
| RN-CONV-009 | Trazabilidad de origen del documento | Todo documento registra el ID exacto de la versión de plantilla usada en el momento de creación. |
| RN-CONV-010 | Inmutabilidad de versiones publicadas | Una vez publicado, el documento/plantilla no puede ser editado. Solo crear nueva versión. |
