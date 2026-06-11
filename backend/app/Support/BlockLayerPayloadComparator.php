<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Compares two block payload arrays for semantic equality, regardless of key order.
 * Extracted from the identical private methods in TemplateVersionBlockLayerWriter
 * and DocumentVersionBlockLayerWriter (D-01).
 */
final class BlockLayerPayloadComparator
{
    /**
     * Returns true if both payloads are semantically equal (key-order-insensitive,
     * recursive ksort before JSON-encoding).
     *
     * @param  array<string, mixed>  $prev
     * @param  array<string, mixed>  $curr
     */
    public static function equal(array $prev, array $curr): bool
    {
        return json_encode(self::normalizeForCompare($prev)) === json_encode(self::normalizeForCompare($curr));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeForCompare(array $data): array
    {
        ksort($data);
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::normalizeForCompare($v);
            }
        }

        return $data;
    }
}
