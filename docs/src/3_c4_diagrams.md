# 📐 Documentación Visual — Maya PD (MAYA-PD-ISO9001)

**Fase:** 4 — Documentación Visual
**Skill:** System Architect
**Fecha:** 2026-03-30
**Herramienta:** C4 Model (Mermaid) + Diagramas de Flujo
**Estado:** FASE 4 Completada — Pendiente aprobación

> Los diagramas de Nivel 1 y Nivel 2 se encuentran en `docs/src/2_architecture_risks.md`.
> Este documento contiene los diagramas de **Nivel 3 (Componentes)** y los **Flujos de Procesos Críticos**.

---

## 1. C4 Nivel 3 — Diagramas de Componentes

### 1.1 Componentes — Laravel API (Backend)

```mermaid
C4Component
    title Laravel API — Diagrama de Componentes

    Container_Boundary(api, "Laravel 13 API") {

        Component(router, "Router / Middleware Stack", "Laravel Router + Middleware", "Recibe requests HTTP. Aplica JWT Middleware, Rate Limiter y Global Scopes antes de llegar al Controller.")

        Component(controllers, "API Controllers", "Laravel Controllers (app/Http/Controllers/Api/)", "Orquestan el flujo: reciben API Request validada, construyen DTO, invocan Service, devuelven Resource. Máx. 10 líneas por método.")

        Component(requests, "API Requests (Form Requests)", "Laravel FormRequest (app/Http/Requests/)", "Validan y autorizan la entrada HTTP. Reglas de formato y tipado. Sin lógica de negocio.")

        Component(dtos, "DTOs de Entrada", "PHP readonly classes (app/DTOs/)", "Transportan datos validados del Controller al Service con tipado estricto. Inmutables. Solo dirección entrada.")

        Component(services, "Services", "PHP Classes (app/Services/)", "Toda la lógica de negocio: ciclo de vida, SoD, snapshots, eventos. No conocen HTTP. Reciben DTOs, devuelven Eloquent Models.")

        Component(repositories, "Repositories", "PHP Classes (app/Repositories/)", "Acceso a datos exclusivamente vía Eloquent + Query Builder. Aplican Global Scopes IDOR. Devuelven Models/Collections.")

        Component(models, "Eloquent Models", "Laravel Eloquent (app/Models/)", "Representación de datos con relaciones, Global Scopes y mutators. Nunca contienen lógica de negocio.")

        Component(policies, "Policies", "Laravel Policies (app/Policies/)", "Autorización: ¿puede este usuario hacer esta acción sobre este recurso? Aplican SoD y reglas de acceso.")

        Component(resources, "API Resources", "Laravel JsonResource (app/Http/Resources/)", "Transforman Eloquent Models a JSON de respuesta. Sin lógica de negocio. Actúan como DTOs de salida.")

        Component(events, "Events & Listeners", "Laravel Events (app/Events/, app/Listeners/)", "Emiten eventos de dominio a RabbitMQ tras acciones críticas (publicación, rechazo, delegación).")

        Component(jobs, "Jobs", "Laravel Jobs (app/Jobs/)", "Tareas asíncronas despachadas a RabbitMQ: GeneratePdfJob. Procesadas por el Queue Worker.")

        Component(scopes, "Global Scopes", "Eloquent Scopes (app/Scopes/)", "Filtrado IDOR automático aplicado a todas las queries de cada Model. Garantizan aislamiento de datos.")
    }

    System_Ext(db, "PostgreSQL 16", "BD principal con JSONB, FDW, uuid-ossp")
    System_Ext(redis, "Redis", "Caché de jerarquía, JWT payload, PDF status")
    System_Ext(rabbitmq, "RabbitMQ", "Broker de mensajería AMQP")

    Rel(router, requests, "Dirige a")
    Rel(requests, controllers, "Datos validados")
    Rel(controllers, dtos, "Construye")
    Rel(controllers, policies, "authorize()")
    Rel(controllers, services, "Invoca con DTO")
    Rel(controllers, resources, "Transforma respuesta")
    Rel(services, repositories, "Lee/escribe datos")
    Rel(services, events, "dispatch()")
    Rel(services, jobs, "dispatch()")
    Rel(repositories, models, "Usa")
    Rel(models, scopes, "Aplica automáticamente")
    Rel(repositories, db, "Queries SQL via Eloquent")
    Rel(services, redis, "Caché de sesión y jerarquía")
    Rel(events, rabbitmq, "Publica evento AMQP")
    Rel(jobs, rabbitmq, "Encola Job AMQP")
```

