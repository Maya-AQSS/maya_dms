# 03 - Cambio de arquitectura: grupos externos y contrato embebido

## Contexto

Durante la refactorización de grupos se decide que Maya DMS no debe actuar como sistema de registro de grupos.  
Los grupos pasan a tratarse como un dominio externo (análogo al origen externo de usuarios).

## Decisión

1. DMS elimina la gestión CRUD de grupos como objetivo de arquitectura (rutas `/api/v1/groups` retiradas del backend en esta refactorización).
2. El frontend no dependerá de un endpoint dedicado `/api/v1/groups`.
3. La información de grupo necesaria para UI se entregará embebida en endpoints de negocio, principalmente plantillas y, cuando aplique, documentos.
4. La resolución de grupos se implementará por capas (`Controller -> DTO -> Service -> Repository -> Resource`) mediante lectura externa.

## Contrato objetivo (transición)

En respuestas de plantillas:

- `group_id: string | null` (compatibilidad temporal).
- `team: { id: string, name: string } | null` (lectura externa; el id coincide con el legado `group_id` mientras dure la transición).

En respuestas de documentos:

- Se incluirá contexto de grupo embebido solo si el caso de uso lo requiere, evitando llamadas extra a grupos.

## Implicaciones técnicas

- La validación de `group_id` deja de depender de `exists:groups,id` local.
- La lógica de integración con grupos externos no se ubica en controladores.
- Se mantiene compatibilidad temporal para migrar frontend de forma incremental y segura.

## Estado

Decisión aprobada para esta rama de refactorización de grupos.  
Implementación técnica en fases posteriores.
