import { defineConfig } from 'vitepress';
import { withMermaid } from 'vitepress-plugin-mermaid';  // Necesario para diagramas C4/Mermaid

export default withMermaid(
  defineConfig({
    title: "Maya PD — Sistema de Gestión de Programaciones Didácticas",
    description: "Sistema avanzado de gestión de Programaciones Didácticas con ciclo de vida ISO 9001, editor BlockNote, PDF asíncrono y colaboración en tiempo real. Backlog técnico generado automáticamente.",
    lang: 'es-ES',

    // Para GitHub Pages
    base: '/agentics-extractor-requisitos/',
    srcDir: './src',

    ignoreDeadLinks: true,

    mermaid: {
      theme: 'dark'
    },

    themeConfig: {
      nav: [
        { text: 'Inicio', link: '/' },
        { text: 'Épicas y Features', link: '/1_epics_and_features' },
        { text: 'Arquitectura', link: '/2_architecture_risks' },
        { text: 'Diagramas C4', link: '/3_c4_diagrams' },
        { text: 'Auditoría', link: '/AUDIT_LOG' },
      ],

      sidebar: [
        // ── Documentación del proyecto ──────────────────────────────────────
        {
          text: '📋 Proyecto',
          items: [
            { text: '📖 Descripción del Proyecto', link: '/0_descripcion_proyecto' },
            { text: '🎯 Épicas y Features (48 Total)', link: '/1_epics_and_features' },
            { text: '🏛️ Arquitectura y Riesgos (STRIDE)', link: '/2_architecture_risks' },
            { text: '📐 Diagramas C4 Model', link: '/3_c4_diagrams' },
            { text: '📋 Registro de Auditoría', link: '/AUDIT_LOG' },
          ]
        },

        // ── Backlog agrupado por CATEGORÍA UNIVERSAL ────────────────────────
        {
          text: '🖥️ UI / Presentation',
          collapsed: false,
          items: [
            { text: 'F-02.3 — Sincronización Optimista (Colaboración RT)', link: '/backlog/F-02.3_optimistic-sync' },
            { text: 'F-03.2 — CRUD Plantillas (Frontend)', link: '/backlog/F-03.2_templates-crud-frontend' },
            { text: 'F-04.2 — Editor BlockNote (Frontend)', link: '/backlog/F-04.2_blocknote-editor-frontend' },
            { text: 'F-04.6 — Estados Visuales de Bloque', link: '/backlog/F-04.6_block-states-ui' },
            { text: 'F-06.2 — Revisión de Documentos (Frontend)', link: '/backlog/F-06.2_review-frontend' },
            { text: 'F-06.3 — Panel de Validadores (Frontend)', link: '/backlog/F-06.3_validators-panel-frontend' },
            { text: 'F-07.3 — Solicitar PDF (Frontend)', link: '/backlog/F-07.3_pdf-request-frontend' },
            { text: 'F-07.4 — Descarga PDF con URL Firmada', link: '/backlog/F-07.4_pdf-download-signed-url' },
            { text: 'F-07.5 — Historial de Versiones (Frontend)', link: '/backlog/F-07.5_version-history-frontend' },
          ]
        },
        {
          text: '⚙️ Logic / Business',
          collapsed: false,
          items: [
            { text: 'F-02.2 — Presencia y Notificaciones WebSocket', link: '/backlog/F-02.2_websocket-presence' },
            { text: 'F-03.1 — CRUD Plantillas (Backend)', link: '/backlog/F-03.1_templates-crud-backend' },
            { text: 'F-03.3 — Bloques de Plantilla (Backend)', link: '/backlog/F-03.3_template-blocks-backend' },
            { text: 'F-03.5 — Clonado de Plantillas', link: '/backlog/F-03.5_template-cloning' },
            { text: 'F-03.6 — Versionado de Plantillas (Snapshot)', link: '/backlog/F-03.6_template-versioning' },
            { text: 'F-03.7 — Compartición de Plantillas', link: '/backlog/F-03.7_template-sharing' },
            { text: 'F-04.1 — CRUD Documentos (Backend)', link: '/backlog/F-04.1_documents-crud-backend' },
            { text: 'F-04.3 — Editor de Bloques (Backend)', link: '/backlog/F-04.3_block-editor-backend' },
            { text: 'F-04.5 — Block Locking WebSocket (Backend)', link: '/backlog/F-04.5_block-locking-backend' },
            { text: 'F-05.1 — Flujo Borrador → En Revisión → Publicado', link: '/backlog/F-05.1_document-lifecycle' },
            { text: 'F-05.2 — Motor de Validadores N-Validadores', link: '/backlog/F-05.2_validators-engine' },
            { text: 'F-05.3 — Delegación de Documentos', link: '/backlog/F-05.3_document-delegation' },
            { text: 'F-06.1 — Ciclo de Revisión (Backend)', link: '/backlog/F-06.1_review-backend' },
            { text: 'F-06.4 — Rechazo y Reactivación de Revisión', link: '/backlog/F-06.4_review-rejection' },
            { text: 'F-07.1 — Cola RabbitMQ Generación PDF', link: '/backlog/F-07.1_pdf-queue-rabbitmq' },
            { text: 'F-07.2 — Worker PDF (Puppeteer/Chromium)', link: '/backlog/F-07.2_pdf-worker-puppeteer' },
            { text: 'F-08.3 — Notificaciones de Dominio (RabbitMQ)', link: '/backlog/F-08.3_domain-notifications' },
          ]
        },
        {
          text: '🗄️ Data',
          collapsed: false,
          items: [
            { text: 'F-00.2 — Schema BD (Migraciones PostgreSQL)', link: '/backlog/F-00.2_database-schema' },
            { text: 'F-03.4 — Bloques de Plantilla (Persistencia JSONB)', link: '/backlog/F-03.4_template-blocks-data' },
            { text: 'F-04.4 — Snapshot Versionado de Documentos', link: '/backlog/F-04.4_document-snapshot' },
          ]
        },
        {
          text: '🔌 Integration',
          collapsed: false,
          items: [
            { text: 'F-00.3 — Configuración FDW (Foreign Data Wrapper)', link: '/backlog/F-00.3_fdw-config' },
            { text: 'F-01.2 — Cliente JWKS (Clave Pública RS256)', link: '/backlog/F-01.2_jwks-client' },
            { text: 'F-02.1 — API REST de Integración (Endpoints Públicos)', link: '/backlog/F-02.1_api-integration' },
          ]
        },
        {
          text: '🏗️ Infrastructure',
          collapsed: false,
          items: [
            { text: 'F-00.1 — Setup Proyecto (Laravel 13 + React 19 + Vite)', link: '/backlog/F-00.1_project-setup' },
            { text: 'F-00.5 — Setup RabbitMQ (Broker + Horizon)', link: '/backlog/F-00.5_rabbitmq-setup' },
            { text: 'F-00.6 — Setup WebSocket (Laravel Reverb)', link: '/backlog/F-00.6_websocket-setup' },
            { text: 'F-00.7 — Setup S3 + URLs Firmadas', link: '/backlog/F-00.7_s3-storage-setup' },
            { text: 'F-08.1 — CI/CD Pipeline (GitHub Actions)', link: '/backlog/F-08.1_cicd-pipeline' },
            { text: 'F-08.2 — Despliegue Docker + Variables de Entorno', link: '/backlog/F-08.2_docker-deployment' },
          ]
        },
        {
          text: '🔒 Security',
          collapsed: false,
          items: [
            { text: 'F-00.4 — Global Scopes (Prevención IDOR)', link: '/backlog/F-00.4_global-scopes-idor' },
            { text: 'F-01.1 — Validación JWT (Middleware RS256)', link: '/backlog/F-01.1_jwt-validation' },
            { text: 'F-01.3 — Policies Laravel (Autorización SoD)', link: '/backlog/F-01.3_policies-authorization' },
            { text: 'F-01.4 — Permisos BD Restringidos (FDW Read-Only)', link: '/backlog/F-01.4_db-permissions' },
            { text: 'F-08.4 — Auditoría de Accesos y Seguridad', link: '/backlog/F-08.4_security-audit' },
            { text: 'F-09.2 — Audit Trail Append-Only (Inmutabilidad)', link: '/backlog/F-09.2_audit-trail-append-only' },
          ]
        },
        {
          text: '📊 Observability',
          collapsed: false,
          items: [
            { text: 'F-09.1 — Audit Log de Negocio (Tabla audit_log)', link: '/backlog/F-09.1_business-audit-log' },
            { text: 'F-09.3 — Dashboard de Auditoría (Frontend)', link: '/backlog/F-09.3_audit-dashboard-frontend' },
            { text: 'F-10.1 — Logging Estructurado de Eventos Críticos', link: '/backlog/F-10.1_structured-logging' },
            { text: 'F-10.2 — Health Check Endpoints', link: '/backlog/F-10.2_health-checks' },
          ]
        },
      ],

      socialLinks: [
        { icon: 'github', link: 'https://github.com/Maya-AQSS/maya-dms' }
      ],

      footer: {
        message: 'Maya PD — Sistema Avanzado de Gestión de Programaciones Didácticas',
        copyright: 'Copyright © 2026 — Generado con Extractor de Requisitos Autónomo'
      },

      search: {
        provider: 'local'
      },

      editLink: {
        pattern: 'https://github.com/Maya-AQSS/agentics-extractor-requisitos/edit/main/docs/:path',
        text: 'Editar esta página en GitHub'
      },

      lastUpdated: {
        text: 'Última actualización',
        formatOptions: {
          dateStyle: 'short',
          timeStyle: 'short'
        }
      }
    }
  })
)
