# Requisitos Extraídos — Transcripción Reunión Departamento

> **Fuente:** `docs/src/0_reunion_departamento.md`
> **Fecha de la reunión:** 2026-03-27, 10:18 AM (1h 50min)
> **Participantes:** Guillermo Garrido Portes + equipo de desarrollo (Aura, Alfredo, otros)
> **Fecha de extracción:** 2026-03-30

> **⚠️ NOTA:** Transcripción automática de videoconferencia. El lenguaje es coloquial y a veces ambiguo. Se han extraído los requisitos interpretando el consenso del equipo. Esta fuente introduce gran cantidad de detalle operativo y decisiones de UI no presentes en otros archivos.

---

## Requisitos Funcionales (RF)

### Sistema General (DMS)

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-001 | Maya DMS es un sistema gestor de calidad general | Aunque el foco actual son las Programaciones Didácticas, el sistema DMS general podrá llevar "cualquier archivo"/tipo de documentación. | Parcialmente nuevo: la conversación se centra solo en programaciones. |
| RF-DEPT-002 | Tipos de documentación | Existen diferentes tipos de documentación: programaciones, documentación de calidad, reservas, etc. Cada tipo tiene plantillas y documentos. | ⚠️ **Nuevo.** Amplía el scope más allá de programaciones. |
| RF-DEPT-003 | Programaciones del centro, departamento y módulo | 3 niveles: programación del centro (aplica a todos), de departamento y por módulo. | Confirma RF-CONV-022 pero añade "programación de centro" y "de departamento" como conceptos explícitos. |

### Plantillas

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-004 | Creadores de plantillas | ~~Jefe de departamento, jefe de estudios, dirección. Los profesores inicialmente NO crean plantillas.~~ **[MODIFICADO por D-03]** Todos los usuarios pueden crear plantillas. Los profesores solo pueden crear plantillas con visibilidad **personal**. Los roles superiores (Jefe de Departamento, Jefe de Estudios, Dirección) pueden crear plantillas con visibilidad para otros (Tipo de Estudio, Estudio, Módulo, Grupo). | Resuelto por D-03 |
| RF-DEPT-005 | Solo se crean documentos con plantillas publicadas | Solo se pueden crear documentos a partir de plantillas publicadas. Los borradores no son visibles para crear documentos. | Confirma RN-CONV-001. |
| RF-DEPT-006 | Plantillas personales permitidas | Se manejó la posibilidad de que profesores hagan plantillas personales (ej. plantilla de tutoriales) pero se dejó como decisión abierta. | ⚠️ **Indeciso.** "Por el momento vamos a pensar en que cuanto menos puedan hacer, mejor." |
| RF-DEPT-007 | Pertenencia polimórfica de plantilla | Una plantilla puede pertenecer a: persona individual, tipo de estudio, estudio, módulo, o **grupo**. Relación polimórfica total para extensibilidad futura. | Confirma RF-CONV-025 y amplía con "grupo" como RF-CLI-001. |
| RF-DEPT-008 | Clonar vs Nueva versión (distinción clave) | **Clonar** = crear plantilla nueva independiente, sin `parent_id`. Es como "crear nueva pero con campos rellenados". **Nueva versión** = misma plantilla, con referencia al padre, incrementa versión. | Confirma RN-CONV-006 con mucho más detalle. |
| RF-DEPT-009 | Plantillas publicadas son visibles según su asignación | Mientras está en borrador, solo la ve el creador. Una vez publicada, la ven quienes corresponda según su asignación polimórfica. | Confirma RF-CLI-002. |
| RF-DEPT-010 | Asignación de plantillas a usuarios | Una persona puede crear plantillas y asignarlas a otros usuarios (ej. clonar una plantilla y asignarla). | ⚠️ **Nuevo.** No aparece en la conversación principal. |
| RF-DEPT-011 | ~~Plantillas no se revisan~~ | ~~Las plantillas NO pasan por proceso de revisión. Se crean, se publican y ya está. Solo los documentos se revisan.~~ **[INVALIDADO por D-05]** Tanto plantillas como documentos **pueden** pasar por revisión si se les asigna un revisor. Sin revisor asignado → publicación directa. | Resuelto por D-05 |

