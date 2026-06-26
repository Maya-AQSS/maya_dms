<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Filtro académico resuelto del listado de documentos (snapshot del cabezal).
 *
 * - cascade: AND entre dimensiones; IN dentro de cada dimensión con varios ids.
 * - union: OR entre dimensiones (perfil multi-contexto).
 */
final readonly class DocumentAcademicListFilter
{
    public const MODE_CASCADE = 'cascade';

    public const MODE_UNION = 'union';

    /**
     * @param  list<string>  $studyTypeIds
     * @param  list<string>  $studyIds
     * @param  list<string>  $moduleIds
     */
    public function __construct(
        public string $mode,
        public array $studyTypeIds = [],
        public array $studyIds = [],
        public array $moduleIds = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->studyTypeIds === [] && $this->studyIds === [] && $this->moduleIds === [];
    }
}
