<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\Processes\ProcessDto;
use App\Models\Process;
use Tests\TestCase;

/**
 * Tests de `ProcessDto::fromModel()`. No tocan BD: usamos un modelo Eloquent
 * in-memory para verificar el mapeo de atributos a propiedades tipadas.
 */
class ProcessDtoTest extends TestCase
{
    private function baseProcess(): Process
    {
        $p = new Process;
        $p->id = '00000000-0000-0000-0000-000000000001';
        $p->code = 'PX01';
        $p->name = 'Proceso X';
        $p->alias = 'px01';
        $p->icon = 'folder';
        $p->color = '#0b5394';
        $p->description = 'Una descripción';
        $p->process_parent_id = '00000000-0000-0000-0000-000000000099';

        return $p;
    }

    public function test_from_model_maps_all_fields(): void
    {
        $dto = ProcessDto::fromModel($this->baseProcess());

        $this->assertSame('00000000-0000-0000-0000-000000000001', $dto->id);
        $this->assertSame('PX01', $dto->code);
        $this->assertSame('Proceso X', $dto->name);
        $this->assertSame('px01', $dto->alias);
        $this->assertSame('folder', $dto->icon);
        $this->assertSame('#0b5394', $dto->color);
        $this->assertSame('Una descripción', $dto->description);
        $this->assertSame('00000000-0000-0000-0000-000000000099', $dto->processParentId);
    }

    public function test_from_model_handles_nullable_fields(): void
    {
        $p = $this->baseProcess();
        $p->icon = null;
        $p->color = null;
        $p->description = null;
        $p->process_parent_id = null;

        $dto = ProcessDto::fromModel($p);

        $this->assertNull($dto->icon);
        $this->assertNull($dto->color);
        $this->assertNull($dto->description);
        $this->assertNull($dto->processParentId);
        // Los obligatorios siguen presentes y tipados como string.
        $this->assertSame('PX01', $dto->code);
    }

    public function test_from_model_casts_required_fields_to_string(): void
    {
        $p = new Process;
        $p->id = '00000000-0000-0000-0000-000000000002';
        $p->code = 12345;       // valores no-string del driver
        $p->name = 'N';
        $p->alias = 'a';

        $dto = ProcessDto::fromModel($p);

        $this->assertSame('12345', $dto->code);
        $this->assertIsString($dto->code);
    }
}
