<?php

namespace Tests\Unit\Support;

use App\Support\PublishedTemplateVersionMetaMerge;
use Tests\TestCase;

class PublishedTemplateVersionMetaMergeTest extends TestCase
{
    public function test_prefer_latest_meta_prefers_higher_version_and_entity_on_tie(): void
    {
        $entity = ['id' => 'e', 'version_number' => 2, 'changelog' => 'ce'];
        $legacy = ['id' => 'l', 'version_number' => 1, 'changelog' => 'cl'];
        $this->assertSame($entity, PublishedTemplateVersionMetaMerge::preferLatestMeta($entity, $legacy));

        $entityLow = ['id' => 'e', 'version_number' => 1, 'changelog' => 'ce'];
        $legacyHigh = ['id' => 'l', 'version_number' => 3, 'changelog' => 'cl'];
        $this->assertSame($legacyHigh, PublishedTemplateVersionMetaMerge::preferLatestMeta($entityLow, $legacyHigh));

        $this->assertSame($entity, PublishedTemplateVersionMetaMerge::preferLatestMeta($entity, [
            'id' => 'l2',
            'version_number' => 2,
            'changelog' => 'otro',
        ]));
    }
}