---

### 1.2 Componentes — React 19 SPA (Frontend)

```mermaid
C4Component
    title React 19 SPA — Diagrama de Componentes

    Container_Boundary(spa, "React 19 SPA (Vite + TypeScript)") {

        Component(router_fe, "Router (React Router v7)", "Rutas declarativas con lazy loading", "Gestiona la navegación entre páginas. Protege rutas con guard de autenticación JWT.")

        Component(auth_ctx, "Auth Context / Store", "React Context + Zustand", "Almacena el token JWT, datos del usuario activo y estado de autenticación. Persiste en sessionStorage.")

        Component(hierarchy_store, "Hierarchy Store", "Zustand slice", "Almacena el árbol académico completo (Tipos → Estudios → Módulos) cargado una vez al inicio. Alimenta los selectores en cascada.")

        Component(dashboard_page, "Dashboard Page", "React Page Component", "Renderiza tarjetas de acción y bandeja de validaciones. Llama al endpoint BFF /api/dashboard.")

        Component(editor, "Editor 2 Paneles", "React Component + BlockNote", "Panel izquierdo: outline de bloques. Panel derecho: propiedades del bloque + editor BlockNote. Compartido entre plantillas y documentos.")

        Component(blocknote, "BlockNote Editor", "BlockNote (lib externa)", "Editor de bloques estilo Notion. Genera JSONB estructurado. Soporta comando '/' y pegado de Markdown.")

        Component(diff_viewer, "Diff Viewer", "React Component + diff-match-patch", "Muestra comparación visual entre contenido original y modificado en bloques Modificables durante la revisión.")

        Component(comments_drawer, "Comments Drawer", "React Portal Component", "Panel lateral derecho con comentarios por bloque. Se abre sobre el layout del editor sin desplazarlo.")

        Component(sidebar, "Sidebar / Aside", "React Component", "Menú lateral colapsable. MVP muestra solo 'Programaciones'. Extensible por configuración.")

        Component(ws_client, "WebSocket Client", "Laravel Echo + Pusher-js", "Gestiona la conexión WebSocket con Reverb. Suscribe a canales de presencia y locks de bloques.")

        Component(api_client, "API Client", "Axios + interceptors", "Centraliza todas las llamadas HTTP. Añade el JWT en headers. Gestiona refresco de token y errores 401/403.")

        Component(pdf_status, "PDF Status Poller", "React Custom Hook", "Consulta periódicamente /api/documents/{id}/pdf-status hasta que el PDF esté ready o falle.")
    }

    System_Ext(api_be, "Laravel API", "Backend RESTful")
    System_Ext(ws_be, "Laravel Reverb", "WebSocket Server")

    Rel(router_fe, auth_ctx, "Lee estado de auth para guards")
    Rel(dashboard_page, api_client, "GET /api/dashboard")
    Rel(editor, blocknote, "Usa como componente de contenido")
    Rel(editor, ws_client, "Solicita/libera locks de bloque")
    Rel(editor, api_client, "Guarda bloques (debounced)")
    Rel(diff_viewer, api_client, "GET /api/documents/{id}/blocks/{uuid}/diff")
    Rel(comments_drawer, api_client, "GET/POST /api/comments")
    Rel(pdf_status, api_client, "GET /api/documents/{id}/pdf-status")
    Rel(api_client, api_be, "HTTPS / JSON")
    Rel(ws_client, ws_be, "WSS")
    Rel(hierarchy_store, api_client, "GET /api/hierarchy (una vez al inicio)")
```

