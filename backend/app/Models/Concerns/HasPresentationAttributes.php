<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Presentation\PresentationAttributes;

/**
 * DMS-B04: expone un portador tipado de atributos derivados de presentación
 * ({@see PresentationAttributes}) fuera del attribute-bag de Eloquent.
 *
 * El portador es de ámbito request (lazy, no se persiste ni se serializa: no es
 * un atributo del modelo). Sustituye a la decoración vía `setAttribute` de los
 * valores derivados que alimentan los DTO de respuesta.
 */
trait HasPresentationAttributes
{
    private ?PresentationAttributes $presentationAttributes = null;

    public function presentation(): PresentationAttributes
    {
        return $this->presentationAttributes ??= new PresentationAttributes;
    }
}
