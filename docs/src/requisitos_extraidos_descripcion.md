# Requisitos Extraídos — Documento Maestro de Contexto

> **Fuente:** `docs/src/0_descripcion_proyecto.md`
> **Fecha del documento:** 2026-03-25
> **Autores:** Equipo Directivo / Coordinador ISO 9001 + Arquitecto Full-Stack
> **Fecha de extracción:** 2026-03-30

---

## Requisitos Funcionales (RF)

### Entidades y Dominio

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-001 | Propósito del sistema | Desarrollo de "Maya PD", aplicación web para creación, gestión y auditoría de Programaciones Didácticas cumpliendo ISO 9001. |
| RF-DESC-002 | Plantillas como moldes normativos | Plantillas compuestas por bloques/secciones. Versiones (v1.0, v2.0). CRUD completo. Jerarquía de visibilidad: Global/Centro, Departamento/Estudio, Personal. |
| RF-DESC-003 | Documentos como instancias | Documentos (Programaciones) son instancias creadas por docentes a partir de una plantilla para un módulo específico y un año escolar. |
| RF-DESC-004 | Bloques con UUID | Todo bloque posee un UUID único, generado al crearse. Es el ancla para comentarios y migraciones. |
| RF-DESC-005 | Estados de bloque en Plantilla | ~~Abiertos (editables libremente) o Bloqueados (texto normativo fijo).~~ **[MODIFICADO por D-01]** 3 estados confirmados: **Editable** (sin restricciones, CRUD completo, default), **Modificable** (editable pero no borrable, alerta al revisor con diff visual del texto original), **Bloqueado** (no modificable). |
| RF-DESC-006 | Comportamiento bloque en Documento | Si un docente desbloquea un bloque bloqueado para adaptarlo, el sistema marca el documento como "Requiere Revisión" por jefatura/coordinación. |
| RF-DESC-007 | Instanciación de bloques vacíos | En un documento, el docente puede instanciar bloques vacíos requeridos por la plantilla (ej. añadir n filas de tabla o bloques customizados predefinidos). |

### Ciclo de Vida

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-008 | Máquina de Estados del Documento | Borrador → En Revisión → Publicado. Al publicarse nueva versión, la anterior se conserva en historial. **[NOTA D-33]** Si la plantilla define publicación manual, se añade estado intermedio **"Validado"**: Borrador → En Revisión → Validado → Publicado. Solo el creador puede pulsar "Publicar". Si publicación automática (default), se mantiene el flujo de 3 estados. |
| RF-DESC-009 | Borrador editable por autor | Solo el autor edita en estado Borrador. |
| RF-DESC-010 | En Revisión bloqueado + feedback | Bloqueado para el autor. El revisor lee y añade feedback. **[NOTA D-02]** El revisor **solo puede comentar**, aceptar o rechazar. No puede editar contenido. **[NOTA D-05]** La revisión es **opcional**: si no se asigna revisor, se puede publicar directamente. |
| RF-DESC-011 | Publicado inmutable y vigente | Versión oficial, inmutable. Las anteriores se conservan. |

### Jerarquía y Agrupación

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-012 | Jerarquía de 3 niveles | Tipo de Estudio → Estudio → Módulo. |
| RF-DESC-013 | Relaciones Polimórficas | Modelado flexible para agrupar plantillas a diferentes niveles de la jerarquía. |

### Migración

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-014 | Migración anual sin sobreescritura | Al cambiar de plantilla (nueva ley), no se sobreescribe la vieja. Se crea documento nuevo con la nueva plantilla. |
| RF-DESC-015 | Migración visual intuitiva | El docente debe poder migrar contenido del año anterior al nuevo documento de forma visual e intuitiva. |
| RF-DESC-016 | Bloqueo + migración forzosa de borrador | ~~Si la plantilla base publica nueva versión, el borrador se bloquea y el sistema inyecta secciones nuevas de forma aditiva sin destruir datos (lo obsoleto → `archived`).~~ **[MODIFICADO por D-26/D-39]** En el MVP, el documento sigue funcionando con la versión de plantilla original. Se muestra un banner informativo. No se bloquea ni se fuerza migración. |

### Versionado

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-017 | Changelog obligatorio | Cada versión publicada almacena un `changelog_message` con descripción de cambios. |
| RF-DESC-018 | Patrón Snapshot | Clonación de todos los registros al publicar. No deltas, no Git. |

### Editor

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-019 | Editor estilo Notion (BlockNote/TipTap) | Comando `/` para añadir bloques. Celdas normativas bloqueadas con candado visual. |
| RF-DESC-020 | Formato JSONB en BD | El editor genera JSON estructurado de bloques (array de objetos). No HTML. Validación estricta en backend. |

