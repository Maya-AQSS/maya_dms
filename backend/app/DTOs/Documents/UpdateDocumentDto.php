<?php
declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Cambios soportados sobre los metadatos de un documento en borrador.
 *
 * Todas las propiedades son opcionales: `null` significa "no tocar"
 * (PATCH semántico), y los `?string` que vengan vacíos los normaliza el
 * service. La presencia se trackea con {@see self::changedFields()}.
 */
readonly class UpdateDocumentDto
{
    /**
     * @param  list<string>  $changedFields  Nombres de campos presentes en el payload (validated keys).
     */
    public function __construct(
        public ?string $title = null,
        public ?string $deliveryDeadline = null,
        public ?string $studyTypeId = null,
        public ?string $studyId = null,
        public ?string $moduleId = null,
        public array $changedFields = [],
    ) {}

    public function has(string $field): bool
    {
        return in_array($field, $this->changedFields, true);
    }

    /**
     * Versión array compatible con consumidores que aún esperan el payload
     * crudo. Sólo incluye los campos efectivamente enviados por el cliente.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $map = [
            'title'             => $this->title,
            'delivery_deadline' => $this->deliveryDeadline,
            'study_type_id'     => $this->studyTypeId,
            'study_id'          => $this->studyId,
            'module_id'         => $this->moduleId,
        ];

        return array_intersect_key($map, array_flip($this->changedFields));
    }
}
