# Documento Maestro de Entrada (Contexto Fuente)

## 0. Datos Operativos del Proyecto (Obligatorio)

| Campo | Valor | Detalle / Contexto Adicional |
| --- | --- | --- |
| **Nombre del proyecto** | Sistema Avanzado de Gestión de Programaciones Didácticas (Maya PD) | Nombre comercial y funcional que verán los usuarios finales en el dashboard. |
| **Codigo interno (si existe)** | MAYA-PD-ISO9001 | Nomenclatura utilizada para repositorios, prefijos de tickets en Jira/GitHub y logs de servidor. |
| **Fecha ultima actualizacion** | 2026-03-25 | Marca de tiempo del cierre de la Fase de Discovery y validación arquitectónica. |
| **Responsable funcional** | Equipo Directivo / Coordinador de Calidad ISO 9001 | Rol encargado de validar que las reglas de negocio, los estados de revisión y las exportaciones PDF cumplen con las exigencias legales e inspecciones educativas. |
| **Responsable tecnico** | Arquitecto Full-Stack Senior / Especialista en Ciberseguridad | Rol encargado de velar por el rendimiento (Laravel/React), la integridad de datos (PostgreSQL FDW, UUIDs, JSONB) y la seguridad (Zero Trust, RBAC, Prevención IDOR). |
| **Estado del discovery** | Listo para extracción IA (Nivel de detalle Extremo) | El contexto fuente ha sido debatido, refutado y consolidado. Las IAs de las siguientes fases deben tomar esto como la "Única Fuente de Verdad" (Single Source of Truth). |
| **Nivel de Criticidad (Tier)** | Tier 1 (Business Critical) | El sistema maneja documentación oficial. Una caída del sistema en periodo de entrega de programaciones o una pérdida de datos (Data Loss) tiene impacto legal y administrativo severo. |
| **Normativas de Referencia** | ISO 9001, RGPD (GDPR), Leyes Educativas vigentes (LOMLOE, etc.) | La arquitectura debe soportar inmutabilidad de datos, auditoría de accesos (quién, cuándo, qué) y cifrado/protección de datos personales de los docentes. |
| **Entornos de Despliegue** | Local, Staging (Pre-producción), Producción | El desarrollo requerirá integración continua (CI/CD). La base de datos de Staging deberá tener un volcado anonimizado o estructura FDW replicada para pruebas sin tocar datos reales corporativos. |
| **Idiomas Soportados** | Español (Base) | Preparado a nivel de frontend (React - i18n) y backend (Laravel lang) por si la interfaz requiere lenguas cooficiales en el futuro, aunque inicialmente el contenido es en español. |

-----

## 1. Contexto GitHub (Obligatorio para Fase 6)

| Campo | Valor | Detalle / Contexto Adicional |
| --- | --- | --- |
| **URL del repositorio principal** | `https://github.com/Maya-AQSS/maya-dms` | Repositorio central. Se asume una estructura de Monorepo (Frontend y Backend en el mismo repositorio bajo directorios separados) o que este es el repositorio principal que orquestará el proyecto. |
| **Repositorio (Formato CLI)** | `Maya-AQSS/maya-dms` | Identificador estricto (`OWNER/REPO`) que utilizarán los scripts y la herramienta de línea de comandos `gh cli` para realizar llamadas a la API de GitHub REST/GraphQL. |
| **Organizacion GitHub** | `Maya-AQSS` | Entidad propietaria. Relevante para la configuración de *GitHub Actions*, *Secrets* de organización (ej. credenciales de la BD externa FDW) y gestión de permisos de los desarrolladores (RBAC de GitHub). |
| **GitHub Project Number** | `15` | ID numérico del tablero de gestión de proyecto. Todos los tickets generados deben inyectarse automáticamente aquí. |
| **URL Vista Tablero Kanban** | `https://github.com/orgs/Maya-AQSS/projects/15/views/1` | Enlace directo a la vista principal de trabajo. La IA estructurará las *Epics* y *Features* para que encajen en las columnas de este proyecto (ej. *Todo, In Progress, Review, Done*). |
| **Publicar milestones por epica** | `Si` | **Decisión Arquitectónica:** Cada componente principal del sistema (ej. *Motor de Snapshots*, *Editor JSONB*, *Integración FDW*) se creará como un `Milestone` en GitHub. Los issues (tareas) se agruparán bajo estos milestones para medir el progreso exacto del MVP. |
| **Vincular dependencias nativas** | `Si` | **Flujo de Trabajo:** Las historias de usuario de React (Frontend) tendrán una dependencia estricta de bloqueo (`blocked by`) sobre los endpoints de Laravel (Backend). La IA debe redactar las tareas indicando qué API REST debe existir antes de que el frontend empiece a pintar la pantalla. |

### 1.1 Prerequisitos Técnicos y Campos Customizados (Checklist DevOps)

Para que la Fase 6 (volcado automático a GitHub) funcione sin intervención manual, el repositorio y el proyecto deben tener configurado lo siguiente:

* **Autenticación CLI:** El entorno que ejecute la IA o el volcado debe tener `gh CLI` autenticado con un *Personal Access Token* (PAT) que tenga permisos de `repo` y `project`.
* **Procesador JSON (`jq`):** Herramienta instalada en el entorno para parsear la respuesta estructurada de la IA antes de inyectarla en GitHub.
* **Campos Requeridos en el Project (V15):** El tablero de GitHub debe tener habilitados (o se deben crear) los siguientes *Custom Fields* para recibir la metadata de la IA:
  * `Priority` (Single select): `Must` (Obligatorio para el MVP ISO 9001), `Should` (Importante pero no bloqueante), `Could` (Mejoras futuras, ej. Full-Text Search).
  * `Component` (Single select): `Frontend (React)`, `Backend (Laravel)`, `Database`, `DevOps/CI`.
  * `Estimation` o `Size`: Para que la IA asigne un peso relativo (Puntos de historia o tallas T-shirt) a la complejidad de cada Feature.

-----

