# Dudas, Contradicciones e Inconsistencias — Análisis Cruzado

> **Fecha de generación:** 2025-03-30
> **Archivos comparados:**
>
> 1. `requisitos_extraidos_conversacion.md` (CONV) — 70 RF, 17 RNF, 14 DA, 10 RN
> 2. `requisitos_extraidos_descripcion.md` (DESC) — 25 RF, 10 RNF, 8 DA, 6 R, 4 SH, 7 G, 7 CE
> 3. `requisitos_extraidos_reunion_cliente.md` (CLI) — 11 RF, 1 DA, 2 RN
> 4. `requisitos_extraidos_reunion_departamento.md` (DEPT) — 54 RF, 4 RNF, 5 DA, 8 RN

---

## Leyenda de Severidad

| Icono | Severidad | Descripción |
|-------|-----------|-------------|
| 🔴 | **Contradicción directa** | Dos fuentes dicen cosas incompatibles. Requiere decisión antes de diseñar. |
| 🟡 | **Ambigüedad / Inconsistencia** | No es contradictorio, pero genera confusión o tiene más de una interpretación. |
| 🟢 | **Decisión pendiente** | Se mencionó pero no se cerró. Requiere confirmación explícita. |
| ⚪ | **Hueco funcional** | Flujo, regla o caso de uso no cubierto en ninguna fuente. |

---

## 1. Contradicciones Directas 🔴

### D-01 — Estados de bloque: ¿2 o 3?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | 2 estados: **Abierto** (editable) y **Bloqueado** (no editable) | RF-CONV-007, RF-CONV-008, RF-CONV-009 |
| CLI | 3 estados: **Editable** (sin restricciones), **Modificable** (editable pero alerta al revisor, no borrable, compara con original), **Bloqueado** (no modificable) | RF-CLI-008, RF-CLI-009, RF-CLI-010 |
| DEPT | Confirma los 3 estados: Bloqueado, Modificable, Editable/Activo | RF-DEPT-048 |

**Pregunta:** ¿Se confirma el modelo de 3 estados (Editable / Modificable / Bloqueado)? Si es así, el estado "Abierto" de la conversación debe desdoblarse en dos. ¿El estado "Modificable" implica que el revisor recibe una notificación automática con el texto original para comparar? ¿Quién define qué bloques son "modificables" vs "editables" — el creador de la plantilla en el wizard?

**Respuesta provisional:** el modelo de 3 estados es el correcto. EL revisor al abrir el documento a revisar ve el texto original de los bloques modificables, con una UI que permite comparar fácilmente (ej. diff visual o versión lado a lado). El creador de la plantilla define el estado de cada bloque en el paso 2 del wizard.

---

### D-02 — Revisor: ¿solo lectura o puede editar?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | El revisor tiene modo **Solo Lectura con opciones de Auditoría**: ver quién escribió qué, poner comentarios y aceptar/rechazar | RF-CONV-043, RF-CONV-044 |
| DEPT | El revisor **con permisos de edición puede modificar directamente** el bloque, con trazabilidad completa (creado por X, modificado por Y) | RF-DEPT-025, RF-DEPT-026 |

**Pregunta:** ¿El revisor puede editar el contenido del documento o solo comentar y aceptar/rechazar? Si puede editar, ¿todos los revisores pueden o es un permiso especial por rol? ¿Qué pasa con la trazabilidad si el revisor modifica un bloque "modificable" — se registra como cambio del revisor o del autor original?

**Respuesta provisional:** el revisor solo puede comentar documentos que revisa, los documentos solo se pueden editar si el documento esta en el estado draft no en el estado de revisión. El revisor no puede modificar el contenido, solo aceptar/rechazar o poner comentarios. 

---

### D-03 — ¿Quién puede crear plantillas?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | "Todos los usuarios con permisos" pueden crear plantillas, incluyendo profesores (con visibilidad personal) | RF-CONV-053 |
| DEPT | Solo **Jefe de Departamento, Jefe de Estudios y Dirección** crean plantillas. Profesores NO en el MVP. | RF-DEPT-004, RN-DEPT-008 |
| DEPT | Se mencionaron plantillas personales de profesores (ej. tutoriales) pero se dejó como decisión abierta: "cuanto menos puedan hacer, mejor" | RF-DEPT-006 |

**Pregunta:** ¿Los profesores pueden crear plantillas personales en el MVP o se deja para v2? Si no pueden, ¿pueden al menos clonar plantillas existentes para uso personal? ¿La pertenencia polimórfica (RF-CONV-025) incluye `personal` como nivel de visibilidad para profesores?

**Respuesta provisional:* Todos los usuarios pueden crear plantillas, pero solo los roles superiores (Jefe de Departamento, Jefe de Estudios y Dirección) pueden crear plantillas con visibilidad para otros (Tipo de Estudio, Estudio, Módulo, grupo). Los profesores solo pueden crear plantillas con visibilidad personal. 

---

### D-04 — Documentos compartidos vs Bloqueo colaborativo

| Fuente | Dice | IDs |
|--------|------|-----|
| CLI | Cuando un usuario edita un bloque, se **bloquea para el resto** con mensaje "Bloqueado por usuario X". Implica edición concurrente multi-usuario. | RF-CLI-006, RF-CLI-007 |
| DEPT | Decisión explícita: los documentos **NO se comparten** en MVP. Cada documento es de una persona particular. | RF-DEPT-014 |

**Pregunta:** Si los documentos no se comparten en el MVP, ¿para qué se necesita el bloqueo colaborativo (RF-CLI-006)? ¿El bloqueo aplica solo a **plantillas** (que sí podrían editarse por varios roles)? ¿O los documentos se compartirán en v2 y el bloqueo es también para v2? Si es así, ¿se implementa la infraestructura WebSocket desde el MVP o se difiere todo?

**Respuesta provisional:* el bloqueo colaborativo se implementa en documentos y plantillas desde el MVP. Si un usuario está editando un bloque, ese bloque se bloquea para el resto de usuarios con el mensaje "Bloqueado por usuario X". El usuario que lo está editando ve un mensaje de "Editando bloque X". Los documentos SI se comparten en el MVP.