### Documentos

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-012 | Clonación al crear documento | Cuando se crea un documento a partir de una plantilla publicada, se clonan todas las secciones de la plantilla (Snapshot). Si hay 4 secciones, se crean 4 nuevas. | Confirma DA-CONV-003. |
| RF-DEPT-013 | Estado de documento: Borrador, Revisión, Publicado | Confirmado. El estado Borrador es editable. | Confirma RF-CONV-016/017/018. |
| RF-DEPT-014 | ~~Documentos NO compartidos (MVP)~~ | ~~Por el momento, cada documento es de una persona particular. No se comparten documentos.~~ **[INVALIDADO por D-04, RESUELTO por D-29]** Los documentos **SÍ se comparten** en el MVP. El **autor principal** selecciona manualmente a qué usuarios/grupos se comparte. Permisos por colaborador: **edición** o **solo lectura**. Se permite **co-autoría** (varios editores). Solo el **autor principal** puede enviar a revisión. Conflictos de edición gestionados por bloqueo colaborativo (RF-DEPT-060). | Resuelto por D-04 + D-29 |
| RF-DEPT-015 | Visibilidad del documento según estado | Borrador: solo lo ves tú. En Revisión: lo pueden ver y comentar los revisores. Publicado: accesible para los que corresponda. | ⚠️ **Nuevo detalle** de visibilidad por estado. |
| RF-DEPT-016 | Eliminación de borradores permitida | Los borradores se pueden eliminar. Los documentos publicados NO por normativa. | Parcialmente nuevo: la conversación no detalla la eliminación. |
| RF-DEPT-017 | Todas las versiones publicadas se conservan | Las publicaciones se mantienen "de por vida" por normativa (ISO 9001). Posible poda futura a 5 años. | Confirma DA-CONV-013 con más detalle. |
| RF-DEPT-018 | Documento creado desde estudio pertenece al estudio | Si el documento se genera desde un estudio, queda asociado al estudio y accesible por los profesores de ese estudio. | ⚠️ **Nuevo matiz** sobre pertenencia contextual. |

### Validaciones y Revisión

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-019 | Validaciones configurables (0 a N) | Cada plantilla define cuántas validaciones requiere un documento, por quién y en qué orden. | ⚠️ **Nuevo.** La conversación solo habla de "el revisor" en singular. Aquí se introduce N validadores. |
| RF-DEPT-020 | Tipos de validación: Síncrona y Asíncrona | **Síncrona (ordenada):** Valida primero A, luego B, luego C en orden estricto. **Asíncrona (sin orden):** Todos validan cuando quieran; cuando todas las validaciones son check, se publica. | ⚠️ **Completamente nuevo.** No aparece en ningún otro archivo. |
| RF-DEPT-021 | Tipo de validación definido en la plantilla | La plantilla especifica si la validación es síncrona o asíncrona, y asigna los validadores y el orden. | ⚠️ **Nuevo.** |
| RF-DEPT-022 | Última validación = publicación (auto o manual) | ~~Cuando la última validación requerida se completa, el documento se publica automáticamente.~~ **[MODIFICADO por D-13, RESUELTO por D-33]** La plantilla define si la publicación es automática (default) o manual. Si es manual, el documento pasa a estado **"Validado"** tras la última aprobación, y solo el **usuario creador** puede pulsar "Publicar" para moverlo a "Publicado". Máquina de estados completa: Borrador → En Revisión → [Validado] → Publicado. | Resuelto por D-13 + D-33 |
| RF-DEPT-023 | Rechazo reinicia TODAS las validaciones | Si un validador rechaza y devuelve a Borrador, todo el proceso de validación se reinicia. Todas las validaciones previas se pierden. | ⚠️ **Nuevo y significativo.** Implica que un rechazo en el paso 3 de 3 obliga a volver a pasar por 1, 2 y 3. |
| RF-DEPT-024 | Comentarios por bloque al rechazar | Cuando un revisor rechaza, puede poner un comentario en un bloque concreto y devolver a Borrador. | Confirma RF-CONV-044. |
| RF-DEPT-025 | ~~Revisor puede editar~~ | ~~El revisor con permisos de edición puede modificar directamente el bloque.~~ **[INVALIDADO por D-02]** El revisor **solo puede comentar**, aceptar o rechazar. Los documentos solo se pueden editar en estado Borrador, no en Revisión. El revisor no modifica contenido. | Resuelto por D-02 |
| RF-DEPT-026 | Trazabilidad de autoría por bloque | Cada bloque guarda: creado por [usuario], modificado por [usuario] + historial de modificaciones. | ⚠️ **Nuevo nivel de detalle.** Amplía RNF-CONV-002. |