### Dashboard y UX

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-021 | Dashboard unificado con tarjetas de acción | Badges semánticos: Urgente, Revisión, Continuar. |
| RF-DESC-022 | Navegación Poka-yoke | Botón de creación en la vista del módulo auto-selecciona la plantilla correcta. Sin formularios intermedios. |
| RF-DESC-023 | Aside colapsable para jerarquía + filtros | Menú lateral colapsable para la jerarquía, tablas filtrables en panel central. |

### Comentarios

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-024 | Comentarios por bloque (UUID) | Visibles en Drawer lateral. Botón "Marcar como resuelto" (Soft delete en BD). |

### Migración Side-by-side

| ID | Requisito | Detalle |
|----|-----------|---------|
| RF-DESC-025 | Pantalla dividida para migración | Documento antiguo (solo lectura) a la izquierda, nuevo a la derecha. Soporte D&D de bloques JSON y botón alternativo para accesibilidad táctil. |

---

## Requisitos No Funcionales (RNF)

| ID | Requisito | Detalle |
|----|-----------|---------|
| RNF-DESC-001 | Tier 1 — Business Critical | Caída en periodo de entrega o pérdida de datos tiene impacto legal y administrativo severo. |
| RNF-DESC-002 | ISO 9001 + RGPD + LOMLOE | Inmutabilidad de datos, auditoría de accesos (quién, cuándo, qué), cifrado/protección datos personales docentes. |
| RNF-DESC-003 | Entornos: Local, Staging, Producción | CI/CD. Staging con volcado anonimizado o FDW replicada para pruebas. |
| RNF-DESC-004 | Idioma: Español (base) | Preparado para i18n en frontend (React) y backend (Laravel lang) para lenguas cooficiales futuras. |
| RNF-DESC-005 | SSO Zero-Click JWT | Login desde dashboard corporativo. Laravel valida firma (Zero Trust). Datos de usuario vía FDW Solo Lectura. |
| RNF-DESC-006 | Auditoría física (PDF) | PDF con Hash criptográfico o QR + fecha + autor + versión. Evita falsificaciones impresas. |
| RNF-DESC-007 | Segregación de Funciones (SoD) | Autor ≠ Aprobador. Policies estrictas. Prevención IDOR: no confiar en IDs del frontend. |
| RNF-DESC-008 | Anti-DoS en búsquedas | Sin Full-Text Search en JSON. Filtros estáticos indexados en B-Tree. |
| RNF-DESC-009 | PDF generado asíncronamente (Jobs/Queues) | Colas de trabajos para notificaciones y PDFs. Respuesta HTTP < 50ms. |
| RNF-DESC-010 | Rendimiento FDW | Prohibidos JOINs complejos hacia tabla externa. Usar caché o payload del token para datos de sesión. |

---

## Decisiones Arquitectónicas (DA)

| ID | Decisión | Justificación |
|----|----------|---------------|
| DA-DESC-001 | React (SPA) + Laravel (API RESTful) + PostgreSQL | Stack obligatorio. Monorepo o repos separados. |
| DA-DESC-002 | JSON/JSONB exclusivo para contenido | Prohibido HTML puro. Validación estructural y prevención XSS. |
| DA-DESC-003 | UUID por bloque | Ancla inmutable para comentarios, migraciones y trazabilidad. |
| DA-DESC-004 | Autenticación delegada (JWT) | Sin pantalla de registro ni contraseñas locales. Token del dashboard corporativo. |
| DA-DESC-005 | Usuarios vía FDW | Tabla maestra de usuarios no reside en Maya PD. Acceso por PostgreSQL FDW. |
| DA-DESC-006 | Versionado por Snapshot | Clonación de registros en BD. Descartado Git y deltas. |
| DA-DESC-007 | PDF asíncrono obligatorio | Generación en colas para evitar timeouts. **[RESUELTO por D-25]** Motor: Puppeteer/Chromium headless. PDFs cacheados, se regeneran si el documento cambia. QR/cabecera corporativa diferido a post-MVP. |
| DA-DESC-008 | Event-Driven para notificaciones | Laravel dispara eventos a cola. **[RESUELTO por D-24]** Cola: RabbitMQ. La app solo emite eventos; NO muestra notificaciones in-app. Visualización de notificaciones desde herramienta externa. |

---

## Restricciones (R)