---

### D-05 — ¿Las plantillas pasan por revisión?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Las plantillas tienen estados Borrador/Publicado (implica posible revisión o al menos un flujo de publicación) | RF-CONV-010 |
| DEPT | Las plantillas **NO se revisan**. Se crean, se publican directamente. Solo los documentos pasan por revisión. | RF-DEPT-011 |

**Pregunta:** ¿Las plantillas tienen un flujo Borrador → Publicado sin revisión intermedia (publicación directa por el creador)? ¿O simplemente se crean y ya están disponibles? Si no hay revisión, ¿hay al menos un paso de "publicar" explícito o son visibles inmediatamente?

**Respuesta provisional:** Tanto plantillas como documentos pueden pasar por revision si se les asigna un revisor. Si no se asigna revisor, se publican directamente.

---

### D-06 — Número de validadores: ¿1 o N?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Habla de "el revisor" en singular. Un único flujo Borrador → Revisión → Publicado. | RF-CONV-016, RF-CONV-017, RF-CONV-018 |
| DEPT | Las plantillas definen **0 a N validaciones** configurables, con validadores específicos y orden definido. Soporta cadenas de validación. | RF-DEPT-019, RF-DEPT-020, RF-DEPT-021 |

**Pregunta:** ¿El MVP soporta múltiples validadores (N) o solo uno? Si es N, ¿es configurable por plantilla o hay un valor por defecto? ¿El flujo de estados de la máquina de estados (Borrador → Revisión → Publicado) cambia si hay N validadores (ej. Borrador → Validación 1 → Validación 2 → Publicado) o se mantiene el mismo pero con N checks internos?

**Respuesta provisional:** el MVP soporta múltiples validadores (N) configurables por plantilla. 

---

## 2. Ambigüedades e Inconsistencias 🟡

### D-07 — Validación síncrona vs asíncrona: ¿qué implica para la UX?

| Fuente | IDs |
|--------|-----|
| DEPT | RF-DEPT-020 |

**Contexto:** Se definen dos tipos: síncrona (A → B → C obligatoriamente en orden) y asíncrona (todos validan cuando quieran). Pero no se define:

- ¿Cómo se configura esto en la plantilla? ¿Un toggle "Validación ordenada: Sí/No"?
- Si es síncrona, ¿el validador 2 ve el documento antes de que el validador 1 apruebe? ¿O ni siquiera le aparece?
- Si es asíncrona, ¿todos los validadores reciben notificación al mismo tiempo?
- ¿Puede haber un mix (primero 2 asíncronos, luego 1 síncrono final)?

**Respuesta provisional:** la validación síncrona implica que el validador 2 no puede ver ni validar el documento hasta que el validador 1 lo apruebe. En la validación asíncrona, todos los validadores reciben notificación al mismo tiempo y pueden validar en cualquier orden. La configuración se hace a nivel de plantilla con un toggle "Validación ordenada: Sí/No". No se contempla un mix de validaciones síncronas y asíncronas en el MVP.

---

### D-08 — Rechazo reinicia TODAS las validaciones: ¿siempre?

| Fuente | IDs |
|--------|-----|
| DEPT | RF-DEPT-023, RN-DEPT-004 |

**Contexto:** Se dijo explícitamente que si un validador rechaza, TODO se reinicia. Pero esto puede ser muy costoso si hay 3-4 validadores.

**Pregunta:** ¿Es siempre así o hay excepciones? ¿Qué pasa si el validador 3 de 3 rechaza por un error menor en un bloque? ¿Se podrían implementar "rechazos parciales" donde solo se invalida el bloque afectado? ¿O se mantiene la regla estricta de "vuelta a paso 0"?

**Respuesta provisional:** Se contempla la posibilidad de "rechazos parciales" en futuras versiones, pero en el MVP se mantiene la regla estricta de "vuelta a paso 0". Es decir, si cualquier validador rechaza, el documento vuelve a estado Borrador y todos los validadores deben validar de nuevo.

---

### D-09 — Editor: ¿una vista o dos secciones?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Editor tipo Notion monolítico (editor de bloques en una vista principal) | RF-CONV-046, DA-CONV-007 |
| CLI | Editor con **2 secciones**: índice de bloques (lateral) + editor del bloque seleccionado (principal) | RF-CLI-005 |
| DEPT | No menciona las 2 secciones, describe un editor BlockNote estándar con D&D | RF-DEPT-045 |

**Pregunta:** ¿El editor tiene un panel lateral con índice de bloques (tipo outline) o es un editor monolítico donde los bloques se apilan uno debajo de otro? ¿El "índice" del cliente se refiere a una ToC (Table of Contents) navegable o a una vista dividida (split view)?

**Respuesta provisional:** el editor tiene un panel lateral con índice de bloques (tipo outline) y un panel principal donde se edita el bloque seleccionado. El índice es navegable y permite seleccionar bloques para editarlos en el panel principal. Este diseño facilita la navegación en documentos largos con muchos bloques.

---

### D-10 — Orden de listados: ¿fecha de modificación o prioridad por plazo?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Búsqueda por filtros estáticos (tipo, estudio, módulo, estado). No define orden por defecto. | RF-CONV-038 |
| DEPT | Listados por **fecha de última modificación** (desc). Pero validaciones pendientes ordenadas por **prioridad = cercanía al plazo**. | RF-DEPT-037, RF-DEPT-030 |

**Pregunta:** ¿El criterio de orden por defecto es "última modificación" para todo y "cercanía al plazo" solo en la bandeja de validaciones? ¿Se puede cambiar el criterio de orden (ej. toggle entre "reciente" y "urgente")?

**Respuesta provisional:** el criterio de orden por defecto en los listados generales es "fecha de última modificación" en orden descendente. Sin embargo, en la bandeja de validaciones pendientes, el criterio de orden es "prioridad", calculada como la cercanía al plazo de entrega. Se implementan enlaces en la cabecera de la tabla para ordenar por todas las columnas relevantes, incluyendo fecha de modificación y prioridad.

---