### Plazos y Prioridades

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-027 | Plazo de entrega en plantilla | Las plantillas tienen un campo de plazo/fecha de entrega. Todos los documentos creados desde esa plantilla heredan el plazo. | ⚠️ **Completamente nuevo.** No aparece en la conversación ni en la descripción. |
| RF-DEPT-028 | Prioridad calculada lógicamente | No se guarda una prioridad manual. La prioridad se calcula automáticamente por cercanía al plazo de entrega (ej. faltan X días → urgente). | ⚠️ **Nuevo.** Elegante solución que elimina campo de prioridad manual. |
| RF-DEPT-029 | Alertas y notificaciones basadas en plazo | El plazo permite programar notificaciones automáticas y mostrar alertas en el dashboard cuando se acerca la fecha. | ⚠️ **Nuevo.** Complementa RF-CONV-065. |
| RF-DEPT-030 | Validaciones ordenadas por prioridad/plazo | En la bandeja de validación, las más urgentes (próximas a vencer) aparecen primero. | ⚠️ **Nuevo criterio de orden.** |

### Dashboard y Navegación

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-031 | Página principal con tipos de documentación | El dashboard principal muestra los diferentes tipos de documentación (Programaciones, Calidad, etc.) como tarjetas/enlaces. | ⚠️ **Nuevo.** La conversación solo habla de dashboard de programaciones. |
| RF-DEPT-032 | Validaciones como sección destacada | Si el usuario tiene documentos pendientes de validar, aparece una tarjeta/mensaje destacado tipo alerta. Si no tiene → no se muestra. **[✅ CONFIRMADO por D-30]** El dashboard SÍ muestra información de validaciones pendientes in-app (tarjetas con prioridad por cercanía al plazo). Esto NO es una notificación push — es una query directa a BD implementada dentro de la app. Las notificaciones de eventos (asignaciones, aprobaciones) sí van por herramienta externa vía RabbitMQ. | ⚠️ **Confirmado.** |
| RF-DEPT-033 | Un único layout con aside | Tras debatir, se decide: un único layout principal con aside que muestra tipos de documentación. El aside cambia según la URL/sección. | ⚠️ **Decisión de diseño tomada en esta reunión.** |
| RF-DEPT-034 | Filtros en lugar de submenús | Los tipos de estudio, estudios y módulos se filtran mediante selectores/filtros en la parte superior del contenido, no mediante navegación en el aside. | ⚠️ **Nuevo.** Cambia la propuesta de la conversación (aside con árbol jerárquico). |
| RF-DEPT-035 | Filtros en cascada (reactivos) | Los filtros de tipo de estudio → estudio → módulo se cargan reactivamente desde el cliente (no llamadas API por cada cambio). Los datos de jerarquía se descargan una vez al inicio. | ⚠️ **Nuevo detalle técnico.** |
| RF-DEPT-036 | Plantillas y Documentos como secciones dentro de cada tipo de documentación | Al entrar a un tipo de documentación (ej. Programaciones), el usuario ve dos bloques: Templates y Docs. | ⚠️ **Nuevo layout.** |
| RF-DEPT-037 | Ordenación por fecha de última modificación | Los listados se ordenan por fecha de última modificación (desc). La publicación actualiza esa fecha. | Parcialmente nuevo: complementa RF-CONV-038. |

