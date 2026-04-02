---
layout: home

hero:
  name: "Maya PD"
  text: "Sistema Avanzado de Gestión de Programaciones Didácticas"
  tagline: "React 19 · Laravel 13 · PostgreSQL 16 · ISO 9001 — Backlog técnico generado automáticamente por agente de requisitos."
  actions:
    - theme: brand
      text: Épicas y Features
      link: /1_epics_and_features
    - theme: alt
      text: Arquitectura y Riesgos
      link: /2_architecture_risks
    - theme: alt
      text: Ver en GitHub
      link: https://github.com/Maya-AQSS/maya-dms

features:
  - icon: 📝
    title: Editor BlockNote de 2 Paneles
    details: Outline navegable de bloques a la izquierda, propiedades y editor estilo Notion a la derecha. Soporta 3 estados de bloque (Editable, Modificable, Bloqueado) y pegado de Markdown.

  - icon: 🔄
    title: Ciclo de Vida ISO 9001
    details: Máquina de estados Borrador → En Revisión → Publicado con 0-N validadores configurables (síncrono o asíncrono), Segregación de Funciones y Audit Trail Append-Only inmutable.

  - icon: 📄
    title: PDF Oficial Asíncrono
    details: Generación asíncrona de PDF con Puppeteer/Chromium headless encolada en RabbitMQ. Descarga segura mediante URLs firmadas temporales (S3). Respuesta HTTP < 50 ms.

  - icon: 🔒
    title: Zero Trust + Prevención IDOR
    details: Autenticación delegada 100% a JWT corporativo (RS256). Sin registro local. Global Scopes en todos los modelos Eloquent. FDW de solo lectura para usuarios externos.

  - icon: 👥
    title: Colaboración en Tiempo Real
    details: Bloqueo de bloques por colaborador vía WebSocket (Laravel Reverb). Canal de presencia con usuarios activos. Lock timeout 5 min con heartbeat. Compartición con permisos edición/lectura.

  - icon: 🏗️
    title: Arquitectura Limpia
    details: Laravel 13 con capas Controller → DTO → Service → Repository → API Resource. PostgreSQL 16 con JSONB y patrón Snapshot. RabbitMQ para desacoplamiento total de notificaciones.
---