### D-11 — Trazabilidad de autoría por bloque: ¿nivel de detalle?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Auditoría: ver quién escribió qué sección (modelo general) | RNF-CONV-002 |
| DEPT | Cada bloque guarda: **creado por** [usuario], **modificado por** [usuario] + **historial completo de modificaciones** | RF-DEPT-026 |

**Pregunta:** ¿Se necesita un historial completo de todas las modificaciones por bloque (tipo Git blame) o solo "última modificación por"? Un historial completo tiene implicaciones serias de almacenamiento en JSONB. ¿Se almacena en la misma tabla de contenido o en una tabla de auditoría separada?

**Respuesta provisional:** Se requiere un historial completo de todas las modificaciones por bloque para cumplir con los requisitos de auditoría. El historial se almacenará en una tabla de auditoría separada para evitar sobrecargar la tabla principal de contenido.

---

### D-12 — Scope del DMS: ¿solo programaciones o todo tipo de documentación?

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Se centra exclusivamente en programaciones didácticas | Todo el archivo |
| DESC | Proyecto: "Gestión de Programaciones Didácticas" | Todo el archivo |
| DEPT | Maya DMS es un gestor de calidad **general**. Programaciones son solo uno de varios tipos (calidad, reservas, etc.) | RF-DEPT-001, RF-DEPT-002 |

**Pregunta:** ¿El MVP solo implementa programaciones pero la arquitectura debe soportar múltiples tipos de documentación? ¿O el MVP ya incluye al menos un segundo tipo de documentación? Esto afecta al diseño del dashboard (RF-DEPT-031) y la navegación.

**Respuesta provisional:** La unica diferencia en el mvp es que en el sidebar solo aparecerá el enlace a programaciones que sera una vista que tendra sus propios documentos y plantillas, pero la arquitectura del sistema soporta múltiples tipos de documentación desde el inicio. En futuras versiones se podrán añadir mas enlaces para organizar la documentacion pero todos los tipos de documentos se podran añadir desde el inicio. La unica diferencia serra que en el sidebar solo aparecera el enlace a programaciones y en un futuro crearemos bloques especiales para añadirlos dentro de las plantillas o documentos que seran bloques de tipo "tabla rellnable".

---

### D-13 — Publicación automática vs manual

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Estado "Publicado" como estado final, sin detallar el mecanismo de transición | RF-CONV-018 |
| DEPT | La **última validación aprobada = publicación automática**, sin paso manual | RF-DEPT-022, RN-DEPT-005 |

**Pregunta:** ¿Confirmado que no hay un botón "Publicar" manual tras la última validación? ¿Hay algún caso donde alguien quiera aprobar pero no publicar inmediatamente (ej. embargo hasta cierta fecha)?

**Respuesta provisional:** la publicacion manual o automatica podria ser definida por la plantilla, pero por defecto se establece que la última validación aprobada implica publicación automática. Se contempla un embargo o fecha de publicación futura como una funcionalidad en futuras versiones.

---

## 3. Decisiones Pendientes 🟢

### D-14 — Plantillas bloqueadas por defecto

| Fuente | IDs |
|--------|-----|
| DEPT | DP-DEPT-006 |

**Se sugirió** que toda plantilla nueva tenga todos los bloques **bloqueados por defecto** y que el creador marque explícitamente cuáles son editables/modificables. No se cerró la decisión.

**Preguntas:** ¿Se confirma "bloqueado por defecto"? ¿O es "editable por defecto" y el creador bloquea los que quiera? La primera opción es más segura pero más trabajo para el creador.

**Respuesta provisional:** son siempre editables pudiendo añadirse como modificvables o bloqueados de forma manual. Se podria añadir un boton de seleccion multiple para marcar varios bloques a la vez como bloqueados o modificables, pero por defecto son editables.

---

### D-15 — Plazos de entrega en plantillas

| Fuente | IDs |
|--------|-----|
| DEPT | RF-DEPT-027, RF-DEPT-028, RF-DEPT-029 |

**Concepto nuevo** introducido solo en la reunión de departamento: las plantillas tienen fecha de plazo y los documentos la heredan. La prioridad se calcula automáticamente.

**Preguntas:** ¿Todo documento tiene plazo obligatorio o es opcional? ¿El plazo se puede modificar individualmente por documento o solo se hereda de la plantilla? ¿Qué pasa si una plantilla no tiene plazo — los documentos no tienen prioridad? ¿Quién define el plazo — el creador de la plantilla o un rol superior?

**Respuesta provisional:** Los plazos se definen a nivel de plantilla, pero son opcionales. Si una plantilla no tiene plazo, los documentos derivados tampoco tendrán prioridad asignada. El creador de la plantilla define el plazo

---

### D-16 — Wizard de 2+1 pasos para creación de plantillas

| Fuente | IDs |
|--------|-----|
| DEPT | RF-DEPT-038, RF-DEPT-039, RF-DEPT-040 |

Se propuso un flujo de creación de plantillas en 3 pasos: (1) crear contenido, (2) asignar propiedades a bloques, (3) vista previa. Los otros archivos hablan de un editor directo sin wizard.

**Preguntas:** ¿Se confirma el wizard? ¿Se puede editar una plantilla ya creada sin pasar por todo el wizard de nuevo? ¿El paso 2 aplica solo a plantillas nuevas o también a clonaciones/nuevas versiones?


**Respuesta provisional:** se pueden añadir los bloques en la izquierda y a la derecha se muestra para el bloque seleccionado de la izquierda las propiedades del bloque, como el estado (editable, modificable o bloqueado) y el plazo de entrega y el blocknote para añadir el contenido del bloque. El wizard se elimina y se sustituye por un editor directo con dos paneles, uno para el índice de bloques y otro para la edición del bloque seleccionado, donde se pueden configurar las propiedades de cada bloque de forma individual.

---

### D-17 — Markdown como opción complementaria al editor

| Fuente | IDs |
|--------|-----|
| DEPT | DA-DEPT-005 |

Se mencionó la posibilidad de que el editor soporte markdown como input alternativo. No se cerró.

**Pregunta:** ¿Se descarta definitivamente? BlockNote ya soporta markdown import/export parcial. ¿Se necesita un toggle "modo markdown" o simplemente que pegando markdown se interprete correctamente?