### Editor y Bloques (Detalle de Implementación)

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-038 | ~~Wizard de 2 pasos para crear plantilla~~ | ~~**Paso 1:** Editor de contenido. **Paso 2:** Asignar propiedades a cada bloque.~~ **[ELIMINADO por D-16]** Reemplazado por editor de 2 paneles simultáneos: panel izquierdo con índice de bloques, panel derecho con propiedades del bloque seleccionado (estado, info) + editor BlockNote. Ver **RF-DEPT-055**. | Resuelto por D-16 |
| RF-DEPT-039 | ~~Vista previa (Paso 3 del wizard)~~ | ~~Tras asignar propiedades, se muestra un preview renderizado.~~ **[ELIMINADO por D-16]** El wizard se elimina. El editor de 2 paneles permite ver las propiedades en tiempo real. | Resuelto por D-16 |
| RF-DEPT-040 | ~~Barra de progreso en wizard~~ | ~~El wizard tiene una barra de progreso visual.~~ **[ELIMINADO por D-16]** No aplica, el wizard ya no existe. | Resuelto por D-16 |
| RF-DEPT-041 | Bloque vacío como tipo de bloque | Se debe crear un tipo de bloque especial "Bloque vacío" que indica al docente que ahí debe rellenar contenido. Texto: "Rellena aquí" o similar. | Confirma RF-CONV-009 con más detalle visual. |
| RF-DEPT-042 | Indicador de "rellenar aquí" | Los bloques vacíos no pueden ser simplemente espacios en blanco (confusos). Deben tener un marcador visual claro (línea separadora, texto placeholder, cuadro punteado). | ⚠️ **Nuevo detalle UX.** |
| RF-DEPT-043 | Botón de información (i) por bloque | Cada bloque tiene un icono de información que al pulsar muestra la descripción/instrucciones de cómo rellenar ese bloque. | ⚠️ **Nuevo.** |
| RF-DEPT-044 | Información del bloque en página aparte o modal | Al pulsar el botón de información (i), se abre una nueva página o panel que muestra el manual/instrucciones de ese bloque específico. | ⚠️ **Nuevo.** |
| RF-DEPT-045 | BlockNote con drag & drop de bloques | Confirmado: BlockNote permite arrastrar bloques arriba y abajo (reordenar). | Confirma DA-CONV-007. |
| RF-DEPT-046 | Aplicación masiva de estado a bloques | Debe existir un botón para aplicar un estado (ej. "Bloquear todos") a todos los bloques de un mismo tipo de golpe. | ⚠️ **Nuevo.** |
| RF-DEPT-047 | Validar que bloques vacíos estén rellenos | Antes de enviar a revisión, el sistema debe verificar que todos los bloques vacíos obligatorios han sido rellenados por el docente. **Mecanismo exacto no definido.** | ⚠️ **Nuevo requisito sin solución cerrada.** |
| RF-DEPT-048 | Triple estado de bloque confirmado | Se confirman los 3 estados: Bloqueado (no modificable), Modificable (no borrable, avisa al revisor), Editable/Activo (sin restricciones). | Confirma RF-CLI-008/009/010. |

### Roles

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-049 | 4 roles iniciales | Profesor, Jefe de Departamento, Jefe de Estudios, Dirección. | ⚠️ **Primer lugar donde se definen explícitamente los 4 roles.** |
| RF-DEPT-050 | Roles vienen del sistema externo | Los roles y permisos vendrán de una BD externa (proyecto "Roles" futuro). Por ahora se asumen 4 fijos. | ⚠️ **Nuevo.** Confirma la existencia de un proyecto Roles paralelo. |
| RF-DEPT-051 | Dirección tiene máximos permisos (con excepciones) | Dirección puede hacer casi todo excepto posiblemente editar documentos de otros (por normativa). | ⚠️ **Nuevo matiz.** |
| RF-DEPT-052 | Permisos CRUD por rol | Cada rol tendrá como mínimo sus permisos CRUD definidos. No todos los roles pueden hacer todo. | ⚠️ **Nuevo detalle.** |

### PDF

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-DEPT-053 | Botón de descarga PDF | Habrá un botón "PDF" que descarga el documento renderizado con formato específico (cabecera, QR de validación). | Confirma RF-CONV-050/051/052. |
| RF-DEPT-054 | QR y cabecera son funcionalidad futura | La parte de seguridad (QR, cabecera corporativa) se deja para una fase posterior. "Solo que funcione" primero. | ⚠️ **Nuevo.** Sugiere que el PDF inicial sea simple y el sello QR venga después. |

---

## Decisiones Arquitectónicas (DA)