---

### 1.3 Componentes — Queue Worker (Procesador Asíncrono)

```mermaid
C4Component
    title Queue Worker — Diagrama de Componentes

    Container_Boundary(worker, "Laravel Queue Worker (Horizon)") {

        Component(horizon, "Laravel Horizon", "Queue Manager", "Supervisa los workers, gestiona reintentos, backoff exponencial y métricas de Jobs pendientes/fallidos.")

        Component(pdf_job, "GeneratePdfJob", "Laravel Job", "Consume Jobs de la cola 'maya.jobs'. Valida permisos del document_version_id antes de procesar. Máx. 3 reintentos.")

        Component(pdf_service, "PdfGenerationService", "PHP Service", "Genera el HTML del documento con metadatos (autor, versión, fecha de publicación) y lo envía a Puppeteer.")

        Component(puppeteer_bridge, "Puppeteer Bridge", "Node.js process / PHP proc_open", "Invoca el script de Puppeteer en el contenedor Chromium aislado. Gestiona el timeout y el resultado.")

        Component(s3_service, "S3StorageService", "PHP Service", "Almacena el PDF generado en Object Storage con la clave {document_id}/{version}/document.pdf. Actualiza el estado en BD.")

        Component(event_job, "DomainEventJob", "Laravel Job", "Publica eventos de dominio (DocumentPublished, DocumentRejected) al exchange de notificaciones de RabbitMQ.")

        Component(audit_writer, "AuditLogRepository", "PHP Repository", "Escribe registros de auditoría desde los Jobs. Solo INSERT en audit_log.")
    }

    System_Ext(rabbitmq_w, "RabbitMQ", "Cola 'maya.jobs' + exchange de eventos")
    System_Ext(chromium, "Chromium headless", "Contenedor Docker aislado sin red interna")
    System_Ext(s3, "Object Storage (S3)", "Almacén privado de PDFs")
    System_Ext(db_w, "PostgreSQL 16", "BD local: actualiza pdf_status, audit_log")
    System_Ext(notif, "Servicio de Notificaciones Externo", "Consume exchange de eventos y envía emails/push")

    Rel(horizon, pdf_job, "Despacha")
    Rel(horizon, event_job, "Despacha")
    Rel(pdf_job, pdf_service, "Invoca")
    Rel(pdf_service, puppeteer_bridge, "Envía HTML")
    Rel(puppeteer_bridge, chromium, "Ejecuta renderizado PDF")
    Rel(pdf_service, s3_service, "Almacena PDF")
    Rel(s3_service, s3, "PUT object")
    Rel(s3_service, db_w, "UPDATE pdf_status = ready")
    Rel(pdf_job, audit_writer, "Registra resultado en audit_log")
    Rel(event_job, rabbitmq_w, "Publica al exchange de notificaciones")
    Rel(notif, rabbitmq_w, "Consume eventos")
    Rel(rabbitmq_w, horizon, "Consume Jobs de 'maya.jobs'")
```

---

## 2. Diagramas de Flujo — Procesos Críticos del Dominio

### 2.1 Flujo de Autenticación JWT (Zero Trust)

