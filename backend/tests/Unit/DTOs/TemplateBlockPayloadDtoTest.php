<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\TemplateBlocks\TemplateBlockPayloadDto;
use App\Enums\BlockType;
use Tests\TestCase;

class TemplateBlockPayloadDtoTest extends TestCase
{
    public function test_toarray_serializa_block_type_y_campos_de_maquetacion(): void
    {
        $dto = new TemplateBlockPayloadDto(
            blockId: 'b1',
            title: 'Portada',
            description: null,
            defaultContent: ['type' => 'doc'],
            blockState: 'locked',
            sortOrder: 1,
            blockType: BlockType::Cover,
            pageBreakAfter: true,
            themeId: 't1',
            applyTheme: false,
        );

        $arr = $dto->toArray();

        // El bug de origen: el snapshot omitía estos campos. Garantizamos que están.
        $this->assertSame('cover', $arr['block_type']);
        $this->assertTrue($arr['page_break_after']);
        $this->assertSame('t1', $arr['theme_id']);
        $this->assertFalse($arr['apply_theme']);
        $this->assertSame('locked', $arr['block_state']);
    }

    public function test_block_type_por_defecto_es_content(): void
    {
        $dto = new TemplateBlockPayloadDto(
            blockId: 'b2',
            title: 'Texto',
            description: null,
            defaultContent: null,
            blockState: 'editable',
            sortOrder: 2,
        );

        $this->assertSame('content', $dto->toArray()['block_type']);
    }
}