| ID | Decisión | Detalle |
|----|----------|---------|
| DA-DEPT-001 | BlockNote confirmado como editor | "Noteblock" o "BlockNote", hijo de TipTap. Genera JSON. Soporta D&D. | 
| DA-DEPT-002 | Un único layout con aside dinámico | Se descartó tener 2 layouts separados. Un layout con aside que cambia según la URL/sección. |
| DA-DEPT-003 | Filtros en cascada desde el cliente | Se descargan tipos de estudio/estudios/módulos una vez y se filtran reactivamente sin llamadas API adicionales. |
| DA-DEPT-004 | ~~Cada intro/enter en BlockNote podría ser un bloque~~ | ~~Se planteó como hipótesis que cada Enter genera un bloque nuevo en el JSON.~~ **[INVALIDADO por D-22]** No relevante. Un bloque es una unidad de contenido definida por el usuario, no se subdivide automáticamente. Puede contener texto, tablas, imágenes, etc. |
| DA-DEPT-005 | ~~Markdown como opción complementaria~~ | ~~Se mencionó la posibilidad de que el editor soporte markdown como input.~~ **[MODIFICADO por D-17]** No hay modo markdown separado. Solo soporte de importación: al pegar texto markdown, BlockNote lo interpreta y convierte al formato de bloque correspondiente. |

---

## Reglas de Negocio (RN)

| ID | Regla | Detalle |
|----|-------|---------|
| RN-DEPT-001 | Cada módulo tiene código único e independiente | Confirmado: no hay dos módulos con el mismo código, incluso si pertenecen a estudios distintos. |
| RN-DEPT-002 | Documentos publicados NO se eliminan | Por normativa ISO, las versiones publicadas se conservan indefinidamente (posible poda a 5 años en el futuro). |
| RN-DEPT-003 | Borradores SÍ se pueden eliminar | Solo los borradores son eliminables. |
| RN-DEPT-004 | Rechazo = vuelta a paso 0 | Si cualquier validador rechaza, se reinicia todo el flujo de validación desde el principio. |
| RN-DEPT-005 | ~~El último validador publica automáticamente~~ | ~~No hay un paso manual "Publicar" tras la última validación.~~ **[MODIFICADO por D-13, RESUELTO por D-33]** Configurable por plantilla: automática (default) o manual. Si manual → estado "Validado" intermedio, solo el creador puede publicar. |
| RN-DEPT-006 | El creador no puede auto-validar | Refuerza RN-CONV-002 SoD sobre auto-aprobación. |
| RN-DEPT-007 | El plazo se hereda de la plantilla al documento | Todos los documentos creados a partir de una plantilla heredan su fecha de plazo de entrega. |
| RN-DEPT-008 | ~~Plantillas solo las crean roles superiores (MVP)~~ | ~~Para el MVP: Jefe de Departamento, Jefe de Estudios, Dirección. Profesores no.~~ **[MODIFICADO por D-03]** Todos los usuarios crean plantillas. Profesores: solo visibilidad personal. Roles superiores: visibilidad compartida (Tipo de Estudio, Estudio, Módulo, Grupo). |

---

## Requisitos No Funcionales (RNF)

| ID | Requisito | Detalle |
|----|-----------|---------|
| RNF-DEPT-001 | Navegación máxima de 3 clics | Principio de diseño: cualquier acción principal accesible en 3 clics o menos. |
| RNF-DEPT-002 | Filtros no invasivos | Los filtros de tipo de estudio/estudio/módulo deben ser discretos y no abarrotar la pantalla, especialmente para dirección. |
| RNF-DEPT-003 | ~~Wizard de plantilla intuitivo~~ | ~~La creación de plantillas en 2+1 pasos.~~ **[ELIMINADO por D-16]** El wizard se sustituye por editor de 2 paneles directos. El requisito de usabilidad se mantiene pero aplica al editor de 2 paneles, no al wizard. |
| RNF-DEPT-004 | Datos de jerarquía cargados una vez | Los datos de tipos de estudio, estudios y módulos se descargan una sola vez al cargar la app. No se hacen llamadas API por cada cambio de filtro. |

---

## Decisiones Pendientes / No Cerradas

