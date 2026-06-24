<?php

declare(strict_types=1);

namespace App\Support\Presentation;

use App\Models\Concerns\HasPresentationAttributes;

/**
 * DMS-B04: portador TIPADO de los atributos DERIVADOS de presentación de un
 * documento/plantilla (capacidades de policy, metadatos de versión publicada,
 * estado de revisión en curso, equipo embebido…).
 *
 * Antes esos valores se "decoraban" sobre el modelo Eloquent vía
 * `setAttribute('clave', …)` y el DTO los leía vía `getAttribute('clave')`,
 * contaminando el attribute-bag y acoplando el DTO a claves mágicas a través de
 * las capas (HTTP gate, services, repositorios). Este value object mutable los
 * recoge en un store propio y tipado, fuera del attribute-bag: los colaboradores
 * lo rellenan ({@see HasPresentationAttributes::presentation})
 * y los puentes de DTO lo consumen.
 *
 * Las proyecciones idiomáticas de Eloquent (`withExists`, alias de `select`)
 * siguen viviendo en el attribute-bag — no son decoración de capa y permanecen
 * intactas.
 *
 * Convención: `null` = "no decorado" (equivalente al antiguo `getAttribute` que
 * devolvía null); cada lector aplica su propio casting/`?? false`, preservando el
 * comportamiento exacto.
 */
final class PresentationAttributes
{
    // Capacidades de policy/gate (Document + Template).
    public ?bool $canClone = null;

    public ?bool $canViewHistory = null;

    public ?bool $canCreateNewVersion = null;

    // Modo de revisión derivado del snapshot anclado (Document).
    public ?string $reviewMode = null;

    // Equipo embebido en la respuesta API (Document + Template).
    /** @var array<string, mixed>|null */
    public ?array $team = null;

    // Última versión publicada (Document + Template).
    public ?string $latestPublishedVersionId = null;

    public ?int $latestPublishedVersionNumber = null;

    public ?string $latestPublishedTitle = null;   // Document

    public ?string $latestPublishedName = null;    // Template

    public mixed $latestPublishedAt = null;        // Template (crudo; el DTO lo formatea)

    // Metadatos de presentación (Document).
    public ?int $templateVersionNumber = null;

    public ?bool $isAssignedReviewer = null;

    // Compartición con el viewer (Document).
    public ?bool $isSharedWithMe = null;

    public ?string $sharePermission = null;

    // Revisión de trabajo en curso (Document + Template).
    public ?bool $workingRevisionInProgress = null;

    public ?string $workingRevisionEditorName = null;

    public mixed $workingRevisionStartedAt = null; // crudo; el DTO lo formatea
}