| ID | Restricción | Detalle |
|----|-------------|---------|
| R-DESC-001 | Audit Trail inmutable | Acciones de publicación/aprobación con estampa de tiempo del servidor (nunca del cliente). Append-Only. |
| R-DESC-002 | SoD estricta | `user_id` creador ≠ `reviewer_id` aprobador, independientemente de rol. |
| R-DESC-003 | Zero Trust / Prevención IDOR | Backend no confía en IDs del frontend. Toda consulta cruzada con permisos del JWT. Global Scopes. |
| R-DESC-004 | Rendimiento FDW acotado | Sin JOINs complejos hacia tabla foránea. Caché/token para datos de sesión. |
| R-DESC-005 | Inviolabilidad del PDF | QR/Hash criptográfico en cada página del PDF vinculable a la BD. **[NOTA RF-DEPT-054/D-25]** Diferido a post-MVP. El PDF inicial será simple (sin QR ni cabecera corporativa). |
| R-DESC-006 | Sin Full-Text Search en JSON | Solo filtros estáticos por metadatos (Estudio, Año, Estado) indexados. |

---

## Supuestos y Huecos (SH)

| ID | Tema | Tipo | Impacto | Acción |
|----|------|------|---------|--------|
| SH-DESC-001 | Matriz de Roles Exacta (RBAC) | Dato faltante | Alto | ~~Solicitar al cliente nomenclatura exacta de roles corporativos que viajan en el JWT.~~ **[PARCIALMENTE RESUELTO por D-23, RESUELTO por D-31]** En el MVP no se implementa RBAC completo. 4 roles conocidos: Profesor, Jefe de Departamento, Jefe de Estudios, Dirección (RF-DEPT-049). Se implementan **políticas por usuario** (no por rol). Desde el día 1: validación SoD mínima `creator_id ≠ reviewer_id` (RF-DEPT-074). RBAC completo con roles granulares se difiere a **V2**. |
| SH-DESC-002 | Infraestructura de Colas | ~~Dato faltante~~ | ~~Medio~~ | **[✅ RESUELTO por D-24]** RabbitMQ como sistema de colas. La app solo emite eventos a RabbitMQ; no muestra notificaciones in-app. |
| SH-DESC-003 | Motor de Renderizado PDF | ~~Supuesto~~ | ~~Medio~~ | **[✅ RESUELTO por D-25]** Puppeteer/Chromium headless. Generación asíncrona (Job en cola). PDFs cacheados, regenerados si el documento cambia. |
| SH-DESC-004 | Destino de Notificaciones | ~~Supuesto~~ | ~~Bajo~~ | **[✅ RESUELTO por D-24/D-27]** Confirmado: Maya PD solo emite evento a RabbitMQ. La app NO muestra notificaciones. La visualización de notificaciones la gestiona una herramienta externa. |

---

## Glosario (G)

| ID | Término | Definición | Sinónimos prohibidos |
|----|---------|------------|---------------------|
| G-DESC-001 | Programación Didáctica | Documento final del docente, auditado para ISO 9001. Instancia de plantilla. | "Archivo", "Formulario" |
| G-DESC-002 | Plantilla (Template) | Estructura normativa base con bloques obligatorios/bloqueados. | "Documento Padre" |
| G-DESC-003 | Bloque (Block) | Unidad mínima de contenido (párrafo, tabla, rúbrica). Objeto dentro de array JSON. UUID único. | "Fragmento HTML", "Div" |
| G-DESC-004 | Snapshot | Copia exacta e inmutable de bloques vinculada a una versión. | "Commit de Git", "Delta" |
| G-DESC-005 | FDW | Extensión PostgreSQL para consultar tabla de usuarios del dashboard corporativo en tiempo real. | "Base de datos externa" (sin especificar FDW) |
| G-DESC-006 | Poka-yoke | Diseño a prueba de errores. Pre-selección de opciones según contexto del usuario. | "Asistente Complejo", "Wizard largo" |
| G-DESC-007 | Side-by-side | Vista de pantalla dividida para migrar bloques de programación antigua a nueva normativa. | "Herramienta de Merge", "Importador mágico" |

---

## Criterios de Extracción definidos por el cliente (CE)

| ID | Parámetro | Valor |
|----|-----------|-------|
| CE-DESC-001 | Idioma de salida | Español técnico profesional |
| CE-DESC-002 | Nivel de detalle | Alto (Extremo). Historias de usuario completas. Tareas técnicas con tablas, endpoints y componentes. |
| CE-DESC-003 | Prioridad | Exhaustividad sobre rapidez. Backlog largo y granular. |
| CE-DESC-004 | Tolerancia a inferencia | Moderada. No inventar funcionalidades. Sí inferir tareas técnicas derivadas (migraciones BD, CORS, Redux/Zustand). |
| CE-DESC-005 | Enfoque | Mixto: Epics orientadas a negocio + Features con componente técnico fuerte. |
| CE-DESC-006 | Desglose Frontend/Backend | Cada Feature dividida en tareas Laravel (API/BD/Colas) y React (UI/Estado/Integración). |
| CE-DESC-007 | Criterios de aceptación | Siempre incluir validaciones de seguridad (ej. "Si no tiene rol → 403 Forbidden"). |
