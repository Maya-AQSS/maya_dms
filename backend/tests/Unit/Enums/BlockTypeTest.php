<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\BlockType;
use Tests\TestCase;

class BlockTypeTest extends TestCase
{
    public function test_structural_blocks_do_not_require_body_content(): void
    {
        // Portada/índice/hoja en blanco no llevan cuerpo Tiptap → exentos de la
        // invariante "modificable/bloqueado no puede estar vacío".
        $this->assertFalse(BlockType::Cover->requiresBodyContent());
        $this->assertFalse(BlockType::Index->requiresBodyContent());
        $this->assertFalse(BlockType::Blank->requiresBodyContent());
    }

    public function test_content_block_requires_body_content(): void
    {
        $this->assertTrue(BlockType::Content->requiresBodyContent());
    }

    public function test_values_lists_every_case(): void
    {
        $this->assertSame(['content', 'cover', 'blank', 'index'], BlockType::values());
    }
}
