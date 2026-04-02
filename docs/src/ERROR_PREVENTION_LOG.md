# ERROR_PREVENTION_LOG

Registro de lecciones aprendidas para prevenir reincidencias durante la generación y publicación de requisitos.

## Formato de entrada

### YYYY-MM-DD HH:MM — [Fase/Componente]

- Causa raíz:
- Señal de detección:
- Corrección aplicada:
- Regla preventiva:
- Verificación:

---

## Entradas

### 2026-03-24 00:00 — [FASE 6 / upload_backlog_to_github.sh]

- Causa raíz: Desalineación entre documentación, reglas y script (prioridades Won't, saneado de body y relaciones de dependencias).
- Señal de detección: Issues creados con contenido no deseado y relaciones incompletas en sidebar de GitHub.
- Corrección aplicada: Normalización de prioridad Won't->Could, body saneado sin Dependencias/Notas y uso de relaciones nativas parent/blocked by.
- Regla preventiva: Antes de ejecutar la subida, validar consistencia entre plantilla de backlog, skill y script; abortar si hay placeholders o prioridad inválida.
- Verificación: Reejecución completa con 63 issues y revisión manual de relaciones en GitHub.