| ID | Tema | Estado | Detalle |
|----|------|--------|---------|
| DP-DEPT-001 | ¿Profesores pueden crear plantillas? | **Resuelto (D-03)** | Sí, pero solo con visibilidad personal. Roles superiores crean plantillas compartidas. |
| DP-DEPT-002 | ¿Cómo validar que todos los bloques vacíos estén rellenos? | **Resuelto (D-20)** | Validación dual: frontend (al enviar a revisión, muestra error) + backend (rechaza con 422 si hay bloques vacíos obligatorios). La plantilla define qué bloques son obligatorios vs opcionales. |
| DP-DEPT-003 | ¿Cómo manejar bloques tipo tabla con granularidad de celdas? | **Resuelto (D-19)** | No aplica. Un bloque es una unidad de contenido que puede contener tablas internamente. No hay bloques dentro de bloques. Tablas rellenables como bloque especial se dejan para futuras versiones (D-32). |
| DP-DEPT-004 | ¿Markdown como opción de entrada? | **Resuelto (D-17)** | No como modo separado. Solo importación por pegado: al pegar markdown, BlockNote lo interpreta automáticamente. |
| DP-DEPT-005 | ¿Cómo subdivide BlockNote los bloques internamente? | **Resuelto (D-22)** | No relevante con la nueva arquitectura. Un bloque = una unidad de contenido definida por el usuario. No se subdivide automáticamente. |
| DP-DEPT-006 | ¿Editor de plantilla: todo bloqueado por defecto? | **Resuelto (D-14)** | No. Todos los bloques son **editables por defecto**. El creador marca manualmente los que deben ser modificables o bloqueados. Botón de selección múltiple para cambio masivo de estado. |

---

## Requisitos Nuevos (derivados de respuestas Ronda 1)

> Los siguientes requisitos surgen de las respuestas provisionales a las dudas de la Ronda 1 y no estaban en la transcripción original.

### Editor y Bloques

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-055 | Editor de plantilla en 2 paneles simultáneos | Panel izquierdo: índice de bloques (lista navegable). Panel derecho: propiedades del bloque seleccionado (estado, info, flag obligatorio) + editor BlockNote para el contenido. Sustituye al wizard (RF-DEPT-038/039/040). **[NOTA D-35]** El plazo de entrega es a nivel de **plantilla**, NO por bloque individual (Opción A confirmada). Las propiedades del bloque son: estado (Editable/Modificable/Bloqueado), flag obligatorio (Sí/No), y descripción/instrucciones. | D-16, D-35 |
| RF-DEPT-056 | Diff visual en revisión de bloques "Modificable" | Cuando un bloque con estado "Modificable" es editado por el docente, el revisor ve un diff visual que resalta las diferencias entre el texto original y el modificado (tipo track changes). Si el revisor aprueba, se aceptan los cambios implícitamente. | D-01, D-28 |
| RF-DEPT-057 | Botón de selección múltiple para estado de bloques | Permite seleccionar varios bloques a la vez y cambiar su estado (Editable/Modificable/Bloqueado) de forma masiva con un único clic. | D-14 |
| RF-DEPT-058 | Flag obligatorio/opcional por bloque | Cada bloque en la plantilla tiene un campo que indica si es obligatorio (debe ser rellenado por el docente) u opcional (puede dejarse vacío). | D-20 |
| RF-DEPT-059 | Importación de markdown por pegado | Al pegar texto con formato markdown en un bloque, BlockNote lo interpreta y convierte automáticamente al formato de bloque correspondiente (encabezados, listas, negritas, etc.). No existe modo markdown separado. | D-17 |

### Bloqueo Colaborativo

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-060 | Bloqueo colaborativo en tiempo real | Cuando un usuario edita un bloque (en documento o plantilla), ese bloque se bloquea automáticamente para el resto de usuarios. **[✅ RESUELTO por D-36]** Tecnología: **WebSocket con Laravel Reverb/Echo + Pusher**. Lock a nivel de bloque (no documento completo). Timeout de lock: **5 minutos**. Heartbeat para detectar desconexiones y liberar locks automáticamente. Se muestra **presencia** en la UI (qué usuarios están viendo el documento). ⚠️ **Nota D-36:** Analizar si WebSocket es la mejor opción para MVP — considerar polling HTTP como alternativa más simple para v1. | D-04, D-36 |
| RF-DEPT-061 | Mensaje "Bloqueado por usuario X" | Los usuarios que intentan editar un bloque que está siendo editado por otro ven un mensaje claro: "Bloqueado por [nombre del usuario]". El bloque aparece visualmente distinto (ej. borde rojo, icono de candado). | D-04 |
| RF-DEPT-062 | Mensaje "Editando bloque X" | El usuario que está editando un bloque ve una indicación visual de que está en modo edición activa (ej. borde azul, texto "Editando"). | D-04 |

