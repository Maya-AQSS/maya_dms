# 📋 Épicas y Features — Maya PD (Sistema Avanzado de Gestión de Programaciones Didácticas)

**Proyecto:** Maya PD — MAYA-PD-ISO9001
**Fecha:** 2026-03-30
**Stack:** React 19 (SPA) · Laravel 13 (API RESTful) · PostgreSQL (JSONB + FDW) · RabbitMQ · WebSocket (Reverb/Echo) · Puppeteer/Chromium
**Estado:** FASE 1 Completada — Listo para aprobación

---

## EPIC-00: Setup e Infraestructura Base

> Fundamentos técnicos necesarios antes de cualquier funcionalidad de negocio. Punto de inicio absoluto del proyecto. Estos backlogs deben completarse en orden antes de abordar cualquier Epic funcional.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-00.1 | Inicialización del monorepo Laravel + React con configuración de entornos (local, staging, producción), variables de entorno y estructura de directorios | MUST |
| F-00.2 | Esquema de base de datos PostgreSQL: migraciones base de plantillas, documentos, bloques (JSONB), grupos, audit_log y snapshots | MUST |
| F-00.3 | Configuración de FDW (Foreign Data Wrapper) para acceso de solo lectura a la tabla de usuarios del dashboard corporativo | MUST |
| F-00.4 | Setup del middleware de autenticación JWT Zero Trust: validación criptográfica del token corporativo, sin registro local ni gestión de contraseñas | MUST |
| F-00.5 | Configuración de RabbitMQ como broker de colas (Laravel Queue driver), workers y canales de eventos | MUST |
| F-00.6 | Setup de WebSocket: Laravel Reverb + Echo + canal Pusher para bloqueo colaborativo y canal de presencia | MUST |
| F-00.7 | Pipeline CI/CD en GitHub Actions: lint, tests automatizados, despliegue a entornos staging y producción | SHOULD |

---

## EPIC-01: Autenticación, Sesión y Control de Acceso

> Garantizar que solo usuarios autenticados por el sistema corporativo accedan a Maya PD, sin duplicar datos de usuarios, y que las políticas de segregación de funciones (SoD) se apliquen desde el día 1.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-01.1 | Middleware Laravel de validación de JWT corporativo: verificación de firma criptográfica, extracción de claims de sesión, rechazo 401 si token inválido o expirado | MUST |
| F-01.2 | Consulta del perfil de usuario activo vía FDW PostgreSQL: nombre, rol declarativo y datos de sesión sin replicar en BD local; caché de payload JWT | MUST |
| F-01.3 | Implementación de Policies de Laravel por usuario para control de acceso a recursos: restricción SoD `creator_id ≠ reviewer_id` en documentos y plantillas | MUST |
| F-01.4 | Global Scopes en todos los Eloquent Models para prevención de IDOR: todas las consultas filtradas por permisos extraídos del JWT autenticado, sin confiar en IDs del frontend | MUST |

---

## EPIC-02: Jerarquía Académica y Grupos

> Dotar al sistema de la estructura organizativa que clasifica plantillas y documentos: niveles académicos (Tipo de Estudio → Estudio → Módulo) desde la fuente FDW, y grupos internos gestionados por Maya PD.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-02.1 | Lectura y exposición de la jerarquía académica (Tipos de Estudio → Estudios → Módulos) desde FDW; API para carga única al inicio del cliente | MUST |
| F-02.2 | CRUD interno de Grupos: creación, edición y eliminación de grupos sin FDW; multi-pertenencia de usuarios; sin anidamiento | MUST |
| F-02.3 | API de selectores en cascada (Tipo de Estudio → Estudio → Módulo) con índices B-Tree en metadatos para búsqueda filtrada anti-DoS | MUST |

---

## EPIC-03: Gestión de Plantillas (Templates)

> Permitir la creación, versionado y ciclo de vida completo de las plantillas normativas que estructuran las Programaciones Didácticas, con control granular por bloque y flujo de aprobación configurable.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-03.1 | CRUD de Plantillas con jerarquía de visibilidad: Global/Centro (roles superiores), Departamento/Estudio, Módulo, Grupo o Personal (solo profesores) | MUST |
| F-03.2 | Editor de plantillas de 2 paneles: panel izquierdo con outline navegable de bloques, panel derecho con propiedades del bloque (estado, flag obligatorio, descripción) + editor BlockNote | MUST |
| F-03.3 | Gestión de estados de bloque en plantilla: Editable (default, CRUD completo), Modificable (editable no borrable, genera diff visual al revisor), Bloqueado (inmutable); flag `obligatorio/opcional` por bloque; selección múltiple para cambio masivo de estado | MUST |
| F-03.4 | Versionado de plantillas mediante patrón Snapshot en PostgreSQL: clonación de registros, UUID único e inmutable por bloque, mensaje de changelog obligatorio al publicar | MUST |
| F-03.5 | Ciclo de vida de plantillas (Borrador → En Revisión → Publicado) con 0-N validadores configurables por plantilla; toggle síncrono/asíncrono; rechazo reinicia todas las validaciones; publicación automática (default) o manual | MUST |
| F-03.6 | Asignación de plantillas a usuarios o grupos: el creador puede asignar una plantilla para que aparezca en el catálogo del destinatario | SHOULD |
| F-03.7 | Configuración de plazo de entrega a nivel de plantilla (campo opcional); los documentos creados sobre la plantilla heredan el plazo | SHOULD |

