<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;
use App\Support\DocumentAcademicContextMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentAcademicContextMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('processes')->insertOrIgnore([
            'id' => '00000000-0000-0000-0000-000000000001',
            'code' => 'DEFAULT_PROCESS',
            'name' => 'Proceso por defecto',
            'alias' => 'default',
        ]);
    }

    public function test_matches_when_document_study_type_is_in_user_profile_lists(): void
    {
        $studyTypeId = (string) Str::uuid();
        $user = new JwtUser([
            'id' => (string) Str::uuid(),
            'study_type_ids' => [$studyTypeId],
        ]);
        $document = $this->makePublishedDocument($studyTypeId);

        $this->assertTrue(DocumentAcademicContextMatcher::matches($user, $document));
    }

    public function test_matches_via_enrollment_tables_when_profile_lists_are_empty(): void
    {
        $userId = (string) Str::uuid();
        $studyTypeId = (string) Str::uuid();
        $user = new JwtUser(['id' => $userId]);

        DB::table('user_study_types')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'study_type_id' => $studyTypeId,
        ]);

        $document = $this->makePublishedDocument($studyTypeId);

        $this->assertTrue(DocumentAcademicContextMatcher::matches($user, $document));
    }

    public function test_does_not_match_when_document_has_no_overlapping_academic_context(): void
    {
        $user = new JwtUser([
            'id' => (string) Str::uuid(),
            'module_ids' => [(string) Str::uuid()],
        ]);
        $document = $this->makePublishedDocument((string) Str::uuid());

        $this->assertFalse(DocumentAcademicContextMatcher::matches($user, $document));
    }

    private function makePublishedDocument(string $studyTypeId): Document
    {
        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla matcher',
            'description' => null,
            'visibility_level' => 'global',
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'published',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $document = Document::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'title' => 'Doc',
            'study_type_id' => $studyTypeId,
            'created_by' => (string) Str::uuid(),
            'owner_id' => (string) Str::uuid(),
            'status' => 'published',
        ]);

        return $document->refresh();
    }
}
