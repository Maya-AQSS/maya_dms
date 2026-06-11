<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TemplateVisibilityLevel;

/**
 * Parámetros de dominio para AcademicScopeNormalizer::normalize().
 *
 * Encapsula los valores que difieren entre el dominio Template y el dominio
 * Document: los IDs del template (siempre fijos), los IDs actuales de la
 * entidad (usados cuando el atributo no está presente en la request), y los
 * mensajes de error específicos de dominio.
 *
 * Divergencias documentadas entre TemplateService y DocumentService (ver changes.md):
 *
 *   - Module branch: Template siempre escribe module_id con el valor del template;
 *     Document solo lo escribe cuando templateModuleId !== null.
 *   - Study branch: Template siempre escribe study_id con el valor del template;
 *     Document solo lo escribe cuando templateStudyId !== null.
 *   - StudyType branch: Template siempre escribe study_type_id con el valor del template;
 *     Document solo lo escribe cuando templateStudyTypeId !== null.
 *   - Mensajes de error: "La plantilla debe…" vs "El documento debe…".
 *
 * El parámetro $strictTemplateIds controla la divergencia:
 *   - true  → comportamiento Template: siempre pisa el campo con el valor del template.
 *   - false → comportamiento Document: solo pisa el campo cuando el valor del template
 *             es no-null.
 */
final readonly class AcademicScopeContext
{
    public function __construct(
        public TemplateVisibilityLevel $visibilityLevel,
        public ?string $templateStudyTypeId,
        public ?string $templateStudyId,
        public ?string $templateModuleId,
        /** ID actual de la entidad (Template o Document), usado cuando el campo no está en attributes. */
        public ?string $entityStudyTypeId = null,
        public ?string $entityStudyId = null,
        public ?string $entityModuleId = null,
        /** Mensaje cuando el module_id enviado difiere del que fija la plantilla. */
        public string $onModuleConflict = 'El módulo no puede cambiar.',
        /** Mensaje cuando el study_id enviado difiere del que fija la plantilla. */
        public string $onStudyConflict = 'El estudio no puede cambiar.',
        /** Mensaje cuando el módulo enviado no pertenece al estudio de la plantilla. */
        public string $onModuleStudyMismatch = 'El módulo debe pertenecer al mismo estudio de la plantilla.',
        /** Mensaje cuando el módulo no pertenece al tipo de estudio de la plantilla. */
        public string $onModuleTypeMismatch = 'El módulo debe pertenecer a un estudio del mismo tipo que la plantilla.',
        /** Mensaje cuando el estudio no pertenece al tipo de estudio de la plantilla. */
        public string $onStudyTypeMismatch = 'El estudio debe pertenecer al mismo tipo de estudio de la plantilla.',
        /** Mensaje cuando el módulo no existe en el catálogo. */
        public string $onModuleNotFound = 'El módulo seleccionado no existe.',
        /** Mensaje cuando el estudio no corresponde al módulo seleccionado. */
        public string $onStudyModuleMismatch = 'El estudio indicado no corresponde con el módulo seleccionado.',
        /**
         * true  → Template domain: siempre sobreescribe los campos académicos con los
         *          valores del template (aunque sean null).
         * false → Document domain: solo sobreescribe cuando el valor del template no es null.
         */
        public bool $strictTemplateIds = true,
    ) {}
}