**Respuesta provisional:** se descarta la opción de un "modo markdown" separado, pero se implementa soporte de importación de markdown. Es decir, si el usuario pega texto con formato markdown en un bloque, el editor lo interpreta y lo convierte al formato de bloque correspondiente (ej. encabezados, listas, negritas). No se contempla un toggle para editar directamente en markdown, pero sí que el editor sea capaz de manejar contenido pegado en markdown de forma inteligente.

---

### D-18 — "Grupo" como nivel de visibilidad/pertenencia

| Fuente | Dice | IDs |
|--------|------|-----|
| CLI | Introduce "Grupo" como nivel de visibilidad además de Tipo/Estudio/Módulo/Personal | RF-CLI-001 |
| CONV | Pertenencia polimórfica: Tipo, Estudio, Módulo, Personal | RF-CONV-025 |
| DEPT | Menciona "grupo" en la lista de pertenencia polimórfica | RF-DEPT-007 |

**Pregunta:** ¿Qué es exactamente un "Grupo"? ¿Es un grupo de usuarios ad-hoc (como un "equipo")? ¿O se refiere a grupo de clase (alumnos)? ¿Quién crea los grupos? ¿Vienen del sistema externo de roles (FDW)?

**Respuesta provisional:** un "Grupo" es una entidad que representa un conjunto de usuarios, como un equipo de trabajo o un departamento. Los grupos pueden ser creados por usuarios con permisos adecuados (ej. Jefe de Estudios) y pueden ser gestionados dentro del sistema. La pertenencia a grupos se puede configurar para controlar la visibilidad de plantillas y documentos, permitiendo compartir contenido con grupos específicos de usuarios.

---

### D-19 — Granularidad de bloques en tablas

| Fuente | IDs |
|--------|-----|
| DEPT | DP-DEPT-003 |

Se debatió pero no se cerró: ¿cada celda de una tabla es un bloque independiente o la tabla entera es un bloque?

**Preguntas:** Si la tabla es un único bloque, ¿se puede bloquear/desbloquear a nivel de celda individual? Si cada celda es un bloque, ¿no explota la granularidad (100 celdas = 100 bloques)? ¿BlockNote soporta nativamente control a nivel de celda?

**Respuesta provisional:** no tiene relevancia, ahora se crea el bloque primero y con el editor se añade todo el texto, titulos, tablas, imagenes etc del bloque por lo que no habrá bloques dentro de bloques, cada bloque es independiente y se puede configurar su estado (editable, modificable o bloqueado) de forma individual, pero no se contempla la posibilidad de tener bloques anidados dentro de otros bloques. Si el usuario quiere añadir una tabla, lo hace dentro del bloque como parte del contenido, pero la tabla en sí no es un bloque separado.

---

### D-20 — Validación de bloques vacíos obligatorios

| Fuente | IDs |
|--------|-----|
| DEPT | RF-DEPT-047, DP-DEPT-002 |

Se identificó que antes de enviar a revisión, el sistema debe verificar que todos los bloques vacíos obligatorios han sido rellenados. Pero no se definió el mecanismo.

**Preguntas:** ¿La validación es en frontend (antes de enviar), backend (al recibir), o ambos? ¿Qué se considera "relleno" — cualquier texto, un mínimo de caracteres, o un contenido semántico válido? ¿Los bloques vacíos opcionales son posibles o todos los bloques vacíos de la plantilla son obligatorios?

**Respuesta provisional:** la validación de bloques vacíos obligatorios se realiza en ambos lados: en el frontend, al intentar enviar a revisión, se muestra un mensaje de error indicando qué bloques están vacíos y deben ser rellenados. En el backend, se realiza una validación adicional para asegurar que no se envíen documentos con bloques vacíos obligatorios, devolviendo un error si se detectan. Se considera "relleno" cualquier contenido que no sea solo espacios en blanco, y la plantilla puede definir qué bloques son obligatorios y cuáles opcionales.

---

### D-21 — Asignación de plantillas a usuarios

| Fuente | IDs |
|--------|-----|
| DEPT | RF-DEPT-010 |

Concepto nuevo: un usuario puede crear plantillas y asignarlas a otros usuarios. No aparece en otros archivos.

**Preguntas:** ¿Qué significa "asignar"? ¿Es "crear un documento desde esta plantilla y dárselo a otro usuario"? ¿O es "hacer que esta plantilla aparezca en el catálogo de otro usuario"? ¿La asignación genera una notificación? ¿Se puede revocar?

**Respuesta provisional:** la asignación de plantillas a usuarios significa que el creador de la plantilla puede seleccionar a qué usuarios o grupos de usuarios se les asigna esa plantilla, lo que hace que la plantilla aparezca en su catálogo de plantillas disponibles para crear documentos. 

Por otro lado estaria bien que se pudiesen generar documentos para otros usuarios. Por ejemplo la jefa de estudios crea un documento desde una plantilla en modo borrador, lo asigna a un profesor para que lo rellene y luego el profesor lo envía a revisión. En este caso, la asignación de la plantilla hace que el documento creado a partir de esa plantilla se asigne al profesor, quien recibe una notificación de que tiene un nuevo documento asignado para rellenar. La asignación se puede revocar o cambiar en cualquier momento por el creador de la plantilla o por un usuario con permisos adecuados.

---

### D-22 — Subdivisión interna de bloques en BlockNote

| Fuente | IDs |
|--------|-----|
| DEPT | DP-DEPT-005 |

Duda técnica no resuelta: "¿Si escribo un párrafo y sigo escribiendo, es un único bloque o varios?"

**Impacto:** Si cada Enter genera un bloque nuevo, una plantilla con texto informativo largo podría tener decenas de bloques. Esto afecta al wizard (paso 2), a la granularidad de bloqueo, y al rendimiento.

**Acción necesaria:** Prototipo técnico con BlockNote para verificar el comportamiento real del JSON generado.