```mermaid
sequenceDiagram
    actor Usuario
    participant SPA as React 19 SPA
    participant Dashboard as Dashboard Corporativo
    participant API as Laravel 13 API
    participant JWKS as JWKS Endpoint (Corp.)
    participant Redis
    participant FDW as BD Corporativa (FDW)

    Usuario->>Dashboard: Login en sistema corporativo
    Dashboard-->>SPA: Redirect con JWT firmado (RS256)
    SPA->>SPA: Almacena JWT en Auth Store (memory/sessionStorage)

    Note over SPA,API: Toda request posterior incluye JWT en Authorization header

    SPA->>API: GET /api/dashboard (Authorization: Bearer JWT)
    API->>API: JwtMiddleware — extrae header
    API->>Redis: ¿JWKS cacheado?
    alt JWKS en caché (TTL 1h)
        Redis-->>API: JWKS keys
    else JWKS expirado o ausente
        API->>JWKS: GET /.well-known/jwks.json
        JWKS-->>API: Claves públicas RS256
        API->>Redis: Cachear JWKS (TTL 1h)
    end
    API->>API: Validar firma + claims (iss, aud, exp)
    alt Token inválido o expirado
        API-->>SPA: 401 Unauthenticated
        SPA->>Dashboard: Redirect para renovar token
    else Token válido
        API->>Redis: ¿Perfil de usuario cacheado?
        alt Perfil en caché (TTL 15 min)
            Redis-->>API: Datos de usuario
        else Sin caché
            API->>FDW: SELECT id, nombre, email FROM users_fdw WHERE id = :sub
            FDW-->>API: Datos del usuario
            API->>Redis: Cachear perfil (TTL 15 min)
        end
        API->>API: Inyectar usuario en Auth::user()
        API->>API: Ejecutar request (Controller → Service → Repository)
        API-->>SPA: 200 JSON Response
    end
```

---

### 2.2 Flujo del Ciclo de Vida de un Documento

```mermaid
stateDiagram-v2
    direction LR

    [*] --> Borrador : Crear desde plantilla (F-04.1)

    Borrador --> Borrador : Editar bloques (F-04.2)\nGuardar cambios (auto o manual)
    Borrador --> EnRevision : Enviar a revisión\n[bloques obligatorios rellenos]\n[SoD: autor ≠ revisor]

    note right of EnRevision
        Documento bloqueado para el autor.
        Solo revisores pueden comentar,
        aprobar o rechazar.
        Diff visual en bloques Modificables.
    end note

    EnRevision --> Borrador : Rechazo (cualquier validador)\n[motivo obligatorio]\n[todas las validaciones reinician]
    EnRevision --> Validado : Última aprobación\n[publicación manual configurada]
    EnRevision --> Publicado : Última aprobación\n[publicación automática — default]

    Validado --> Publicado : Autor pulsa "Publicar"\n[solo el autor principal]

    Publicado --> Publicado : Nueva versión (re-publicación)\n[Snapshot anterior inmutable]

    note right of Publicado
        Append-Only: inmutable.
        Snapshot creado en BD.
        PDF generado en Job asíncrono.
        Evento emitido a RabbitMQ.
    end note

    Publicado --> [*]
```

---

### 2.3 Flujo de Revisión con N Validadores (Síncrono vs Asíncrono)

```mermaid
flowchart TD
    A([Autor envía a revisión]) --> B{¿N validadores configurados?}

    B -- N = 0 --> C[Publicar directamente\nSnapshot + PDF Job]
    C --> Z([Documento Publicado])

    B -- N >= 1 --> D{¿Modo validación?}

    D -- Síncrono --> E[Notificar solo a Validador 1\nvía RabbitMQ]
    E --> F{Validador 1 decide}
    F -- Rechaza --> REJ[Volver a Borrador\nReinicio total\nMotivo registrado en audit_log]
    REJ --> A
    F -- Aprueba --> G{¿Quedan más validadores?}
    G -- Sí --> H[Notificar siguiente validador\nvía RabbitMQ]
    H --> F
    G -- No --> PUB

    D -- Asíncrono --> I[Notificar a TODOS\nvía RabbitMQ simultáneamente]
    I --> J{Cada validador decide}
    J -- Alguno rechaza --> REJ
    J -- Todos aprueban --> PUB

    PUB{¿Publicación manual?}
    PUB -- Sí --> VAL[Estado: Validado\nEsperar acción del autor]
    VAL --> K([Autor pulsa Publicar])
    K --> FIN[Snapshot + PDF Job + Evento RabbitMQ]
    PUB -- No automático --> FIN
    FIN --> Z
```

