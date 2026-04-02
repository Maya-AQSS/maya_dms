# Requisitos Extraídos — Notas de Reunión con Cliente

> **Fuente:** `docs/src/0_reunion_cliente.md`
> **Fecha del documento:** No especificada (pre-discovery)
> **Autor:** Cliente (Product Owner)
> **Fecha de extracción:** 2026-03-30

> **⚠️ NOTA IMPORTANTE:** Este archivo es corto (~15 líneas) pero introduce varios conceptos **exclusivos** que NO aparecen en los otros archivos fuente. Requiere atención especial en la fase de cruce.

---

## Requisitos Funcionales (RF)

### Visibilidad y Pertenencia

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-CLI-001 | Niveles de visibilidad ampliados | Las plantillas y documentos los crea un usuario para: "Tipo de estudio", "Estudio", **"Grupo"**, "Módulo", o **Privado (usuario)**. | ⚠️ **"Grupo" es nuevo.** En la conversación solo se mencionan Tipo de Estudio, Estudio, Módulo y Personal. **[✅ CONFIRMADO por D-18, RESUELTO por D-38]** "Grupo" se gestiona dentro de la app con **CRUD propio** (RF-DEPT-075). Grupos 100% internos (no FDW). Un usuario puede pertenecer a múltiples grupos. Sin anidamiento. Solo usuarios con permisos gestionan grupos. |
| RF-CLI-002 | Visibilidad según estado | Mientras es privado, solo visible para el creador. Una vez público, visible para quienes se comparta. | Parcialmente nuevo: el detalle de "para quienes se comparta" no está en otros archivos. **[NOTA D-14]** Los documentos SÍ se comparten en MVP (modificación respecto a RF-DEPT-014 original). |

### Editor

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-CLI-003 | Editor con dos secciones | El editor tendrá primero una sección donde se establecen los bloques de contenido (índice/resumen de bloques), y luego una sección donde se edita el bloque seleccionado. | ⚠️ **Nuevo.** ~~La conversación y descripción hablan de un editor tipo Notion directo, no de dos paneles.~~ **[✅ CONFIRMADO por D-07]** El wizard se elimina y se adopta el diseño de 2 paneles: índice a la izquierda, propiedades/contenido a la derecha. Este requisito es ahora el **diseño canónico**. |

### Bloqueo Colaborativo

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-CLI-004 | Bloqueo de bloque por edición concurrente | Cuando alguien está editando un bloque, se queda **bloqueado para el resto de usuarios**. | ⚠️ **Nuevo.** ~~La conversación NO menciona edición concurrente ni bloqueo colaborativo.~~ **[✅ CONFIRMADO por D-04, RESUELTO por D-36]** Bloqueo colaborativo confirmado para MVP. Tecnología: **WebSocket con Laravel Reverb/Echo + Pusher**. Lock a nivel de bloque (no documento). Timeout: **5 min**. Heartbeat para detectar desconexiones. Se muestra **presencia** (quién ve el documento). ⚠️ Pendiente análisis de si polling HTTP es más viable para MVP. |
| RF-CLI-005 | Mensaje de bloqueo para otros usuarios | Los demás usuarios ven: "Bloqueado por usuario X". | ⚠️ **[✅ CONFIRMADO por D-04]** Véase RF-DEPT-061. |
| RF-CLI-006 | Mensaje de edición para el editor | El usuario que está editando ve: "Editando bloque X". | ⚠️ **[✅ CONFIRMADO por D-04]** Véase RF-DEPT-062. |

### Almacenamiento

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-CLI-007 | Contenido como JSON de BlockNote | Cada bloque contendrá una estructura JSON generada por BlockNote, guardada en BD como bloque de contenido con su estructura JSON. | Confirma DA-CONV-007 y RF-DESC-020. |

### Triple Estado de Bloque (⚠️ CONCEPTO CLAVE EXCLUSIVO)

| ID | Requisito | Detalle | ¿Exclusivo de esta fuente? |
|----|-----------|---------|---------------------------|
| RF-CLI-008 | Bloque Editable | El bloque se puede **editar, modificar y eliminar** sin restricciones. | ⚠️ ~~Nuevo como estado diferenciado.~~ **[✅ MODELO CANÓNICO por D-01]** Confirmado como estado por defecto de todo bloque al crear. |
| RF-CLI-009 | Bloque Modificable | El bloque se puede **modificar** pero avisará al revisor con un mensaje del **texto inicial** para que el revisor pueda comparar el original con la modificación. **No se puede eliminar.** | ⚠️ **[✅ CONFIRMADO por D-01]** Estado intermedio confirmado. Diff visual con texto original (RF-DEPT-056). |
| RF-CLI-010 | Bloque Bloqueado | El bloque **no se puede modificar** en absoluto. | Consistente con RF-CONV-003 y RF-DESC-005. **[✅ CONFIRMADO por D-01]** |
| RF-CLI-011 | Distinción explícita Editable vs Modificable | El cliente enfatiza que hay que distinguir claramente entre "Editable" (sin restricciones) y "Modificable" (con aviso al revisor + texto original). | ⚠️ **[✅ CONFIRMADO por D-01]** Este matiz es ahora el modelo canónico del proyecto. Los 3 estados se encuentran consolidados en RF-DESC-005, RF-CONV-003 y RF-DEPT-055/056. |

---

## Decisiones Arquitectónicas (DA)

| ID | Decisión | Detalle |
|----|----------|---------|
| DA-CLI-001 | BlockNote como editor | Coincide con DA-CONV-007. Confirma la elección de BlockNote. |

---

## Reglas de Negocio (RN)

| ID | Regla | Detalle |
|----|-------|---------|
| RN-CLI-001 | Bloque Modificable debe preservar texto original | Al modificar un bloque "Modificable", el sistema guarda el texto inicial y lo muestra al revisor junto con la versión modificada. |
| RN-CLI-002 | Bloque Modificable no es eliminable | A diferencia del Editable (CRUD completo), el Modificable solo permite modificación, no eliminación. |

---

## Resumen de Conceptos Exclusivos de esta Fuente

| # | Concepto | Impacto en el diseño |
|---|---------|---------------------|
| 1 | **"Grupo" como nivel de visibilidad** | Requiere ampliar la relación polimórfica de plantillas para soportar una entidad "Grupo" adicional. |
| 2 | **Editor con 2 secciones (índice + edición)** | ~~Cambia el diseño del editor de un canvas único (tipo Notion) a un layout de dos paneles.~~ **[✅ CONFIRMADO por D-07]** Ahora es el diseño canónico (RF-DEPT-055). |
| 3 | **Bloqueo colaborativo por bloque** | Requiere mecanismo de locking en tiempo real ~~(WebSockets o polling)~~ no contemplado en otros archivos. **[✅ CONFIRMADO por D-04, RESUELTO por D-36]** MVP incluirá locking vía WebSocket Laravel Reverb/Echo + Pusher. Lock por bloque, timeout 5min, heartbeat, presencia. |
| 4 | **Triple estado de bloque (Editable/Modificable/Bloqueado)** | Impacta modelo de datos, lógica de backend y UI del editor. Requiere almacenar texto original para bloques Modificables. |