**Respuesta provisional:** lo mismo que con tabla ya no es relevante, bloque sera un conjunto de informacion que se añada al bloque, si el usuario añade un parrafo y sigue escribiendo, todo ese contenido formara parte del mismo bloque, no se generan bloques nuevos por cada salto de linea o por cada parrafo. El bloque se considera una unidad de contenido que puede contener texto, tablas, imágenes, etc., pero no se subdivide automáticamente en bloques más pequeños a menos que el usuario lo configure manualmente.

---

## 4. Huecos Funcionales ⚪

### D-23 — Matriz RBAC completa

Ninguna fuente define la matriz completa de permisos por rol. Se mencionan 4 roles (RF-DEPT-049) y permisos genéricos, pero falta:

- ¿Qué puede hacer exactamente cada rol sobre plantillas?
- ¿Qué puede hacer exactamente cada rol sobre documentos?
- ¿Quién puede asignar validadores?
- ¿Quién puede publicar directamente sin validación?
- ¿Un Jefe de Estudios puede editar documentos de otro departamento?

**Referencia:** SH-DESC-001 identifica este hueco como "Falta la Matriz RBAC".

**RESPUESTA PROVISIONAL:** Por el momento no limitaremos los permisos con roles pero en el futturo si se implementaran

---

### D-24 — Infraestructura de colas para notificaciones

Se define que las notificaciones son event-driven (DA-CONV-011, RF-CONV-065) pero no se especifica:

- ¿Qué sistema de colas? ¿Redis, RabbitMQ, Laravel Queues con database driver?
- ¿Las notificaciones son in-app, email, o ambas?
- ¿Hay un centro de notificaciones en la UI?

**Referencia:** SH-DESC-002 identifica este hueco.


**Respuesta provisional:* existe un sistema de notificaciones que utiliza rabit por lo que el sistema debera enviar las notificaciones a traves de rabit, el sistema no muestra notificaciones esto se hace en otra parte, pero el sistema de notificaciones se encarga de enviar las notificaciones a los usuarios correspondientes, ya sea por email o in-app, dependiendo de la configuración del usuario.

---

### D-25 — Motor de generación PDF

Se confirma PDF asíncrono (RF-CONV-050, DA-CONV-012) pero no se define:

- ¿DomPDF, wkhtmltopdf, Puppeteer/Chromium headless, o WeasyPrint?
- ¿El usuario espera en la UI o recibe una notificación cuando el PDF está listo?
- ¿Se cachean los PDFs generados o se regeneran cada vez?

**Referencia:** SH-DESC-003 identifica este hueco.

**Respuesta provisional:** se implementa un motor de generación de PDF utilizando Puppeteer/Chromium headless para asegurar una alta fidelidad en la conversión de los documentos. El proceso de generación es asíncrono, por lo que el usuario no espera en la UI. En su lugar, recibe una notificación (in-app o por email) cuando el PDF está listo para descargar. Los PDFs generados se cachean durante un período de tiempo determinado para mejorar el rendimiento en caso de descargas repetidas, pero se regeneran si el documento original ha sido modificado desde la última generación del PDF.

---

### D-26 — Flujo de migración desde sistema legacy

Se menciona migración side-by-side con drag & drop (RF-CONV-057, RF-CONV-058, RF-CONV-059) pero no se detalla:

- ¿Qué formato tienen los datos legacy (Excel, Word, PDF, BD)?
- ¿La migración es un módulo dentro de Maya o una herramienta externa?
- ¿Se mantiene el sistema legacy en paralelo durante cuánto tiempo?
- ¿Hay mapeo de campos legacy → bloques de plantilla?

**RESPUESTA PROVISIONAL:** la migración se implementara en el futuro no es necesario por el moemento.


---

### D-27 — Destino de las notificaciones

Se habla de notificaciones basadas en eventos y ahora también basadas en plazos (RF-DEPT-029), pero:

- ¿Dónde se muestran? ¿Campana en header, email, ambos?
- ¿El usuario puede configurar sus preferencias de notificación?
- ¿Hay notificaciones push (WebSocket) o solo polling?

**Referencia:** SH-DESC-004 identifica este hueco.

La aplicacion no muestra notificaciones , esto se realizara desde otra herramienta

---

### D-28 — Comportamiento del bloque "Modificable" cuando el revisor lo ve

El estado "Modificable" (RF-CLI-009) implica que:

1. El autor puede editar el bloque pero no borrarlo
2. Si lo modifica, el revisor ve el texto original para comparar

Pero no se define:

- ¿El revisor ve un diff visual (tipo track changes) o dos versiones lado a lado?
- ¿El texto original se guarda automáticamente o es responsabilidad del snapshot?
- ¿El revisor puede "restaurar" el texto original?
- ¿El estado "Modificable" se aplica solo al contenido textual o también a formato/estructura?

**Respuesta provisional:** el revisor ve un diff visual que resalta las diferencias entre el texto original y el modificado, similar a la función de "track changes" en procesadores de texto. SI EL lo lee y no envia el docuemnto a borrador implica que mantendra los cambios. 

---

## 5. Ronda 2 — Dudas emergentes tras análisis de respuestas 🔄

> Las siguientes dudas surgen del análisis cruzado de las respuestas provisionales de la Ronda 1. Se detectan contradicciones entre respuestas, huecos nuevos y workflows no definidos.

### D-29 🔴 — Documentos compartidos en MVP: ¿qué implica exactamente?

La respuesta a D-04 revierte RF-DEPT-014 y confirma que los documentos **SÍ se comparten** en el MVP. Pero no se define:

- ¿Quién decide compartir? ¿El autor selecciona colaboradores manualmente o la visibilidad es automática por pertenencia polimórfica (estudio, módulo, grupo)?
- ¿Qué permisos tiene el usuario con quien se comparte: solo lectura, edición, o comentario?
- ¿Puede haber co-autoría (varios editores) o solo un autor principal + colaboradores de lectura?
- ¿Cómo se gestionan conflictos de edición — solo mediante el bloqueo colaborativo (D-04)?
- Si hay múltiples editores, ¿quién envía a revisión? ¿Cualquier editor o solo el autor principal?

**Impacto:** Afecta modelo de datos (`document_collaborators`), permisos, bloqueo colaborativo y flujo de revisión completo.