---

## EPIC-04: Gestión de Documentos (Programaciones Didácticas)

> Permitir a los docentes crear, editar, versionar y publicar sus Programaciones Didácticas a partir de plantillas, con cumplimiento total de los requisitos de inmutabilidad y trazabilidad ISO 9001.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-04.1 | Creación de documentos desde plantilla con selector contextual Poka-yoke: botón de 1 clic en la vista del Módulo que auto-selecciona la plantilla activa publicada sin formularios intermedios | MUST |
| F-04.2 | Editor de documentos de 2 paneles (mismo layout que plantillas): outline de bloques navegable + propiedades/BlockNote; soporte de pegado Markdown con conversión automática; comando `/` para añadir bloques | MUST |
| F-04.3 | Máquina de estados del documento (Borrador → En Revisión → Validado → Publicado): Borrador editable solo por autor/co-autores; En Revisión bloqueado para el autor; estado Validado intermedio si publicación manual; Publicado inmutable | MUST |
| F-04.4 | Versionado de documentos mediante Snapshot Append-Only: versiones publicadas inmutables, historial completo conservado; UUID de bloque persistente entre versiones | MUST |
| F-04.5 | Validación de bloques obligatorios antes de enviar a revisión: validación dual (frontend notificación + backend 422) que impide el envío si hay bloques `obligatorio: true` vacíos | MUST |
| F-04.6 | Banner informativo en el editor cuando existe una nueva versión de la plantilla: sin migración forzada, el documento permanece vinculado a la versión original; solo lectura informativa | MUST |

---

## EPIC-05: Colaboración y Compartición de Documentos

> Habilitar el trabajo simultáneo sobre un documento entre múltiples usuarios, la co-autoría controlada y la delegación de documentos a profesores por parte de roles superiores.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-05.1 | Compartición de documentos: el autor principal selecciona colaboradores manualmente y asigna permisos de edición o solo lectura por colaborador; solo el autor principal puede enviar a revisión | MUST |
| F-05.2 | Bloqueo colaborativo en tiempo real vía WebSocket (Laravel Reverb/Echo + Pusher): lock a nivel de bloque con mensaje "Bloqueado por usuario X", timeout de 5 min, heartbeat para detectar desconexiones, canal de presencia con usuarios activos visibles | SHOULD |
| F-05.3 | Delegación de documentos: usuario con permisos superior (Jefe de Estudios) crea borrador y lo asigna a un profesor; el profesor es el autor para SoD y trazabilidad; el profesor no puede rechazar la asignación; el superior no puede editar el documento tras asignarlo | MUST |

---

## EPIC-06: Sistema de Revisión, Comentarios y Validación

> Implementar el flujo de revisión con comentarios por bloque, diff visual de cambios, y la lógica de aprobación/rechazo con N validadores configurables.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-06.1 | Flujo de revisión con N validadores (0-N por plantilla): validación síncrona (orden estricto, N no ve hasta que N-1 aprueba) o asíncrona (notificación simultánea, cualquier orden); sin revisor asignado el autor publica directamente | MUST |
| F-06.2 | Panel de comentarios por bloque (Drawer lateral derecho): comentarios atados al UUID del bloque, visibles al revisor sin contaminar el editor; botón "Marcar como resuelto" con Soft Delete en BD | MUST |
| F-06.3 | Diff visual en bloques "Modificables" editados por el docente: el revisor ve el contenido original vs. el modificado (track changes); si el revisor aprueba, los cambios se aceptan implícitamente | MUST |
| F-06.4 | Rechazo de documento: cualquier validador puede rechazar; el documento vuelve a Borrador y TODAS las validaciones se reinician desde cero; historial de rechazos visible en auditoría | MUST |

---

## EPIC-07: Dashboard y Navegación

> Proveer al usuario una pantalla de inicio centralizada con acceso rápido a los documentos prioritarios, bandeja de validaciones pendentes y navegación fluida por la jerarquía académica.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-07.1 | Dashboard unificado con patrón BFF (Backend For Frontend): endpoint único que devuelve Tarjetas de Acción categorizadas (Urgente, Revisión pendiente, Continuar borrador) con badges semánticos | MUST |
| F-07.2 | Bandeja de validaciones pendientes en dashboard: tarjetas con prioridad por cercanía al plazo de entrega; implementado como query directa a BD (no notificación push) | MUST |
| F-07.3 | Layout con menú lateral (aside) colapsable: MVP muestra únicamente el enlace a "Programaciones"; arquitectura preparada para múltiples tipos de documento en versiones futuras | MUST |
| F-07.4 | Filtros en cascada (Tipo de Estudio → Estudio → Módulo) como selectores en la cabecera del contenido: datos descargados una vez al inicio y gestionados reactivamente en el cliente | MUST |
| F-07.5 | Ordenación de listados de documentos: default por fecha de última modificación (descendente); bandeja de validaciones por cercanía al plazo; cabeceras de tabla clicables para reordenar | MUST |