---

### 2.4 Flujo de Generación PDF Asíncrona

```mermaid
sequenceDiagram
    actor Autor
    participant API as Laravel API
    participant RabbitMQ
    participant Horizon as Queue Worker (Horizon)
    participant PdfSvc as PdfGenerationService
    participant Chromium as Chromium headless (Docker)
    participant S3 as Object Storage (S3)
    participant DB as PostgreSQL

    Autor->>API: PUT /api/documents/{id}/publish
    API->>DB: Crear Snapshot (document_versions, is_immutable=true)
    API->>DB: INSERT audit_log (action=published)
    API->>RabbitMQ: dispatch(GeneratePdfJob { document_version_id })
    API-->>Autor: 200 OK — publicación confirmada (<50ms)

    Note over API,RabbitMQ: El autor recibe respuesta inmediata.\nEl PDF se genera en background.

    RabbitMQ->>Horizon: Consume GeneratePdfJob
    Horizon->>DB: Verificar que version_id existe y es published
    alt version_id inválido o no publicado
        Horizon->>DB: INSERT audit_log (action=pdf_job_discarded)
        Horizon-->>RabbitMQ: ACK (Job descartado, no reintento)
    else version_id válido
        Horizon->>DB: UPDATE pdf_status = 'processing'
        Horizon->>PdfSvc: buildHtml(DocumentVersion)
        PdfSvc->>DB: SELECT snapshot JSONB + metadatos (autor, versión, fecha)
        DB-->>PdfSvc: Datos del snapshot
        PdfSvc->>PdfSvc: Generar HTML con cabecera de metadatos
        PdfSvc->>Chromium: Renderizar HTML → PDF
        Chromium-->>PdfSvc: Archivo PDF binario
        PdfSvc->>S3: PUT {doc_id}/{version}/document.pdf
        S3-->>PdfSvc: 200 OK
        PdfSvc->>DB: UPDATE pdf_status = 'ready', s3_key = '...'
        PdfSvc->>DB: INSERT audit_log (action=pdf_generated)
    end

    Note over Autor,API: Autor consulta estado del PDF
    Autor->>API: GET /api/documents/{id}/pdf-status
    API->>DB: SELECT pdf_status WHERE document_version_id = :id
    API-->>Autor: { status: 'ready' }

    Autor->>API: GET /api/documents/{id}/pdf
    API->>API: Verificar permiso (Global Scope)
    API->>S3: Generar URL firmada (TTL 15 min)
    API-->>Autor: 302 → URL firmada S3
```

---

### 2.5 Flujo de Colaboración en Tiempo Real (Block Locking)