Respuesta provisional: los documentos se comparten en el MVP, el autor puede seleccionar a qué usuarios o grupos de usuarios se les asigna el documento, lo que hace que el documento aparezca en su bandeja de entrada para colaborar. Los usuarios con quienes se comparte el documento pueden tener permisos de edición o solo lectura, dependiendo de la configuración establecida por el autor. Puede haber co-autoría, permitiendo que varios usuarios editen el mismo documento, pero solo un autor principal es responsable de enviar el documento a revisión. El bloqueo colaborativo se utiliza para gestionar conflictos de edición, asegurando que solo un usuario pueda editar un bloque específico al mismo tiempo.

---

### D-30 🔴 — La app NO muestra notificaciones, pero ¿qué son las alertas del dashboard?

| Respuesta | Dice |
|-----------|------|
| D-27 | "La aplicación no muestra notificaciones, esto se realizará desde otra herramienta" |
| D-24 | "El sistema envía notificaciones a través de RabbitMQ, no muestra notificaciones" |
| RF-DEPT-032 | "Si el usuario tiene documentos pendientes de validar, aparece una tarjeta/mensaje destacado tipo alerta en el dashboard" |
| D-10 | "En la bandeja de validaciones pendientes, el criterio de orden es prioridad" |

**Pregunta:** ¿Las alertas/tarjetas del dashboard (validaciones pendientes, prioridad por plazo) son queries directas a BD que SÍ se implementan en la app? ¿O tampoco se implementan y las validaciones pendientes se gestionan desde la herramienta externa? Si el dashboard muestra "tienes 3 validaciones pendientes", eso es una funcionalidad in-app, no una notificación push. ¿Se confirma que el dashboard SÍ muestra esta información?

Respuesta provisional: el dashboard SÍ muestra información sobre validaciones pendientes, incluyendo una tarjeta o mensaje destacado que indica al usuario cuántas validaciones tiene pendientes y su prioridad basada en la cercanía al plazo de entrega. Esta funcionalidad se implementa dentro de la aplicación, permitiendo a los usuarios gestionar sus tareas de validación directamente desde el dashboard, aunque las notificaciones específicas sobre eventos (como asignaciones o aprobaciones) se gestionan a través de la herramienta externa.

---

### D-31 🔴 — Sin RBAC en MVP pero ISO 9001 exige Segregación de Funciones

| Respuesta | Dice |
|-----------|------|
| D-23 | "Por el momento no limitaremos los permisos con roles" |
| RNF-DESC-002 | ISO 9001 + RGPD (inmutabilidad, auditoría, SoD) |
| R-DESC-002 | SoD estricta: `user_id` creador ≠ `reviewer_id` aprobador |
| RN-DEPT-006 | El creador no puede auto-validar |

**Pregunta:** Sin RBAC, ¿cómo se garantiza la SoD? Posibles interpretaciones:
1. **Sin RBAC completo pero con regla SoD mínima:** No se limitan acciones por rol, pero `creator_id ≠ reviewer_id` se valida siempre. ¿Confirmado?
2. **Sin restricciones de ningún tipo:** Cualquiera puede hacer cualquier cosa, incluyendo aprobar su propio documento. ¿Esto violaría ISO 9001?
3. **Sin roles pero con permisos implícitos:** Todos pueden hacer todo excepto auto-aprobarse. ¿Es esta la intención?

**Recomendación:** Al menos implementar la validación `creator_id ≠ reviewer_id` aunque no haya RBAC completo. Es una línea de código que evita una violación normativa.

Respuesta provisional: se implementa una validación en el backend que asegura que el `creator_id` del documento no puede ser igual al `reviewer_id` que aprueba la validación. Esto garantiza la segregación de funciones (SoD) mínima requerida por ISO 9001, incluso sin un sistema RBAC completo. De esta manera, se cumple con los requisitos normativos sin limitar las acciones de los usuarios por roles específicos. Por el momento seran politicas referidas al usuario posteriormente en la V2 ya implementaremos roles para cada accion de la app, pero la validacion de que el creador no pueda auto-validarse se implementa desde el inicio para asegurar el cumplimiento normativo.

---

### D-32 🟡 — "Tabla rellenable" como bloque especial futuro: ¿es la misma tabla predefinida?

D-12 menciona que en un futuro se crearán "bloques de tipo tabla rellenable" para añadir dentro de plantillas/documentos.

| Fuente | Dice | IDs |
|--------|------|-----|
| CONV | Tablas predefinidas con celdas de cabecera bloqueadas (fondo gris + candado) + celdas vacías editables | RF-CONV-034, RF-CONV-035 |
| D-12 (respuesta) | "Bloques de tipo tabla rellenable" para futuras versiones | — |

**Pregunta:** ¿Son el mismo concepto? ¿O la "tabla rellenable" es algo más complejo (ej. tabla dinámica donde el docente puede añadir/quitar filas)? En el MVP, ¿las tablas simples del editor BlockNote (tabla estándar HTML) se soportan o se difieren todas las tablas a v2?

Respuesta provisional: Son tablas que a partir de datos de apis se rellenaran casi todas las casillas dejando libres solo algunas de ellas casillas para que el usuario las rellene, por ejemplo una tabla de alumnos con sus datos personales y solo la columna de "observaciones" quedaria vacia para que el docente pueda rellenarla, pero el resto de la tabla se rellenaria a partir de los datos que se obtengan de las apis, por lo que no se contempla la posibilidad de tablas dinámicas donde el usuario pueda añadir o quitar filas, sino que se trata de tablas predefinidas con un formato específico y ciertas celdas bloqueadas para mantener la integridad de los datos.

---

### D-33 🟢 — Publicación manual tras validación: ¿nuevo estado "Validado"?

D-13 establece que la plantilla define si la publicación es automática o manual. Si es manual:

- ¿Quién pulsa el botón "Publicar"? ¿El último validador, el autor, o un rol específico?
- ¿En qué estado queda el documento entre "todas las validaciones aprobadas" y "publicado"? ¿Se necesita un nuevo estado **"Validado"** o **"Aprobado"** en la máquina de estados?
- Si el estado intermedio existe, ¿quién tiene acceso al documento en ese estado?
- ¿Hay un plazo máximo para publicar tras la validación?