## 2. Inventario de Fuentes de Contexto (Obligatorio)

Anotar todas las fuentes desde las que la IA debe extraer requisitos. Se especifica el nivel de prioridad y el tipo de extracción esperada para garantizar que no se pierdan los matices arquitectónicos.

| ID | Tipo de fuente | Fecha | Autor/Origen | Estado | Ubicacion o referencia | Detalle Extendido / Qué debe extraer la IA de aquí |
| --- | --- | --- | --- | --- | --- | --- |
| **SRC-01** | Entrevista / Sesión de Discovery Arquitectónico | 2026-03-25 | Cliente (Product Owner) y Arquitecto IA | Procesada (Fase 1 y 2) | Historial completo de la conversación del chat actual. | **Prioridad Crítica (Fuente de la Verdad).** De aquí la IA debe extraer todas las reglas de negocio base: El ciclo de vida del documento (`Borrador` -> `Revisión` -> `Publicado`), el sistema de versionado mediante *Snapshots* en BD, la estructura de la jerarquía educativa (Tipos -> Estudios -> Módulos) y la UX de migración *Side-by-side* con Drag & Drop. |
| **SRC-02** | Definición de Arquitectura Técnica (Anexo a SRC-01) | 2026-03-25 | Arquitecto Full-Stack | Procesada | Historial del chat (Decisiones Técnicas) | **Prioridad Crítica (Restricciones).** La IA debe extraer las imposiciones de infraestructura: Uso exclusivo de PostgreSQL con columnas `JSON/JSONB` para los bloques, conexión FDW (Foreign Data Wrapper) en modo solo lectura para usuarios externos, validación de token JWT (Zero Trust), y uso de Jobs/Queues para la exportación de PDFs y notificaciones encoladas. |
| **SRC-03** | Directrices de Ciberseguridad e ISO 9001 | 2026-03-25 | Especialista Ciberseguridad / ISO | Procesada | Historial del chat (Filtros de Seguridad) | **Prioridad Alta (Compliance).** La IA debe extraer las reglas de auditoría: Comentarios por UUID que hacen *Soft Delete*, inmutabilidad de los registros publicados (Append-Only), URLs firmadas temporales para archivos privados, y el estampado de marcas de agua/QR/Hash en los PDFs finales para evitar falsificaciones físicas. |
| **SRC-04** | Enlace Externo (Tablero Kanban) | 2026-03-25 | Equipo Maya-AQSS | Pendiente de Inyección | [GitHub Project 15](https://github.com/orgs/Maya-AQSS/projects/15/views/1) | **Destino Operativo.** La IA utilizará esta referencia no para leer requisitos, sino para conocer la estructura de destino donde deberá mapear las *Epics* y *User Stories* generadas (asegurando compatibilidad con los campos customizados del tablero). |
| **SRC-05** | Enlace Externo (Repositorio) | 2026-03-25 | Equipo Maya-AQSS | Pendiente de Inyección | [Repo Maya DMS](https://github.com/Maya-AQSS/maya-dms.git) | **Destino de Código.** Referencia para la orquestación. La IA asume que este es el repositorio donde vivirán tanto el frontend en React como el backend en Laravel, estructurando las tareas (`issues`) en base a la separación lógica de este monorepo o repositorios vinculados. |

### 2.1 Directrices de Resolución de Conflictos para la IA

Para asegurar la coherencia durante la Fase de extracción de requisitos (Fase 1 - Epics y Features), la IA debe aplicar las siguientes reglas si encuentra información contradictoria en las fuentes:

1. **La Arquitectura manda sobre la Funcionalidad:** Si una idea funcional entra en conflicto con el rendimiento o la seguridad de la base de datos (ej. "hacer búsquedas de texto libre en todos los JSON en tiempo real"), la IA debe priorizar la restricción técnica acordada en SRC-02 y SRC-03 (usar filtros estáticos para prevenir ataques DoS).
2. **El cumplimiento ISO 9001 es innegociable:** Cualquier funcionalidad que permita borrar el historial de un documento, alterar una versión publicada sin dejar rastro, o que el creador apruebe su propio documento, debe ser descartada y reemplazada por la regla de auditoría estricta (SRC-03).
3. **UX Poka-yoke:** La IA no debe generar historias de usuario que incluyan "formularios de configuración complejos" para los docentes. Todo debe extraerse bajo la premisa Poka-yoke acordada en SRC-01 (creación contextual de 1 clic, interfaces limpias tipo Notion/BlockNote).

¡Perfecto! Llegamos al corazón del documento: el **Bloque 3**. Aquí es donde volcamos toda la "materia prima" de nuestra sesión de *brainstorming*. Este bloque es denso intencionadamente, porque de aquí la Inteligencia Artificial extraerá las historias de usuario (User Stories), las tareas técnicas (Technical Chores) y los criterios de aceptación.

Aquí tienes el Bloque 3 expandido al máximo nivel de detalle técnico y de negocio:

---

## 3. Contexto Bruto del Cliente (Obligatorio)

Pegar aquí contenido literal o resumido fiel del cliente. Toda la información a continuación emana de la sesión de Discovery de Arquitectura y Negocio realizada el 25-03-2026.

### 3.1 Transcripciones / Conversaciones (Reglas de Negocio y Dominio)

* **Propósito del Sistema:** Desarrollo de "Maya PD", una aplicación web para la creación, gestión y auditoría de Programaciones Didácticas de un centro educativo, garantizando el cumplimiento estricto de la normativa de calidad ISO 9001.
* **Entidades Core:**
  * **Plantillas (Templates):** Son los "moldes" normativos. Están compuestas por bloques o secciones. Pueden tener versiones (v1.0, v2.0). Se pueden crear, clonar, editar y eliminar. Tienen jerarquía de visibilidad (Nivel Global/Centro, Nivel Departamento/Estudio, Nivel Personal).
  * **Documentos (Programaciones):** Son las instancias creadas por los docentes a partir de una Plantilla para un módulo específico y un año escolar.
* **Anatomía de los Bloques (Secciones):**
  * Cada bloque almacena información (descripción, contenido) y su fecha de creación.
  * **Estados del bloque en Plantilla:** ~~Abiertos (editables libremente) o Bloqueados (texto normativo fijo).~~ **[ACTUALIZADO post-Discovery]** 3 estados confirmados: **Editable** (sin restricciones, CRUD completo — estado por defecto), **Modificable** (editable pero no borrable; alerta al revisor con diff visual del texto original), **Bloqueado** (no modificable). El creador de la plantilla define el estado de cada bloque en el panel de propiedades del editor de 2 paneles.
  * **Comportamiento en Documento:** Si un docente desbloquea un bloque bloqueado para adaptarlo, el sistema marca automáticamente el documento como "Requiere Revisión" por parte de jefatura/coordinación.
  * En un documento, el docente puede instanciar bloques vacíos requeridos por la plantilla (ej. añadir *n* filas a una tabla de "Criterios de Evaluación" o añadir bloques customizados predefinidos). Cada bloque en la plantilla tiene un **flag obligatorio/opcional** que determina si el docente debe rellenarlo antes de enviar a revisión.
* **Ciclo de Vida del Documento (Máquina de Estados):**
  * `Borrador` (Draft): Editable por el autor (y co-autores con permiso de edición).
  * `En Revisión` (In Review): Bloqueado para el autor. El revisor solo puede **comentar, aceptar o rechazar** — no puede editar contenido.
  * `Validado` (Validated): **[NUEVO estado — opcional]** Si la plantilla define publicación manual, el documento pasa a este estado tras la última aprobación. Solo el autor principal puede pulsar "Publicar". Si la plantilla define publicación automática (default), este estado se omite.
  * `Publicado` (Published): Versión oficial, inmutable y vigente. Al publicarse una nueva versión, la anterior se conserva en el historial.
  * **Revisión opcional:** Si no se asigna ningún revisor, el creador puede publicar directamente (skip "En Revisión").
  * **N validadores configurables:** Cada plantilla define 0 a N validadores, con toggle síncrono (en orden estricto) o asíncrono (en paralelo). Si un validador rechaza, el documento vuelve a Borrador y TODAS las validaciones se reinician.
* **Jerarquía Académica (Agrupación):**
  * Estructura de 3 niveles: `Tipo de Estudio` (ej. FP, Bachillerato) -> `Estudio` (ej. DAW, Comercio) -> `Módulo` (ej. DWES, Inglés).
  * **Nivel adicional: "Grupo"** — Entidad interna de Maya PD que representa un conjunto de usuarios (equipo de trabajo, departamento). Se gestiona con CRUD propio dentro de la app (no viene de FDW). Un usuario puede pertenecer a múltiples grupos. Sin anidamiento.
  * El modelado de datos debe soportar flexibilidad (Relaciones Polimórficas) para agrupar plantillas y documentos a diferentes niveles: Tipo de Estudio, Estudio, Módulo, Grupo, o Personal.
* **Migración Anual (Cambio de Plantilla):**
  * Cuando un curso termina y se exige usar una Plantilla nueva (ej. cambio de ley educativa), no se sobreescribe la plantilla vieja.
  * Se crea un documento nuevo basado en la nueva plantilla. ~~El docente debe poder migrar su contenido del año anterior al nuevo documento de forma visual e intuitiva para no empezar de cero.~~ **[DIFERIDO]** La migración side-by-side se difiere a versiones futuras (no MVP). En el MVP, el documento sigue funcionando con la versión de plantilla original. Se muestra un banner informativo si hay nueva versión disponible.
* **Documentos Compartidos y Colaboración:**
  * **[NUEVO — post-Discovery]** Los documentos SÍ se comparten en el MVP. El autor principal selecciona manualmente a qué usuarios/grupos se comparte, con permisos de **edición** o **solo lectura** por colaborador.
  * Se permite **co-autoría** (varios editores). Solo el **autor principal** puede enviar a revisión.
  * **Bloqueo colaborativo** a nivel de bloque: cuando un usuario edita un bloque, se bloquea para el resto con mensaje "Bloqueado por usuario X". Tecnología: WebSocket (Laravel Reverb/Echo + Pusher). Timeout de lock: 5 min. Heartbeat para detectar desconexiones. Presencia de usuarios visible en la UI.
* **Delegación de Documentos:**
  * **[NUEVO — post-Discovery]** Un usuario con permisos (ej. Jefa de Estudios) puede crear un documento en borrador y asignarlo a otro usuario (ej. profesor) para que lo rellene. El profesor no puede rechazar la asignación. El **autor para SoD y trazabilidad es el profesor asignado**. El superior no puede editar el documento tras asignarlo. El documento aparece en los borradores del profesor. Funcionalidad MVP.

### 3.2 Notas de entrevistas (Arquitectura, Seguridad y UX)

* **Decisiones Arquitectónicas (Backend - Laravel & PostgreSQL):**
  * **Versionado BD:** Se descarta usar Git. El versionado es interno en PostgreSQL usando el patrón *Snapshot* (clonación de registros) para mantener consultas rápidas (`SELECT * WHERE version_id = X`). Las modificaciones exigen un "mensaje de commit" (changelog).
  * **Formato JSONB:** El editor no genera HTML WYSIWYG libre. Genera un JSON estructurado de bloques (Array de objetos). Esto permite validación estricta en el backend, prevención de XSS y facilidad para generar PDFs. Cada bloque tiene un **UUID único** inmutable.
  * **Migración de Versiones:** ~~Si un borrador está en curso (Plantilla v1.0) y se publica la v2.0 de la plantilla, el borrador se bloquea y exige migración. El sistema inyecta las secciones nuevas de la v2.0 de forma automática (aditiva, sin destruir los datos previos del usuario, marcando lo obsoleto como `archived`).~~ **[DIFERIDO — post-MVP]** En el MVP, el documento sigue funcionando con la versión de plantilla original. Se muestra un banner informativo al usuario. No se bloquea ni se fuerza migración.
  * **Procesos Asíncronos (Queues):** Las notificaciones (Event-Driven) y la generación del PDF oficial se envían a colas de trabajos (Jobs) vía **RabbitMQ**. La app solo emite eventos; NO muestra notificaciones in-app. La visualización de notificaciones corresponde a herramienta externa. Respuesta HTTP <50ms.
* **Decisiones de Ciberseguridad e Integridad (ISO 9001):**
  * **SSO + FDW:** Login "Zero-Click" mediante token JWT proveniente de un dashboard central corporativo. Laravel valida la firma (Zero Trust). Los datos del usuario no se duplican, se consultan en la BD central mediante PostgreSQL `FDW` con un usuario de **Solo Lectura estricta**.
  * **Auditoría Física (PDF):** El PDF generado asíncronamente mediante **Puppeteer/Chromium headless** ~~incrusta un Hash criptográfico o Código QR, además de fecha, autor y versión, para evitar falsificaciones impresas.~~ PDFs cacheados, regenerados si el documento cambia. **[DIFERIDO]** QR/Hash criptográfico y cabecera corporativa se dejan para fase posterior al MVP. El PDF inicial será simple.
  * **Segregación de Funciones (SoD):** *Policies* estrictas. El autor (`user_id`) jamás puede ser el aprobador (`reviewer_id`) del mismo documento. ~~RBAC estricto.~~ **[ACTUALIZADO]** En el MVP no se implementa RBAC completo por roles. Se implementan **políticas por usuario** (no por rol). Validación `creator_id ≠ reviewer_id` desde el día 1. RBAC completo con roles granulares se difiere a V2. Las peticiones a la API no confían en los IDs del frontend (Prevención IDOR).
  * **Búsqueda Anti-DoS:** Se descarta el *Full-Text Search* en JSON por el momento. Las búsquedas usarán filtros estáticos (dropdowns de Año, Estudio, Estado) indexados en B-Tree.
* **Decisiones de Experiencia de Usuario (React):**
  * **Editor:** Componente estilo Notion (BlockNote/TipTap). Uso del comando `/` para añadir bloques. Celdas normativas bloqueadas visualmente con candado. **[ACTUALIZADO]** Editor de **2 paneles simultáneos**: panel izquierdo con índice de bloques (outline navegable), panel derecho con propiedades del bloque seleccionado (estado, flag obligatorio, descripción) + editor BlockNote para contenido. El wizard de 3 pasos queda **eliminado**. Soporte de importación de markdown por pegado (BlockNote interpreta y convierte automáticamente). No hay modo markdown separado.
  * **Dashboard Unificado:** Patrón *BFF* (Backend For Frontend). Un único endpoint devuelve "Tarjetas de Acción" (Badges semánticos: Urgente, Revisión, Continuar). El dashboard SÍ muestra información de validaciones pendientes in-app (tarjetas con prioridad por cercanía al plazo de entrega). Esto NO es notificación push — es query directa a BD. Navegación mediante menú lateral (`aside`) colapsable. **[ACTUALIZADO]** En el MVP, el aside solo muestra el enlace a "Programaciones". Los filtros de Tipo de Estudio → Estudio → Módulo se implementan como **selectores en cascada** en la parte superior del contenido (no árbol jerárquico en aside). Los datos de jerarquía se cargan una vez al inicio (reactivos desde el cliente).
  * **Creación Contextual (Poka-yoke):** Botón de creación en la vista del módulo que auto-selecciona la plantilla correcta sin formularios intermedios.
  * **Comentarios por Bloque:** En revisión, la interfaz no se ensucia. Comentarios atados al UUID del bloque, visibles en un *Drawer* (panel lateral derecho) con botón de "Marcar como resuelto" (Soft delete en BD).
  * **Migración Side-by-side:** ~~Para el cambio de curso, interfaz de pantalla dividida. Documento antiguo (solo lectura) a la izquierda, nuevo documento a la derecha. Soporte para *Drag & Drop* de bloques JSON o mediante botón de acción (Accesibilidad táctil).~~ **[DIFERIDO — post-MVP]** La migración side-by-side se difiere a futuras versiones. En el MVP, el documento sigue vinculado a su versión de plantilla original con banner informativo si hay nueva versión.

### 3.3 Correos / mensajes relevantes

* *No aplica.* Toda la información ha sido extraída de manera síncrona mediante sesión de Discovery Técnico y Funcional.

### 3.4 Documentos externos y enlaces

* **Tablero de Proyecto:** [https://github.com/orgs/Maya-AQSS/projects/15/views/1](https://github.com/orgs/Maya-AQSS/projects/15/views/1) (Destino de las Epics/Features).
* **Repositorio Código:** [https://github.com/Maya-AQSS/maya-dms.git](https://github.com/Maya-AQSS/maya-dms.git) (Destino de la orquestación de issues).

### 3.5 Decisiones Confirmadas post-Discovery (Rondas de Análisis Cruzado)

> **Fecha:** 2026-03-30. Fuente: `dudas_y_contradicciones.md` — 39 dudas analizadas (28 Ronda 1 + 11 Ronda 2), todas resueltas.
> Las siguientes decisiones surgen del cruce de 4 archivos fuente (conversación, descripción, reunión departamento, reunión cliente) y las respuestas del Product Owner.

#### 3.5.1 — Bloques y Editor

| Decisión | Detalle | Ref. |
|----------|---------|------|
| **3 estados de bloque** | Editable (default, CRUD completo), Modificable (editable no borrable, diff visual al revisor), Bloqueado (inmutable). | D-01 |
| **Editable por defecto** | Todo bloque nuevo es "Editable". El creador marca manualmente los "Modificables" o "Bloqueados". Botón de selección múltiple para cambio masivo. | D-14 |
| **Editor de 2 paneles** | Panel izq: índice de bloques (outline). Panel der: propiedades del bloque (estado, obligatorio, descripción) + BlockNote. Wizard de 3 pasos **eliminado**. | D-09, D-16 |
| **Flag obligatorio/opcional** | Cada bloque tiene flag `obligatorio: true/false`. Validación dual (frontend + backend 422) antes de enviar a revisión. | D-20 |
| **Bloque = unidad de contenido** | Un bloque puede contener texto, tablas, imágenes, etc. No se subdivide automáticamente (cada Enter NO genera un bloque nuevo). Tablas dentro de un bloque son contenido, no bloques separados. | D-19, D-22 |
| **Markdown por pegado** | Al pegar markdown, BlockNote lo interpreta y convierte. No hay modo markdown separado. | D-17 |
| **Plazo a nivel de plantilla** | El plazo de entrega se define a nivel de **plantilla** (opcional), NO por bloque individual. Los documentos heredan el plazo. | D-15, D-35 |
| **Tabla rellenable (futuro)** | Bloque especial alimentado por APIs externas con ciertas celdas editables. Diferido a post-MVP. | D-32 |

#### 3.5.2 — Revisión y Validación

| Decisión | Detalle | Ref. |
|----------|---------|------|
| **Revisor = solo lectura** | El revisor solo puede comentar, aceptar o rechazar. No puede editar contenido. Documentos solo editables en estado Borrador. | D-02 |
| **Revisión opcional** | Si no se asigna revisor → publicación directa (skip "En Revisión"). Aplica a plantillas y documentos. | D-05 |
| **N validadores (0-N)** | Configurable por plantilla. Toggle "Validación ordenada: Sí/No" (síncrona/asíncrona). | D-06, D-07 |
| **Validación síncrona** | El validador N no ve el documento hasta que N-1 apruebe. | D-07 |
| **Validación asíncrona** | Todos reciben notificación simultánea. Validan en cualquier orden. | D-07 |
| **Rechazo = vuelta a paso 0** | Si cualquier validador rechaza, el documento vuelve a Borrador. Todas las validaciones se reinician. Rechazos parciales diferidos a futuro. | D-08 |
| **Publicación auto o manual** | Configurable por plantilla. Default: automática. Si manual → estado "Validado" intermedio. Solo el creador puede publicar. | D-13, D-33 |
| **Diff visual en revisión** | Bloques "Modificables" editados muestran diff (track changes) al revisor. Si aprueba → cambios aceptados implícitamente. | D-01, D-28 |
| **Plantillas también se revisan** | Mismo sistema de 0-N validadores. Mientras está en revisión, se crean docs sobre última versión publicada. Nueva versión al publicar. | D-05, D-37 |

#### 3.5.3 — Creación y Permisos

| Decisión | Detalle | Ref. |
|----------|---------|------|
| **Todos crean plantillas** | Profesores: solo visibilidad personal. Roles superiores (Jefe Depto, Jefe Estudios, Dirección): visibilidad compartida (Tipo Estudio, Estudio, Módulo, Grupo). | D-03 |
| **Documentos compartidos (MVP)** | El autor selecciona colaboradores con permisos de edición o solo lectura. Co-autoría permitida. Solo el autor principal envía a revisión. | D-04, D-29 |
| **Sin RBAC en MVP** | No se limitan acciones por rol. Políticas por usuario. `creator_id ≠ reviewer_id` desde día 1. RBAC completo en V2. | D-23, D-31 |
| **4 roles conocidos** | Profesor, Jefe de Departamento, Jefe de Estudios, Dirección. Roles vendrán de BD externa (proyecto "Roles" futuro). | RF-DEPT-049 |
| **Delegación de documentos** | Superior crea borrador → asigna a profesor. Autor para SoD = profesor. Superior no edita tras asignar. MVP. | D-21, D-34 |
| **Asignación de plantillas** | El creador puede asignar plantilla a usuarios/grupos → aparece en su catálogo. | D-21 |

#### 3.5.4 — Infraestructura y Tecnología

| Decisión | Detalle | Ref. |
|----------|---------|------|
| **RabbitMQ para colas** | La app solo emite eventos a RabbitMQ. NO muestra notificaciones in-app. Visualización desde herramienta externa. | D-24 |
| **Puppeteer/Chromium headless** | Motor PDF con alta fidelidad. Proceso asíncrono (Job en cola). PDFs cacheados, regenerados si doc cambia. QR/cabecera diferidos a post-MVP. | D-25 |
| **WebSocket (Laravel Reverb/Echo + Pusher)** | Para bloqueo colaborativo y presencia. Lock a nivel de bloque, timeout 5 min, heartbeat. ⚠️ Analizar si polling HTTP es más viable para MVP. | D-36 |
| **Dashboard SÍ muestra bandeja de validaciones** | Tarjetas in-app con prioridad por cercanía al plazo. Es query a BD, no notificación push. Notificaciones de eventos van por herramienta externa. | D-30 |
| **Tabla de auditoría separada** | Historial completo de modificaciones por bloque (creado por, modificado por, fechas, valores antes/después) en tabla dedicada, no en JSONB. | D-11 |
| **Filtros en cascada desde cliente** | Tipo Estudio → Estudio → Módulo. Datos descargados una vez al inicio. Selectores en cascada en contenido, no árbol en aside. | RF-DEPT-035 |

#### 3.5.5 — Arquitectura Multitype y Versionado

| Decisión | Detalle | Ref. |
|----------|---------|------|
| **Arquitectura multi-tipo-documentación** | Diseño desde el inicio para múltiples tipos (Programaciones, Calidad, Reservas…). En el MVP, sidebar solo muestra "Programaciones". | D-12 |
| **Migración diferida** | Side-by-side y migración forzosa diferidas a post-MVP. Documentos siguen con versión de plantilla original. Banner informativo si hay nueva versión. | D-26, D-39 |
| **CRUD de Grupos** | Interfaz CRUD interna. 100% internos (no FDW). Sin anidamiento. Un usuario puede pertenecer a múltiples grupos. | D-18, D-38 |
| **Ordenación de listados** | Default: fecha de última modificación (desc). Bandeja de validaciones: prioridad por cercanía al plazo. Cabeceras clicables para reordenar. | D-10 |

-----

## 4. Hechos Confirmados (sin derivar requisitos) (Obligatorio)

Solo hechos ya confirmados por el cliente/arquitectura durante la fase de Discovery. No inferencias. Estas son reglas inamovibles.

* **Hecho 1 (Stack Inamovible):** El sistema se construirá obligatoriamente sobre **React 19** (Frontend SPA), **Laravel 13** (Backend API RESTful) y **PostgreSQL** (Base de Datos principal y motor FDW).
* **Hecho 2 (Estructura JSONB):** El contenido de las plantillas y programaciones se almacenará exclusivamente en formato `JSON/JSONB`. Queda prohibido el almacenamiento de HTML puro en la base de datos para garantizar la validación estructural y prevenir ataques XSS.
* **Hecho 3 (Identidad por UUID):** Todo bloque de contenido dentro del JSON poseerá un Identificador Único Universal (UUID) generado en el momento de su creación. Este UUID es el ancla para los hilos de comentarios y las migraciones futuras.
* **Hecho 4 (Autenticación Delegada):** La aplicación no tendrá pantalla de registro ni gestión local de contraseñas. El acceso se realiza mediante un Token JWT validado criptográficamente, proveniente de un dashboard corporativo principal.
* **Hecho 5 (Usuarios FDW):** La tabla maestra de usuarios no reside en la base de datos de "Maya PD". Se accederá a ella mediante la extensión de PostgreSQL `Foreign Data Wrapper` (FDW).
* **Hecho 6 (Motor de Versionado):** El versionado de documentos y plantillas se realizará mediante clonación de registros en la base de datos (patrón *Snapshot*). Queda descartado el uso de repositorios Git o almacenamiento por deltas/diferencias para este fin.
* **Hecho 7 (PDF Oficial Asíncrono):** El artefacto final exigido por inspección educativa es un documento PDF. Su generación se ejecutará obligatoriamente en segundo plano (colas/jobs) para evitar bloqueos del servidor (Timeouts).
* **Hecho 8 (3 Estados de Bloque):** Cada bloque posee tres estados funcionales: **Editable** (estado por defecto, el docente puede modificar libremente), **Modificable** (visible y editable bajo restricciones, ej. sugerencias del revisor) y **Bloqueado** (solo lectura, contenido normativo fijado por la plantilla). Adicionalmente, cada bloque lleva un flag `obligatorio | opcional`.
* **Hecho 9 (Colas — RabbitMQ):** El sistema de colas/jobs de Laravel se desplegará sobre **RabbitMQ** como broker de mensajería. La aplicación solo emite eventos (`Event::dispatch()`); no implementa notificaciones in-app. El consumo de las notificaciones (email, push) es responsabilidad de un servicio externo.
* **Hecho 10 (PDF — Puppeteer/Chromium):** El motor de renderizado PDF será **Puppeteer** ejecutándose sobre un binario **Chromium headless** en el servidor. La inyección de QR/Hash criptográfico en el PDF queda **diferida a post-MVP**.
* **Hecho 11 (WebSocket — Locking Colaborativo):** El bloqueo en tiempo real durante la edición colaborativa se implementa con **Laravel Reverb / Echo + canal Pusher**. Incluye timeout de inactividad de 5 minutos, heartbeat periódico y canal de presencia para mostrar usuarios activos.
* **Hecho 12 (Documentos Compartidos en MVP):** Los documentos **SÍ se comparten** en el MVP. El autor selecciona colaboradores manualmente, asignando permisos de `edición` o `solo lectura`. Se establece coautoría; solo el autor original puede enviar a revisión.
* **Hecho 13 (Sin RBAC en MVP — Policies por Usuario):** El MVP **no implementa RBAC** (Control de Acceso Basado en Roles). Los permisos se gestionan mediante **Policies de Laravel por usuario**. La restricción de Segregación de Funciones (`creator_id ≠ reviewer_id`) se aplica desde el día 1. RBAC se implementará en V2.
* **Hecho 14 (Migración Side-by-side Diferida):** La funcionalidad de migración interactiva (*side-by-side* con Drag & Drop) queda **diferida a post-MVP**. Los documentos permanecen vinculados a la versión de plantilla con la que fueron creados; un banner informativo indica que existe versión nueva.
* **Hecho 15 (Entidad Grupo):** Se crea la entidad **"Grupo"** con CRUD interno en Maya PD: sin anidamiento, con multi-pertenencia de usuarios, sin sincronización FDW. Los grupos se usan para asignar visibilidad de plantillas y organizar docentes.
* **Hecho 16 (Delegación de Documentos en MVP):** El flujo de delegación es funcionalidad MVP: un superior (jefe de departamento) crea un borrador y lo asigna a un profesor. El profesor es el autor a efectos de SoD. El profesor **no puede rechazar** la delegación.
* **Hecho 17 (Editor 2 Paneles):** El editor de documentos usa una maquetación de **2 paneles** (bloques a la izquierda, propiedades/preview a la derecha). Queda descartado el wizard multi-paso. Se soporta pegado de Markdown que se interpreta automáticamente.
* **Hecho 18 (Validación N-Validadores):** Cada plantilla define entre **0 y N validadores** con toggle síncrono/asíncrono. La publicación puede ser automática (por defecto) o manual, en cuyo caso el documento pasa a un estado intermedio **"Validado"** antes de ser publicado.
* **Hecho 19 (Deadline a Nivel de Plantilla):** Los plazos de entrega se configuran **a nivel de plantilla**, no por bloque individual. Esto simplifica la gestión y evita micro-deadlines fragmentados.
* **Hecho 20 (Historial de Auditoría en Tabla Separada):** El registro de auditoría (quién editó qué bloque, cuándo) se almacena en una **tabla relacional separada**, no embebido dentro del campo JSONB del documento.

---

## 5. Restricciones y Condicionantes Conocidos (Obligatorio)

Limitaciones técnicas, normativas o de negocio que condicionan la forma en la que se debe programar el sistema.

* **Restricción 1 (Cumplimiento Legal ISO 9001):** Toda acción de publicación o aprobación debe generar un registro de auditoría inmutable (Audit Trail) con estampa de tiempo del servidor (jamás del cliente). Las versiones publicadas operan bajo el principio *Append-Only* (Solo inserción, prohibido el borrado o edición).
* **Restricción 2 (Segregación de Funciones - SoD):** A nivel de *Policies* en Laravel, está estrictamente prohibido que el usuario creador de un documento sea la misma persona que lo aprueba o lo pasa a estado "Publicado", independientemente de si tiene el rol de "Súper-Administrador".
* **Restricción 3 (Seguridad Zero Trust / Prevención IDOR):** El backend (Laravel) tiene prohibido confiar en cualquier ID de recurso o filtro enviado por el frontend (React). Toda consulta a la base de datos debe estar cruzada con el ID y los permisos del usuario extraídos del Token JWT autenticado (*Global Scopes*).
* **Restricción 4 (Rendimiento FDW):** Dado que cruzar tablas locales con tablas foráneas mediante FDW puede degradar el rendimiento masivamente, se restringe el uso de sentencias `JOIN` complejas hacia la tabla externa. La información de sesión de los usuarios debe apoyarse en la caché o en el *payload* del token.
* **Restricción 5 (Inviolabilidad del PDF — MVP Parcial):** Para garantizar la trazabilidad física, los PDFs deben inyectar fecha, autor y versión exacta. La inyección de elemento de validación criptográfica (Código QR o Hash) queda **diferida a post-MVP** por complejidad técnica. En MVP se genera el PDF con Puppeteer/Chromium sin QR/Hash.
* **Restricción 6 (Restricción de Búsqueda):** Para prevenir ataques de Denegación de Servicio (DoS) por sobrecarga de CPU en la base de datos, queda temporalmente restringida la implementación de motores de búsqueda de texto libre (*Full-Text Search*) dentro de las columnas JSON. La búsqueda será estrictamente por metadatos (Estudio, Año, Estado).
* **Restricción 7 (SoD Desde Día 1 — Sin Excepciones):** La regla `creator_id ≠ reviewer_id` se implementa desde la primera versión mediante Policies de Laravel. Ningún usuario, independientemente de su nivel de acceso, puede aprobar un documento que haya creado él mismo. Esta restricción es innegociable y aplica incluso antes de tener RBAC.
* **Restricción 8 (Sin Notificaciones In-App):** Maya PD **no implementa** sistema de notificaciones dentro de la aplicación (ni push, ni bell icon, ni inbox de notificaciones). La app solo emite eventos a la cola. Si se necesitan notificaciones, las consume un servicio externo. El dashboard muestra una bandeja de validación mediante consulta directa a BD.
* **Restricción 9 (Documentos Vinculados a Versión Original):** Un documento creado con la plantilla v1.0 permanece siempre en v1.0. **No se migra automáticamente** a la versión nueva de la plantilla. Solo se muestra un banner informativo indicando que existe una versión más reciente.

---

## 6. Supuestos y Huecos de Informacion (Obligatorio)

Este bloque ayuda a la IA (y al equipo humano) a detectar incertidumbre antes de extraer los requisitos definitivos. Representa las decisiones de infraestructura o negocio que aún deben cerrarse.

| Tema | Tipo (`Supuesto` o `Dato faltante`) | Impacto (`Alto`, `Medio`, `Bajo`) | Estado | Resolución |
| --- | --- | --- | --- | --- |
| **Matriz de Roles Exacta (RBAC)** | Dato faltante | Alto | ⚠️ PARCIALMENTE RESUELTO | No habrá RBAC en MVP. Los permisos se gestionan con **Policies de Laravel por usuario**. La regla SoD (`creator_id ≠ reviewer_id`) se aplica desde el día 1. El RBAC completo con roles del JWT se implementará en **V2**. |
| **Infraestructura de Colas (Queues)** | Dato faltante | Medio | ✅ RESUELTO | Se desplegará **RabbitMQ** como broker de mensajería dedicado. Descartado el driver `database` de Laravel. |
| **Motor de Renderizado PDF** | Supuesto | Medio | ✅ RESUELTO | Se confirma **Puppeteer** sobre **Chromium headless**. La inyección de QR/Hash criptográfico queda diferida a post-MVP. |
| **Destino de Notificaciones** | Supuesto | Bajo | ✅ RESUELTO | Confirmado: Maya PD solo emite eventos a la cola (`Event::dispatch()`). **No hay notificaciones in-app.** Un servicio externo consume la cola. El dashboard incluye una **bandeja de validación** consultando directamente la BD. |

---

## 7. Criterios de Extraccion para la IA (Obligatorio)

Definir cómo quieres que el Agente IA procese las fuentes de los bloques anteriores para generar el Backlog (Epics, Features y Tareas).

| Parametro | Valor | Justificación / Instrucción Extendida |
| --- | --- | --- |
| **Idioma de salida** | Español | Todo el Backlog, Epics, User Stories y Criterios de Aceptación deben redactarse en español técnico profesional. |
| **Nivel de detalle esperado** | Alto (Extremo) | Las historias de usuario deben seguir el formato *"Como [rol], quiero [acción] para [beneficio]"*. Las tareas técnicas (Chores) deben especificar qué tablas, endpoints o componentes de React se ven afectados. |
| **Priorizar rapidez o exhaustividad** | Exhaustividad | Es preferible un Backlog largo y granular que tareas genéricas. Cada regla de negocio (ej. prevención IDOR, Drag & Drop, Soft Deletes) debe tener su propia tarea o criterio de aceptación explícito. |
| **Tolerancia a inferencia** | Moderada (Guiada por Arquitectura) | La IA **no debe inventar** funcionalidades nuevas que no estén en el Bloque 3. Sin embargo, **debe inferir** las tareas técnicas necesarias (ej. migraciones de BD, configuración de CORS, setup de Redux/Zustand en React) derivadas de las decisiones arquitectónicas. |
| **Enfoque principal** | Mixto (Negocio + Clean Architecture) | Las *Epics* deben estar orientadas a negocio (ej. "Gestión de Plantillas ISO 9001"), pero las *Features/Issues* deben tener un fuerte componente técnico (ej. "Crear endpoint POST /api/documents con validación de schema JSON"). |

**Notas obligatorias para el Agente Generador del Backlog:**

1. **Desglose Frontend/Backend:** Cada funcionalidad (Feature) debe dividirse claramente en tareas de Laravel (API/BD/Colas) y tareas de React (UI/Estado/Integración).
2. **Criterios de Aceptación:** Deben incluir siempre validaciones de seguridad (ej. "Si el usuario no tiene rol de jefatura, el endpoint devuelve 403 Forbidden").
3. **Mapeo a GitHub:** Estructurar la salida de forma que sea fácilmente parseable por un script `bash` o `gh cli` para inyectarla en el proyecto definido en el Bloque 1.

---

## 8. Glosario y Terminologia del Cliente (Recomendado)

Términos de dominio estandarizados para evitar que la IA (o nuevos desarrolladores) interpreten conceptos erróneamente. El uso de estos términos es obligatorio en todo el código fuente y documentación.

| Termino | Definicion del cliente / Arquitectura | Sinónimos/alias prohibidos o a evitar |
| --- | --- | --- |
| **Programación Didáctica** | El documento final, rellenado por el docente, que se audita para la ISO 9001. Se instancia a partir de una plantilla. | (Evitar llamarlo genéricamente "Archivo" o "Formulario") |
| **Plantilla (Template)** | Estructura normativa base. Contiene los bloques obligatorios y bloqueados que exigen las leyes educativas. | Molde, Formato Base. (Evitar "Documento Padre") |
| **Bloque (Block)** | Unidad mínima de contenido (párrafo, tabla, rúbrica). Almacenado como un objeto dentro de un Array JSON. Todo bloque tiene un UUID único. | Sección, Componente. (Evitar "Fragmento HTML" o "Div") |
| **Snapshot** | Copia exacta e inmutable de los bloques de un documento en un momento temporal, vinculada a una versión (ej. v2.0). | Clonación de registro. (Evitar "Commit de Git" o "Delta") |
| **FDW (Foreign Data Wrapper)** | Extensión de PostgreSQL que permite a Maya-PD consultar la tabla de usuarios del dashboard corporativo en tiempo real, sin replicar datos. | (Evitar "Base de datos externa" sin especificar FDW) |
| **Poka-yoke** | Diseño de interfaz a prueba de errores. El sistema pre-selecciona opciones basándose en el contexto del usuario (ej. auto-seleccionar la plantilla según el Módulo). | (Evitar "Asistente Complejo" o "Wizard largo") |
| **Side-by-side** | Vista de pantalla dividida que permite al docente migrar bloques de su programación antigua a la estructura de la nueva normativa anual. | Pantalla dividida. (Evitar "Herramienta de Merge" o "Importador mágico") |

---

## 9. Semaforo por Fase (Control Operativo)

Este cuadro de mando indica qué fases del workflow están listas para ejecutarse basándose en la completitud de este documento.

| Fase del Workflow | Minimo requerido de este documento | Estado Actual | Responsable Siguiente Paso |
| --- | --- | --- | --- |
| **Fase 1 - Epics y Features** | Bloques 0, 2, 3, 4, 6, 7 | 🟢 **Listo** — Discovery cerrado, 39 decisiones consolidadas en B3.5, B4 y B6 actualizados | Agente Generador de Backlog (IA) — Ejecutar `/iniciar-requisitos` |
| **Fase 2 - Arquitectura y Riesgos** | Bloques 0, 3, 5, 6, 7 | 🟢 **Listo** — Restricciones R1-R9 definidas, supuestos resueltos | Arquitecto Técnico |
| **Fase 3 - Backlog por feature** | Bloques 0, 2, 3, 4, 5, 7 | 🟢 **Listo para Generación** — Hechos H1-H20 confirmados | Agente Generador de Tareas (IA) |
| **Fase 4 - Diagramas C4** | Bloques 0, 3, 5, 7 | 🟢 **Listo para Modelado** | Arquitecto Técnico |
| **Fase 5 - Publicacion VitePress**| Bloques 0, 9 y artefactos aprobados| 🟡 **Pendiente** (Requiere F1-F4) | DevOps / Documentador |
| **Fase 6 - Subida a GitHub** | Bloques 1 y backlog aprobado | 🟡 **Pendiente** (A la espera del Backlog) | Script de Automatización GitHub CLI |

---

## 10. Registro de Cambios y Aprobaciones

Historial inmutable de las modificaciones de este Contexto Fuente, garantizando trazabilidad en la toma de requisitos (compliance ISO 9001 de desarrollo).

| Fecha | Cambio realizado | Responsable | Aprobado por |
| --- | --- | --- | --- |
| 2026-03-25 | Sesión inicial de Discovery Técnico y Funcional (Brainstorming). | IA Mentor (Arquitecto) | Cliente (Product Owner) |
| 2026-03-25 | Volcado inicial resumido del Contexto Fuente. | IA Mentor | Cliente |
| 2026-03-25 | Expansión Exhaustiva (Iteración 1): Detalle profundo de B3, B4 y B5 documentando JSONB, Queues, UX y constraints de ISO 9001. | IA Mentor | Cliente |
| 2026-03-25 | Expansión Definitiva (Iteración 2): Nivel de detalle extremo en todos los bloques (0-10) para inyección directa a Agente de Backlog y automatización GitHub. | IA Mentor | Cliente |
| 2026-03-25 | Consolidación post-Discovery: Integración de 39 decisiones confirmadas (2 rondas de análisis cruzado). Añadido B3.5 (Decisiones), B4 ampliado (H8-H20), B5 actualizado (R5 parcial, R7-R9 nuevas), B6 resuelto (3/4 cerrados), B9 semáforo actualizado. | IA Agente de Requisitos | Cliente (Product Owner) |