```mermaid
sequenceDiagram
    actor Editor1 as Editor 1 (Docente A)
    actor Editor2 as Editor 2 (Docente B)
    participant WS as WebSocket Server (Reverb)
    participant API as Laravel API
    participant Redis as Redis (lock store)
    participant DB as PostgreSQL

    Note over Editor1,Editor2: Ambos tienen el documento abierto.\nCanal de presencia activo.

    Editor1->>WS: JOIN canal de presencia (documento {id})
    WS->>API: /broadcasting/auth — verificar JWT + permiso
    API-->>WS: Autorizado
    WS-->>Editor2: Notificación: "Editor 1 se unió"

    Editor2->>WS: JOIN canal de presencia
    WS-->>Editor1: Notificación: "Editor 2 se unió"

    Editor1->>WS: LOCK_REQUEST { block_uuid: "abc-123" }
    WS->>Redis: SET lock:doc:{id}:block:abc-123 → user_id_1 (TTL 300s)
    WS-->>Editor1: LOCK_GRANTED { block_uuid: "abc-123" }
    WS-->>Editor2: BLOCK_LOCKED { block_uuid: "abc-123", locked_by: "Docente A" }

    Note over Editor2: Bloque "abc-123" aparece deshabilitado\ncon mensaje "Bloqueado por Docente A"

    Editor2->>WS: LOCK_REQUEST { block_uuid: "abc-123" }
    WS->>Redis: GET lock:doc:{id}:block:abc-123 → user_id_1 (lock activo)
    WS-->>Editor2: LOCK_DENIED { block_uuid: "abc-123", locked_by: "Docente A" }

    loop Heartbeat cada 30s mientras Editor1 edita
        Editor1->>WS: HEARTBEAT { block_uuid: "abc-123" }
        WS->>Redis: EXPIRE lock:doc:{id}:block:abc-123 300s (renovar TTL)
    end

    Editor1->>WS: LOCK_RELEASE { block_uuid: "abc-123" }
    WS->>Redis: DEL lock:doc:{id}:block:abc-123
    WS-->>Editor2: BLOCK_UNLOCKED { block_uuid: "abc-123" }
    Note over Editor2: Bloque "abc-123" vuelve a estar disponible

    Editor1->>API: PATCH /api/documents/{id}/blocks/abc-123 (contenido guardado)
    API->>DB: UPDATE bloque en JSONB del documento
    API->>DB: INSERT audit_log
    API-->>Editor1: 200 OK
```

---

### 2.6 Flujo de Delegación de Documento

```mermaid
flowchart LR
    A([Jefe de Departamento]) -->|1. Crea borrador| B[Documento en Borrador\ncreator_id = jefe_id]
    B -->|2. Acción: Delegar a profesor X| C{DocumentService\n::delegate}
    C -->|3a. Verificar permiso del\nsolicitante Policy| D{¿Tiene permisos\nde delegación?}
    D -- No --> E[HTTP 403]
    D -- Sí --> F[UPDATE creator_id → profesor_id\nUPDATE delegated_by → jefe_id]
    F --> G[INSERT audit_log\ndelegante, delegado,\ndoc_id, timestamp servidor]
    G --> H[dispatch DocumentDelegated\na RabbitMQ]
    H --> I[Documento visible en\nbandeja del Profesor]

    subgraph Resultado
        I
        J[Jefe: no puede editar\nel documento — 403]
        K[Profesor: autor para SoD\ncreator_id = profesor_id\nno puede rechazar asignación]
    end

    I --> K
    I --> J
```

---

### 2.7 Flujo de Prevención IDOR (Global Scopes)

```mermaid
flowchart TD
    A[HTTP Request\nGET /api/documents/DOC-123] --> B[JwtMiddleware\nExtrae user_id del JWT]
    B --> C[DocumentController::show]
    C --> D[DocumentRepository::findOrFail\nDOC-123]
    D --> E{Global Scope\nAccessibleToUser aplicado}
    E --> F["WHERE id = 'DOC-123'\nAND (\n  creator_id = :user_id\n  OR id IN (\n    SELECT document_id FROM document_collaborators\n    WHERE user_id = :user_id\n  )\n  OR id IN (\n    SELECT document_id FROM document_validators\n    WHERE user_id = :user_id\n  )\n)"]
    F --> G{¿Registro encontrado?}
    G -- Sí → usuario tiene acceso --> H[DocumentResource\nSerializa la respuesta]
    H --> I[200 OK + JSON]
    G -- No → usuario no tiene acceso\nO documento no existe --> J[404 Not Found]

    note1[/"⚠️ Se devuelve 404 (no 403)\npara no revelar la existencia\ndel recurso al atacante"/]
    J --- note1
```

---

## 3. Mapa de Dependencias entre Servicios del Backend