**Impacto:** Si se confirma, la máquina de estados pasa de 3 a 4 estados: Borrador → En Revisión → **Validado** → Publicado.

Respuesta provisional: Puede elegirse si se autopublica con la ultima validacion o si se crea el estado "Validado" y se requiere un paso manual de publicación. Si se opta por el estado "Validado", el documento quedaría en ese estado tras la última validación aprobada, y solo usuarios que lo creo podrían pulsar el botón "Publicar" para moverlo al estado "Publicado".

---

### D-34 🟡 — Flujo de delegación de documentos: workflow no documentado

La respuesta a D-21 introduce un flujo completamente nuevo no presente en ninguna fuente original:

> "La jefa de estudios crea un documento desde una plantilla en modo borrador, lo asigna a un profesor para que lo rellene y luego el profesor lo envía a revisión."

**Preguntas:**
- ¿El profesor puede rechazar la asignación?
- ¿Quién es el "autor" para efectos de SoD y trazabilidad — el creador del draft (jefa) o el profesor asignado?
- ¿El superior puede seguir editando el documento después de asignarlo?
- ¿Es funcionalidad MVP o se difiere a v2?
- ¿Se genera una notificación de asignación (y si la app no muestra notificaciones, cómo se entera el profesor)?

**Impacto:** Si es MVP, requiere campo `assigned_to` en documentos, UI de asignación, y lógica de permisos de edición compartida.

Respuesta provisional: El proferor no puede rechazar la asignación, el autor para efectos de SoD y trazabilidad es el profesor asignado, el superior no puede seguir editando el documento después de asignarlo, esta funcionalidad se implementa en el MVP, y se genera una notificación de asignación a través de la herramienta externa para informar al profesor que tiene un nuevo documento asignado para rellenar. Ademas le aparecera en sus borradores el documento asignado para que pueda empezar a rellenarlo.

---

### D-35 🟡 — ¿Cada bloque tiene su propio plazo de entrega?

| Respuesta | Dice |
|-----------|------|
| D-15 | "Los plazos se definen a nivel de **plantilla**, pero son opcionales" |
| D-16 | "El panel derecho muestra propiedades del bloque como **el estado** (editable, modificable o bloqueado) **y el plazo de entrega** y el BlockNote para el contenido" |

**Pregunta:** D-16 sugiere que **cada bloque** tiene su propio plazo de entrega. Pero D-15 dice que el plazo es a nivel de **plantilla**. ¿Qué es correcto?

- **Opción A:** Plazo solo a nivel de plantilla (y se hereda a documentos). Los bloques no tienen plazo individual.
- **Opción B:** Cada bloque tiene su propio plazo individual (ej. "Rellena la sección de objetivos antes del 15 de octubre"). Esto añade complejidad significativa.

Adicionalmente: ¿el bloque tiene un campo `obligatorio: true/false` como se confirma en D-20?

Respuesta provisional: OPCION A

---

### D-36 🟢 — Infraestructura real-time para bloqueo colaborativo

D-04 confirma bloqueo colaborativo en MVP para documentos y plantillas. Esto requiere comunicación en tiempo real.

**Preguntas técnicas:**
- ¿Tecnología: WebSocket (Laravel Reverb/Echo + Pusher), Server-Sent Events (SSE), o polling HTTP?
- ¿Timeout de lock? Si un usuario cierra el navegador sin guardar, ¿cuánto tiempo queda bloqueado el bloque para otros?
- ¿Heartbeat para detectar desconexiones?
- ¿Se muestra presencia (qué usuarios están viendo el documento)?
- ¿El lock es solo del bloque que se está editando o del documento completo?

**Impacto:** WebSocket con Laravel Reverb es significativo en infraestructura. Afecta despliegue, escalabilidad y costes.

Respuesta provisional: se implementa bloqueo colaborativo a nivel de bloque utilizando WebSockets con Laravel Reverb/Echo y Pusher. El lock se aplica solo al bloque que se está editando, permitiendo que otros usuarios puedan editar otros bloques del mismo documento simultáneamente. Se establece un timeout de lock de 5 minutos para manejar casos donde un usuario cierra el navegador sin guardar, y se implementa un heartbeat para detectar desconexiones y liberar locks de forma automática. Además, se muestra presencia en la UI indicando qué usuarios están viendo el documento en tiempo real.

Respuesta a analizar profundamente si es la mejor opcion para el MVP, dado que la infraestructura de WebSockets puede ser compleja y costosa. Alternativamente, se podría considerar un sistema de locking más simple basado en polling HTTP para el MVP, con la intención de implementar WebSockets en una versión futura.

---

### D-37 🟡 — Revisión de plantillas: ¿usa el mismo sistema de N-validadores?

D-05 dice que plantillas pueden tener revisión si se asigna revisor. Pero:

- ¿Se usa el mismo sistema de 0-N validadores (sync/async) que para documentos?
- ¿Quién configura los validadores de una plantilla — la propia plantilla o un ajuste del sistema?
- Si una plantilla está en revisión, ¿se pueden crear documentos desde su **última versión publicada** (anterior)?
- ¿Se genera nueva versión al publicar la plantilla tras revisión (v1.0 → v2.0)?

Respuesta provisional: Sí, se utiliza el mismo sistema de 0-N validadores para plantillas que para documentos, permitiendo tanto validación síncrona como asíncrona. Los validadores de una plantilla son configurados por el creador de la plantilla durante su creación o edición, pudiendo seleccionar a los usuarios que actuarán como validadores. Si una plantilla está en revisión, se pueden seguir creando documentos a partir de su última versión publicada, pero no a partir de la versión en revisión hasta que esta sea aprobada y publicada. Al publicar una nueva versión de la plantilla tras revisión (por ejemplo, v1.0 → v2.0), se genera automáticamente una nueva versión en el sistema, manteniendo un historial de versiones para trazabilidad y auditoría.

---

### D-38 🟢 — CRUD de "Grupo" en la UI

D-18 dice que los grupos se gestionan dentro del sistema. Pero:

