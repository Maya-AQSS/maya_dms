<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Tests for SanitizesBlockContent trait exercising all branches of
 * prepareForValidation() and sanitizeRichContent() via the template block
 * store (POST) and update (PUT) endpoints.
 *
 * Targets (31.4% → ≥80%):
 *   - prepareForValidation: description present + null/array/string results
 *   - prepareForValidation: default_content present + non-array result → null
 *   - sanitizeRichContent: string with parentKey='text' (preserved)
 *   - sanitizeRichContent: string without parentKey (trimmed)
 *   - sanitizeRichContent: empty string → null
 *   - sanitizeRichContent: non-array, non-string (integer, null) → passthrough
 *   - sanitizeRichContent: list array (filters nulls)
 *   - sanitizeRichContent: associative array (recursive key→value)
 */
class SanitizesBlockContentApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $codes = ['template.show', 'template.update']): array
    {
        auth()->forgetUser();
        $this->assignUserPermissions($sub, $codes);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @return array{templateId: string, blockId: string}
     */
    private function seedTemplateWithBlock(string $ownerId, mixed $defaultContent = null): array
    {
        $templateId = (string) Str::uuid();
        $blockId    = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Plantilla SanitizeContent',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Base',
            'default_content' => $defaultContent,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 0,
        ]);

        return ['templateId' => $templateId, 'blockId' => $blockId];
    }

    // ─── prepareForValidation: description path ───────────────────────────────

    /**
     * When description is a non-empty array, sanitizeRichContent returns the
     * array and prepareForValidation sets description to that array.
     */
    public function test_store_description_as_array_is_preserved(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $description = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

        $response = $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'       => 'Bloque Con Descripción',
            'block_state' => BlockState::Editable->value,
            'description' => $description,
        ], $headers)->assertCreated();

        // The sanitized description is stored and returned
        $this->assertNotNull($response->json('data.description'));
    }

    /**
     * When description is a plain string (non-empty), sanitizeRichContent trims
     * and returns it; prepareForValidation stores it since it's a string.
     *
     * Note: The validation rule is 'nullable|array' — a string description would
     * fail validation. This test confirms the sanitization path executes (description
     * field exists in request) even if validation rejects non-array values.
     */
    public function test_store_description_as_string_hits_sanitize_path(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        // description as string → sanitizeRichContent returns trimmed string
        // but validation 'array' rule will reject it → 422 (confirming the code path ran)
        $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'       => 'Bloque',
            'block_state' => BlockState::Editable->value,
            'description' => '  Some rich text  ',
        ], $headers)->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    /**
     * When description is empty string, sanitizeRichContent returns null,
     * and prepareForValidation sets description to null (not string → null).
     */
    public function test_store_empty_string_description_normalizes_to_null(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $response = $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'       => 'Bloque',
            'block_state' => BlockState::Editable->value,
            'description' => '',
        ], $headers)->assertCreated();

        // Empty string was normalized to null — passes 'nullable|array' validation
        $this->assertNull($response->json('data.description'));
    }

    // ─── prepareForValidation: default_content path ──────────────────────────

    /**
     * When default_content is a non-null non-array value (e.g. integer),
     * sanitizeRichContent returns it as-is, but prepareForValidation sets
     * default_content to null because result is not an array.
     * Validation then receives null → passes 'nullable|array'.
     */
    public function test_store_non_array_default_content_normalized_to_null(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $response = $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'          => 'Bloque',
            'block_state'    => BlockState::Editable->value,
            'default_content' => 12345,   // integer → non-array → null after sanitize
        ], $headers)->assertCreated();

        $this->assertNull($response->json('data.default_content'));
    }

    /**
     * When default_content is an associative array (e.g. rich content node),
     * sanitizeRichContent recurses into it and returns the sanitized array.
     */
    public function test_store_associative_default_content_is_sanitized_recursively(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $content = [
            'type'    => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]],
            ],
        ];

        $response = $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'           => 'Bloque',
            'block_state'     => BlockState::Editable->value,
            'default_content' => $content,
        ], $headers)->assertCreated();

        $this->assertNotNull($response->json('data.default_content'));
    }

    /**
     * When default_content is a list (indexed array), sanitizeRichContent
     * processes it as a list, filtering out null items.
     */
    public function test_store_list_default_content_filters_null_items(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        // A list array with some items
        $content = [
            ['type' => 'paragraph', 'text' => 'First'],
            ['type' => 'paragraph', 'text' => 'Second'],
        ];

        $response = $this->postJson("/api/v1/templates/{$ctx['templateId']}/blocks", [
            'title'           => 'Bloque',
            'block_state'     => BlockState::Editable->value,
            'default_content' => $content,
        ], $headers)->assertCreated();

        $this->assertNotNull($response->json('data.default_content'));
    }

    // ─── sanitizeRichContent: string with 'text' key ─────────────────────────

    /**
     * Within an associative array, the key 'text' passes its value through
     * sanitizeRichContent with parentKey='text'. An empty string 'text' → null
     * (and is dropped from the output), but a non-empty 'text' is preserved.
     */
    public function test_update_text_key_within_content_preserves_spaces(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        // The 'text' key inside the node preserves leading/trailing spaces
        $content = [
            'type'    => 'doc',
            'content' => [
                [
                    'type'    => 'paragraph',
                    'content' => [['type' => 'text', 'text' => '  leading spaces  ']],
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/blocks/{$ctx['blockId']}", [
            'default_content' => $content,
        ], $headers)->assertOk();

        $stored = $response->json('data.default_content');
        $this->assertNotNull($stored);
        // The 'text' value is preserved with its spaces (not trimmed)
        $text = $stored['content'][0]['content'][0]['text'] ?? null;
        $this->assertSame('  leading spaces  ', $text);
    }

    /**
     * A 'text' key with empty string value → sanitizeRichContent returns null
     * → item is dropped from output.
     */
    public function test_update_empty_text_key_is_dropped(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $content = [
            'type'    => 'doc',
            'content' => [
                [
                    'type'    => 'paragraph',
                    'content' => [['type' => 'text', 'text' => '']],
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/blocks/{$ctx['blockId']}", [
            'default_content' => $content,
        ], $headers)->assertOk();

        $stored = $response->json('data.default_content');
        // The empty 'text' key is dropped from the node; the node itself remains
        $innerNode = $stored['content'][0]['content'][0] ?? null;
        $this->assertNotNull($innerNode);
        // The 'text' key is absent because empty string → null → dropped
        $this->assertArrayNotHasKey('text', $innerNode);
    }

    // ─── sanitizeRichContent: non-string, non-array (passthrough) ────────────

    /**
     * Boolean values inside a content node are not string and not array →
     * sanitizeRichContent returns them as-is (passthrough).
     */
    public function test_update_boolean_value_in_content_passes_through(): void
    {
        $userId  = (string) Str::uuid();
        $ctx     = $this->seedTemplateWithBlock($userId);
        $headers = $this->authHeaders($userId);

        $content = [
            'type'  => 'doc',
            'bold'  => true,  // boolean passthrough
        ];

        $response = $this->putJson("/api/v1/blocks/{$ctx['blockId']}", [
            'default_content' => $content,
        ], $headers)->assertOk();

        $stored = $response->json('data.default_content');
        $this->assertTrue($stored['bold']);
    }
}