```mermaid
graph TD
    subgraph Controllers
        DC[DocumentController]
        TC[TemplateController]
        RC[ReviewController]
        DashC[DashboardController]
        PDF_C[PdfController]
    end

    subgraph Services
        DS[DocumentService]
        TS[TemplateService]
        RS[ReviewService]
        SS[SnapshotService]
        DashS[DashboardService]
        PDF_S[PdfJobService]
        LockS[DocumentLockService]
        AuditS[AuditLogRepository]
    end

    subgraph Repositories
        DR[DocumentRepository]
        TR[TemplateRepository]
        GR[GroupRepository]
        AR[AuditLogRepository]
    end

    subgraph Jobs_Events
        PDF_J[GeneratePdfJob]
        EV[Domain Events → RabbitMQ]
    end

    DC --> DS
    TC --> TS
    RC --> RS
    DashC --> DashS
    PDF_C --> PDF_S

    DS --> DR
    DS --> SS
    DS --> AuditS
    DS --> EV
    DS --> PDF_S

    TS --> TR
    TS --> SS
    TS --> AuditS
    TS --> EV

    RS --> DR
    RS --> AuditS
    RS --> EV

    SS --> DR
    SS --> TR

    DashS --> DR

    PDF_S --> PDF_J
    PDF_J --> PDF_S
```

---

## 4. Modelo de Datos Simplificado (Entidades Principales)

```mermaid
erDiagram
    TEMPLATES {
        uuid id PK
        string name
        text description
        string visibility_scope
        string status
        uuid created_by FK
        date deadline_date
        timestamp created_at
    }

    TEMPLATE_VERSIONS {
        uuid id PK
        uuid template_id FK
        int version_number
        jsonb blocks_content
        string changelog
        uuid published_by FK
        bool is_immutable
        timestamp published_at
    }

    DOCUMENTS {
        uuid id PK
        uuid template_version_id FK
        uuid module_id
        uuid creator_id
        uuid delegated_by FK
        string status
        date deadline_date
        timestamp created_at
        timestamp updated_at
    }

    DOCUMENT_VERSIONS {
        uuid id PK
        uuid document_id FK
        int version_number
        jsonb blocks_content
        string changelog
        uuid published_by FK
        bool is_immutable
        timestamp published_at
    }

    DOCUMENT_COLLABORATORS {
        uuid id PK
        uuid document_id FK
        uuid user_id
        string permission
    }

    DOCUMENT_VALIDATORS {
        uuid id PK
        uuid document_id FK
        uuid user_id
        int sequence_order
        string validation_mode
        string status
        timestamp validated_at
    }

    COMMENTS {
        uuid id PK
        uuid document_id FK
        uuid block_uuid
        uuid author_id
        text content
        timestamp created_at
        timestamp resolved_at
    }

    GROUPS {
        uuid id PK
        string name
        uuid created_by FK
    }

    GROUP_USER {
        uuid group_id FK
        uuid user_id
    }

    AUDIT_LOG {
        uuid id PK
        string entity_type
        uuid entity_id FK
        uuid block_uuid
        string action
        uuid user_id
        string ip_address
        jsonb previous_value
        jsonb new_value
        timestamp timestamp
    }

    DOCUMENT_PDF_STATUS {
        uuid id PK
        uuid document_version_id FK
        string status
        string s3_key
        timestamp generated_at
        text error_message
    }

    TEMPLATES ||--o{ TEMPLATE_VERSIONS : "tiene versiones"
    TEMPLATE_VERSIONS ||--o{ DOCUMENTS : "base de"
    DOCUMENTS ||--o{ DOCUMENT_VERSIONS : "tiene versiones"
    DOCUMENTS ||--o{ DOCUMENT_COLLABORATORS : "compartido con"
    DOCUMENTS ||--o{ DOCUMENT_VALIDATORS : "validado por"
    DOCUMENTS ||--o{ COMMENTS : "tiene comentarios"
    DOCUMENT_VERSIONS ||--o| DOCUMENT_PDF_STATUS : "tiene PDF"
    GROUPS ||--o{ GROUP_USER : "tiene miembros"
```