---

## EPIC-08: Exportación PDF (Asíncrona)

> Generar el artefacto PDF oficial exigido por inspección educativa de forma asíncrona, con metadatos incrustados, caché inteligente y descarga segura.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-08.1 | Job asíncrono de generación PDF con Puppeteer sobre Chromium headless: encolado en RabbitMQ al publicar el documento; respuesta HTTP < 50 ms sin bloqueo del servidor | MUST |
| F-08.2 | Caché de PDFs generados: almacenamiento del PDF generado asociado a la versión del documento; regeneración automática del caché si el documento es actualizado (nueva versión) | MUST |
| F-08.3 | Inyección de metadatos en PDF: fecha de generación (timestamp servidor), autor, versión exacta del documento; sin QR ni hash criptográfico en MVP | MUST |
| F-08.4 | Endpoint de descarga del PDF generado con URL firmada temporal para archivos privados; verificación de permiso del solicitante | MUST |

---

## EPIC-09: Auditoría e Inmutabilidad (Compliance ISO 9001)

> Garantizar trazabilidad completa de todas las modificaciones sobre documentos y plantillas, con registros inmutables de servidor que satisfagan los requisitos de inspección ISO 9001 y RGPD.

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-09.1 | Tabla relacional de auditoría separada (no JSONB): registro por acción sobre bloque con campos creado_por, modificado_por, timestamp servidor, valor_anterior, valor_nuevo; índices para consulta por documento y por usuario | MUST |
| F-09.2 | Audit Trail Append-Only para versiones publicadas: prohibición de UPDATE/DELETE sobre registros publicados a nivel de BD (constraint + Policy Laravel); timestamp siempre del servidor, nunca del cliente | MUST |
| F-09.3 | Trazabilidad del ciclo de vida completo de documentos y plantillas: registro de cada transición de estado (quién, cuándo, de qué estado a cuál) en la tabla de auditoría | MUST |

---

## EPIC-10: Observabilidad y Monitoreo

> Instrumentar el sistema para detectar anomalías de rendimiento, errores críticos y el estado de los servicios dependientes (BD, colas, WebSocket).

| Feature ID | Feature | Prioridad MoSCoW |
| --- | --- | --- |
| F-10.1 | Logging estructurado de eventos críticos de negocio: autenticación JWT, publicación de documento, rechazo de validación, generación PDF, errores de Job; formato JSON compatible con ELK/Loki | SHOULD |
| F-10.2 | Health check endpoints: estado del servidor Laravel, conectividad PostgreSQL (local + FDW), disponibilidad RabbitMQ y canal WebSocket; respuesta estandarizada para monitoreo externo | SHOULD |

---

## Features Explícitamente Fuera de Scope (Won't)

> Estas funcionalidades han sido analizadas y diferidas de forma explícita durante la sesión de Discovery. No se crearán backlogs para ellas en el MVP.

| Feature | Motivo de exclusión | Referencia |
| --- | --- | --- |
| Migración Side-by-side (pantalla dividida con Drag & Drop) | Diferida a post-MVP; el documento permanece en versión original con banner informativo | Hecho 14, D-26, D-39 |
| QR / Hash criptográfico en PDF | Diferida a post-MVP por complejidad técnica | Hecho 10, Restricción 5 |
| RBAC completo por roles granulares del JWT | Diferido a V2; MVP usa Policies por usuario | Hecho 13, D-23, D-31 |
| Full-Text Search en columnas JSONB | Restringida por prevención de DoS en BD | Restricción 6, D-27 |
| Tabla rellenable desde APIs externas (bloque especial) | Diferida a post-MVP | D-32 |
| Rechazos parciales de validadores | Diferida a post-MVP; en MVP el rechazo reinicia todo | D-08 |
| Notificaciones in-app (bell icon, inbox) | Fuera de scope; la app solo emite eventos a RabbitMQ | Restricción 8, Hecho 9 |

---

## Resumen de Prioridades

| Prioridad | Cantidad de Features |
| --- | --- |
| **MUST** | 41 |
| **SHOULD** | 5 |
| **COULD** | 0 |
| **WON'T** | 7 (listadas arriba, no generan backlog) |
| **Total Features activas** | 46 |

---

## Mapa de Dependencias de Epics

```
EPIC-00 (Infra Base)
  └─► EPIC-01 (Auth + SoD)
        └─► EPIC-02 (Jerarquía + Grupos)
              ├─► EPIC-03 (Plantillas)
              │     └─► EPIC-04 (Documentos)
              │           ├─► EPIC-05 (Colaboración)
              │           ├─► EPIC-06 (Revisión)
              │           ├─► EPIC-08 (PDF)
              │           └─► EPIC-09 (Auditoría)
              └─► EPIC-07 (Dashboard)
EPIC-10 (Observabilidad) — Paralelo a cualquier Epic, no bloqueante
```