- ¿Hay una interfaz CRUD para crear/editar/eliminar grupos?
- ¿Quién gestiona los grupos — cualquier usuario o solo ciertos roles (problema con D-31: sin RBAC)?
- ¿Se sincronizan con el sistema externo (FDW) o son 100% internos de Maya PD?
- ¿Un usuario puede pertenecer a múltiples grupos?
- ¿Un grupo puede contener a otros grupos (anidamiento)?


Respuesta provisional: Sí, se implementa una interfaz CRUD para gestionar grupos dentro de la aplicación. Cualquier usuario con permisos adecuados (por ejemplo, Jefe de Estudios) puede crear, editar y eliminar grupos. Los grupos son internos de Maya PD y no se sincronizan automáticamente con el sistema externo (FDW), aunque se podría considerar esta funcionalidad para futuras versiones. Un usuario puede pertenecer a múltiples grupos, permitiendo una flexibilidad en la gestión de permisos y visibilidad. Sin embargo, no se contempla la posibilidad de anidamiento de grupos; es decir, un grupo no puede contener a otros grupos, sino que solo puede contener usuarios individuales.

---

### D-39 🟡 — Migración eliminada: ¿qué ocurre cuando la plantilla cambia de versión?

D-26 dice que la migración se difiere al futuro. Pero los requisitos originales definen un escenario crítico:

| Fuente | IDs | Escenario |
|--------|-----|-----------|
| CONV | RF-CONV-026 | Si un borrador está en curso y la plantilla publica v2.0, el borrador se bloquea y exige migración |
| CONV | RF-CONV-027 | La migración inyecta secciones nuevas de forma aditiva |
| CONV | RF-CONV-029 | Banner informativo al usuario |

**Pregunta:** Si la migración se elimina del MVP, ¿qué ocurre en este escenario?
- **Opción A:** El borrador sigue funcionando con la v1 de la plantilla (ignora la v2). Riesgo: documentos basados en plantillas obsoletas.
- **Opción B:** El borrador se bloquea pero no hay mecanismo de migración. Riesgo: el profesor pierde su trabajo.
- **Opción C:** No se permite crear nueva versión de plantilla si hay borradores activos. Riesgo: la plantilla queda bloqueada indefinidamente.
- ¿Cuál es el comportamiento esperado sin migración?

Respuesta provisional: si aparece una nueva plantilla el documento no se bloquea, el documento sigue funcionando con la versión de plantilla con la que se creó, ignorando las nuevas versiones de la plantilla. Se muestra un banner informativo al usuario indicando que hay una nueva versión de la plantilla disponible, pero no se obliga a migrar ni se bloquea el documento. El usuario puede seguir editando su documento basado en la versión original de la plantilla sin perder su trabajo, aunque se recomienda revisar las nuevas versiones de la plantilla para aprovechar mejoras o cambios futuros.

---

## Resumen Ejecutivo

### Ronda 1 (resuelta)

| Categoría | Cantidad | Severidad |
|-----------|----------|-----------|
| Contradicciones directas | 6 | 🔴 |
| Ambigüedades/Inconsistencias | 7 | 🟡 |
| Decisiones pendientes | 9 | 🟢 |
| Huecos funcionales | 6 | ⚪ |
| **Subtotal Ronda 1** | **28** | — |

### Ronda 2 (pendiente de respuesta)

| Categoría | Cantidad | Severidad |
|-----------|----------|-----------|
| Contradicciones directas (nuevas) | 3 | 🔴 (D-29, D-30, D-31) |
| Ambigüedades/Inconsistencias (nuevas) | 5 | 🟡 (D-32, D-34, D-35, D-37, D-39) |
| Decisiones pendientes (nuevas) | 3 | 🟢 (D-33, D-36, D-38) |
| **Subtotal Ronda 2** | **11** | — |
| **TOTAL ACUMULADO** | **39** | — |

### Top 5 — Decisiones más urgentes (Ronda 2)

1. **D-29 + D-31** — Documentos compartidos + Sin RBAC. Si todos pueden editar todo y no hay restricción de roles, se viola ISO 9001 (SoD). **Crítico.**
2. **D-30** — ¿El dashboard muestra bandeja de validaciones o todo se delega a herramienta externa? Afecta a si existe UX de revisión in-app.
3. **D-34** — Flujo de delegación (superior crea → asigna a profesor). Workflow nuevo no documentado. Si es MVP, impacta modelo de datos y UX significativamente.
4. **D-39** — Sin migración, ¿qué ocurre cuando una plantilla sube de versión con borradores activos? Posible pérdida de datos.
5. **D-35** — ¿Plazo por bloque o por plantilla? Afecta al modelo de datos del editor.

### Requisitos impactados por respuestas de Ronda 1

| Acción | IDs afectados | Respuesta origen |
|--------|--------------|-----------------|
| **INVALIDADO** | RF-DEPT-014 (docs no compartidos) | D-04 |
| **INVALIDADO** | RF-DEPT-025 (revisor edita) | D-02 |
| **INVALIDADO** | RF-DEPT-011 (plantillas no se revisan) | D-05 |
| **ELIMINADO** | RF-DEPT-038, RF-DEPT-039, RF-DEPT-040 (wizard) | D-16 |
| **INVALIDADO** | DA-DEPT-004 (cada Enter = bloque) | D-22 |
| **MODIFICADO** | RN-DEPT-008 → todos crean plantillas, profesores solo personal | D-03 |
| **MODIFICADO** | DA-DEPT-005 → solo paste-and-interpret markdown | D-17 |
| **MODIFICADO** | RF-CONV-020 → revisión ahora opcional (sin revisor → publicación directa) | D-05 |
| **MODIFICADO** | RF-DEPT-022 → publicación auto O manual configurable por plantilla | D-13 |
| **MODIFICADO** | RF-DEPT-004 → profesores SÍ crean (visibilidad personal) | D-03 |
| **RESUELTO** | SH-DESC-002 → RabbitMQ como cola | D-24 |
| **RESUELTO** | SH-DESC-003 → Puppeteer/Chromium headless para PDF | D-25 |
| **RESUELTO** | SH-DESC-004 → App solo emite eventos, no muestra notificaciones | D-24/D-27 |
