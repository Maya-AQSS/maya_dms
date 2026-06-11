<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\BlockLayerPayloadComparator;
use PHPUnit\Framework\TestCase;

class BlockLayerPayloadComparatorTest extends TestCase
{
    // ── Equality ────────────────────────────────────────────────────────────────

    public function test_equal_returns_true_for_identical_arrays(): void
    {
        $a = ['title' => 'Block', 'sort_order' => 1, 'content' => ['type' => 'doc']];
        $b = ['title' => 'Block', 'sort_order' => 1, 'content' => ['type' => 'doc']];

        $this->assertTrue(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_returns_true_when_key_order_differs(): void
    {
        $a = ['title' => 'Block', 'sort_order' => 1];
        $b = ['sort_order' => 1, 'title' => 'Block'];

        $this->assertTrue(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_returns_true_when_nested_key_order_differs(): void
    {
        $a = ['content' => ['z' => 2, 'a' => 1]];
        $b = ['content' => ['a' => 1, 'z' => 2]];

        $this->assertTrue(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_handles_deep_nesting(): void
    {
        $a = ['outer' => ['middle' => ['z' => 'last', 'a' => 'first']]];
        $b = ['outer' => ['middle' => ['a' => 'first', 'z' => 'last']]];

        $this->assertTrue(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_returns_true_for_empty_arrays(): void
    {
        $this->assertTrue(BlockLayerPayloadComparator::equal([], []));
    }

    // ── Inequality ──────────────────────────────────────────────────────────────

    public function test_equal_returns_false_when_values_differ(): void
    {
        $a = ['title' => 'Block A'];
        $b = ['title' => 'Block B'];

        $this->assertFalse(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_returns_false_when_keys_differ(): void
    {
        $a = ['title' => 'Block'];
        $b = ['name' => 'Block'];

        $this->assertFalse(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_returns_false_when_nested_values_differ(): void
    {
        $a = ['content' => ['type' => 'doc', 'version' => 1]];
        $b = ['content' => ['type' => 'doc', 'version' => 2]];

        $this->assertFalse(BlockLayerPayloadComparator::equal($a, $b));
    }

    public function test_equal_returns_false_when_one_is_empty(): void
    {
        $this->assertFalse(BlockLayerPayloadComparator::equal(['a' => 1], []));
        $this->assertFalse(BlockLayerPayloadComparator::equal([], ['a' => 1]));
    }

    public function test_equal_returns_false_when_extra_key_present(): void
    {
        $a = ['title' => 'Block', 'sort_order' => 1];
        $b = ['title' => 'Block', 'sort_order' => 1, 'extra' => 'value'];

        $this->assertFalse(BlockLayerPayloadComparator::equal($a, $b));
    }
}