### Validaciones y Revisión

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-063 | Asignación opcional de revisor | La revisión es **opcional** tanto para plantillas como para documentos. Si no se asigna ningún revisor, el creador puede publicar directamente (skip del estado "En Revisión"). Si se asigna al menos un revisor, el flujo pasa por el estado "En Revisión" normalmente. | D-05 |
| RF-DEPT-064 | Toggle "Validación ordenada" en plantilla | La plantilla incluye un campo boolean "Validación ordenada: Sí/No" que define si las validaciones son síncronas (en orden estricto) o asíncronas (en cualquier orden). Default: No (asíncrona). | D-07 |
| RF-DEPT-065 | Visibilidad condicional para validadores síncronos | En validación síncrona, el documento NO aparece en la bandeja del validador N hasta que el validador N-1 haya aprobado. Todos los validadores asíncronos reciben notificación simultánea. | D-07 |
| RF-DEPT-066 | Validación dual de bloques vacíos obligatorios | Frontend: al intentar enviar a revisión, se muestra error indicando qué bloques obligatorios están vacíos. Backend: valida y devuelve 422 si bloques obligatorios están vacíos. Se considera "relleno" cualquier contenido que no sea solo espacios en blanco. | D-20 |

### Workflow de Delegación

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-067 | Asignación de documentos a usuarios | Un usuario con permisos (ej. Jefa de Estudios) puede crear un documento en modo borrador desde una plantilla y asignarlo a otro usuario (ej. profesor) para que lo rellene. El profesor recibe notificación (vía sistema externo) de que tiene un documento asignado. La asignación se puede revocar o cambiar. **[✅ RESUELTO por D-34]** El profesor **NO puede rechazar** la asignación. El **autor para SoD y trazabilidad es el profesor asignado** (no el superior que lo creó). El superior **NO puede editar** el documento después de asignarlo. **Es funcionalidad MVP.** El documento aparece en los borradores del profesor asignado. | D-21, D-34 |

### Dashboard y Ordenación

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-068 | Cabeceras de tabla clicables para ordenar | Las tablas/listados de documentos y plantillas tienen cabeceras clicables que permiten ordenar por cualquier columna relevante (fecha de modificación, prioridad, nombre, estado). Default: fecha de modificación desc. En bandeja de validaciones: prioridad por cercanía al plazo. | D-10 |

### Arquitectura

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-069 | Arquitectura multi-tipo-documentación | El sistema se diseña desde el inicio para soportar múltiples tipos de documentación (Programaciones, Calidad, Reservas, etc.). En el MVP, el sidebar solo muestra el enlace a "Programaciones". Los demás tipos se añadirán como enlaces adicionales en futuras versiones sin rediseño de arquitectura. | D-12 |

### Nuevas Decisiones Arquitectónicas

| ID | Decisión | Detalle | Origen |
|----|----------|---------|--------|
| DA-DEPT-006 | Tabla de auditoría separada para historial de bloques | El historial completo de modificaciones por bloque (creado por, modificado por, fechas, valores antes/después) se almacena en una **tabla de auditoría separada**, no dentro del JSONB de contenido principal. | D-11 |
| DA-DEPT-007 | RabbitMQ como sistema de colas | Las notificaciones se envían a través de RabbitMQ. La app SOLO emite eventos a la cola; NO muestra notificaciones in-app. La visualización de notificaciones corresponde a una herramienta externa. | D-24 |
| DA-DEPT-008 | Motor PDF: Puppeteer/Chromium headless | La generación de PDFs utiliza Puppeteer/Chromium headless para alta fidelidad. El proceso es asíncrono (Job en cola). Los PDFs generados se cachean y se regeneran solo si el documento ha sido modificado. El usuario recibe notificación (vía sistema externo) cuando el PDF está listo. QR y cabecera corporativa se dejan para fase posterior. | D-25 |
| DA-DEPT-009 | WebSocket con Laravel Reverb/Echo + Pusher para bloqueo colaborativo | Infraestructura de comunicación en tiempo real para locking de bloques y presencia de usuarios. Lock a nivel de bloque con timeout de 5 minutos y heartbeat. ⚠️ Analizar si polling HTTP simple es suficiente para MVP (menor infraestructura/coste). | D-36 |
| DA-DEPT-010 | Documentos vinculados a versión de plantilla original | Los documentos siguen funcionando con la versión de plantilla con la que fueron creados, ignorando nuevas versiones. Se muestra un banner informativo al usuario indicando que hay una nueva versión disponible. No se fuerza migración ni se bloquea el documento. | D-39 |

