<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DocumentMigrationBlockDiffer;
use Tests\TestCase;

class DocumentMigrationBlockDifferTest extends TestCase
{
    private function differ(): DocumentMigrationBlockDiffer
    {
        return new DocumentMigrationBlockDiffer;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function block(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => 'text',
            'title' => 'Bloque '.$id,
            'default_content' => ['default-'.$id],
            'block_state' => 'editable',
            'mandatory' => false,
            'sort_order' => 0,
        ], $overrides);
    }

    public function test_unchanged_block_carries_old_content_and_target_metadata(): void
    {
        $source = [$this->block('a', ['sort_order' => 0])];
        $target = [$this->block('a', ['sort_order' => 0, 'default_content' => ['new-a']])];
        $oldContent = ['a' => ['user-typed-a']];

        $blocks = $this->differ()->diff($source, $target, $oldContent);

        $this->assertCount(1, $blocks);
        $b = $blocks[0];
        $this->assertSame('a', $b['template_block_id']);
        $this->assertFalse($b['new_block']);
        $this->assertFalse($b['removed_block']);
        $this->assertFalse($b['changed_block_state']);
        $this->assertFalse($b['locked']);
        $this->assertSame(['new-a'], $b['new_default_content']);
        $this->assertSame(['user-typed-a'], $b['old_content']);
        $this->assertSame(['default-a'], $b['old_default_content']);
    }

    public function test_added_block_has_no_old_content(): void
    {
        $source = [$this->block('a')];
        $target = [$this->block('a'), $this->block('b', ['sort_order' => 1])];

        $blocks = $this->differ()->diff($source, $target, ['a' => ['x']]);

        $added = collect($blocks)->firstWhere('template_block_id', 'b');
        $this->assertNotNull($added);
        $this->assertTrue($added['new_block']);
        $this->assertFalse($added['removed_block']);
        $this->assertNull($added['old_content']);
        $this->assertNull($added['old_default_content']);
    }

    public function test_removed_block_appears_last_with_old_content(): void
    {
        $source = [$this->block('a'), $this->block('gone', ['sort_order' => 1])];
        $target = [$this->block('a')];
        $oldContent = ['a' => ['ka'], 'gone' => ['kept-content']];

        $blocks = $this->differ()->diff($source, $target, $oldContent);

        $removed = collect($blocks)->firstWhere('template_block_id', 'gone');
        $this->assertNotNull($removed);
        $this->assertTrue($removed['removed_block']);
        $this->assertFalse($removed['new_block']);
        $this->assertNull($removed['block_state']);
        $this->assertSame(['kept-content'], $removed['old_content']);
        // Removed blocks are listed after all target blocks.
        $this->assertSame('gone', $blocks[array_key_last($blocks)]['template_block_id']);
    }

    public function test_block_state_change_to_locked_is_flagged(): void
    {
        $source = [$this->block('a', ['block_state' => 'editable'])];
        $target = [$this->block('a', ['block_state' => 'locked'])];

        $blocks = $this->differ()->diff($source, $target, ['a' => ['x']]);

        $b = $blocks[0];
        $this->assertTrue($b['changed_block_state']);
        $this->assertSame('editable', $b['old_block_state']);
        $this->assertSame('locked', $b['block_state']);
        $this->assertTrue($b['locked']);
    }

    public function test_target_blocks_are_ordered_by_sort_order(): void
    {
        $source = [$this->block('a'), $this->block('b'), $this->block('c')];
        $target = [
            $this->block('c', ['sort_order' => 2]),
            $this->block('a', ['sort_order' => 0]),
            $this->block('b', ['sort_order' => 1]),
        ];

        $blocks = $this->differ()->diff($source, $target, []);

        $ids = array_column($blocks, 'template_block_id');
        $this->assertSame(['a', 'b', 'c'], $ids);
    }
}