---

## Requisitos Nuevos (derivados de respuestas Ronda 2)

> Los siguientes requisitos surgen de las respuestas provisionales a las dudas de la Ronda 2.

### Colaboración y Compartición de Documentos

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-070 | Autor selecciona colaboradores manualmente | El autor principal de un documento puede sel. a qué usuarios o grupos se comparte el documento, especificando permisos de **edición** o **solo lectura** por colaborador. | D-29 |
| RF-DEPT-071 | Co-autoría con múltiples editores | Se permite co-autoría: varios usuarios pueden tener permiso de edición sobre el mismo documento. Solo el **autor principal** puede enviar el documento a revisión. | D-29 |
| RF-DEPT-072 | Presencia de usuarios en documento | Se muestra en la UI qué usuarios están viendo o editando el documento en tiempo real (indicadores de presencia). | D-36 |

### Máquina de Estados Ampliada

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-073 | Estado "Validado" opcional | Si la plantilla define publicación **manual**, el documento pasa a estado "Validado" tras la última aprobación. Solo el autor/creador puede pulsar "Publicar" para moverlo a "Publicado". Si la plantilla define publicación **automática** (default), se salta este estado. Máquina: Borrador → En Revisión → [Validado] → Publicado. | D-33 |

### Segregación de Funciones

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-074 | SoD `creator_id ≠ reviewer_id` desde MVP | Se implementa validación en el backend que impide que el creador de un documento sea su propio validador/revisor. Es una **política por usuario**, no por rol. RBAC completo con roles se difiere a V2. | D-31 |

### Gestión de Grupos

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-075 | CRUD de grupos en la aplicación | Interfaz CRUD para crear, editar y eliminar grupos dentro de Maya PD. Los grupos son **100% internos** (no se sincronizan con FDW). Un usuario puede pertenecer a **múltiples grupos**. No se contempla anidamiento (un grupo no contiene otros grupos). Solo usuarios con permisos adecuados (ej. Jefe de Estudios) pueden gestionar grupos. | D-38 |

### Versionado de Plantillas

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-076 | Banner informativo de nueva versión de plantilla | Si un documento está basado en una plantilla cuya nueva versión ha sido publicada, se muestra un **banner informativo** al usuario indicando que hay una versión más reciente disponible. El documento NO se bloquea ni se fuerza migración. El usuario puede seguir editando con la versión original. | D-39 |
| RF-DEPT-077 | Revisión de plantillas con N-validadores | Las plantillas utilizan el **mismo sistema de 0-N validadores** (síncronos/asíncronos) que los documentos. El creador de la plantilla configura los validadores. Mientras una plantilla está en revisión, se pueden seguir creando documentos desde su **última versión publicada**. Al publicar la nueva versión (ej. v1.0 → v2.0), se genera automáticamente un nuevo registro de versión. | D-37 |

### Futuro (post-MVP documentado)

| ID | Requisito | Detalle | Origen |
|----|-----------|---------|--------|
| RF-DEPT-078 | Bloque tipo "tabla rellenable" alimentada por API | Bloque especial (futuro) que se rellena automáticamente con datos de APIs externas, dejando solo ciertas celdas editables. Ej: tabla de alumnos con columna "observaciones" vacía para el docente. NO son tablas dinámicas (no se añaden/quitan filas). Diferido a versiones posteriores. | D-32 |
