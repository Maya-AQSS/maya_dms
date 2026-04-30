<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Team;
use App\Models\Template;
use App\Models\TemplateVersion;
use Database\Seeders\PermissionsSeeder;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class GlobalScopesIsolationTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
    }

    private function seedTemplateAndDocument(string $creatorId): array
    {
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'              => $templateId,
            'name'            => 'Plantilla Aislada',
            'description'     => null,
            'study_id'        => null,
            'created_by'      => $creatorId,
            'status'          => 'draft',
            'version'         => 1,
            'review_stages'   => 0,
            'review_mode'     => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id'               => $documentId,
            'template_id'      => $templateId,
            'title'            => 'Documento Privado',
            'study_id'         => null,
            'created_by'       => $creatorId,
            'owner_id'         => $creatorId,
            'status'           => 'draft',
            'current_version'  => 1,
            'submitted_at'     => null,
            'published_at'     => null,
        ]);

        return [$templateId, $documentId];
    }

    /**
     * @param  list<string>  $realmRoles
     * @param  array<string, mixed>  $extraClaims
     */
    private function buildAuthTokensForUser(
        string $userId,
        array $realmRoles = [],
        array $extraClaims = [],
    ): string {
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-iso-'.$userId,
            $userId,
            'test-issuer',
            'test-audience',
            $realmRoles,
            $extraClaims,
        );

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        return $token;
    }

    /**
     * Escenario 4: Test de aislamiento entre usuarios (User B trata de acceder a Doc de User A) -> 404 NO ENCONTRADO
     */
    public function test_user_b_accessing_user_a_document_returns_404(): void
    {
        $userA = 'user-a-uuid-123';
        $userB = 'user-b-uuid-999';

        [$templateId, $documentId] = $this->seedTemplateAndDocument($userA);

        $tokenA = $this->buildAuthTokensForUser($userA);

        // User A debe poder ver su propio documento
        $this->getJson(
            "/api/v1/documents/{$documentId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertSuccessful();

        $tokenB = $this->buildAuthTokensForUser($userB);

        // Limpiar el estado de autenticación para asegurar que el segundo request sea totalmente independiente
        auth()->forgetUser();

        // User B no debe encontrarlo, arrojando 404 para evitar IDOR (information disclosure)
        $this->getJson(
            "/api/v1/documents/{$documentId}",
            ['Authorization' => 'Bearer '.$tokenB],
        )->assertNotFound();
    }
    /**
     * Test de aislamiento para Plantillas (User B trata de acceder a Template de User A) -> 404 NO ENCONTRADO
     */
    public function test_user_b_accessing_user_a_template_returns_404(): void
    {
        $userA = 'user-a-uuid-123';
        $userB = 'user-b-uuid-999';

        [$templateId] = $this->seedTemplateAndDocument($userA);

        $this->assignUserPermissions($userA, ['templates.read']);

        $tokenA = $this->buildAuthTokensForUser($userA);

        // User A debe poder ver su propia plantilla
        $this->getJson(
            "/api/v1/templates/{$templateId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertSuccessful();

        $tokenB = $this->buildAuthTokensForUser($userB);
        auth()->forgetUser();

        // User B no debe encontrarla
        $this->getJson(
            "/api/v1/templates/{$templateId}",
            ['Authorization' => 'Bearer '.$tokenB],
        )->assertNotFound();
    }

    /**
     * Sin CRUD de equipos en API: el aislamiento se refleja vía plantillas de visibilidad por equipo.
     */
    public function test_teacher_without_team_membership_cannot_view_team_scoped_template(): void
    {
        $userA = 'user-a-uuid-123';
        $userB = 'user-b-uuid-999';

        $teamId = (string) Str::uuid();
        Team::query()->forceCreate([
            'id' => $teamId,
            'name' => 'Equipo privado',
            'description' => null,
            'owner_id' => $userA,
            'is_department' => false,
        ]);

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla de equipo',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Team->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => $teamId,
            'created_by' => $userA,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $this->assignUserPermissions($userA, ['templates.read']);

        $tokenA = $this->buildAuthTokensForUser($userA);
        $this->getJson(
            "/api/v1/templates/{$templateId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertSuccessful();

        $tokenB = $this->buildAuthTokensForUser($userB, ['teacher']);
        auth()->forgetUser();

        $this->getJson(
            "/api/v1/templates/{$templateId}",
            ['Authorization' => 'Bearer '.$tokenB],
        )->assertNotFound();
    }

    public function test_user_b_accessing_user_a_comment_returns_404(): void
    {
        $userA = 'user-a-uuid-123';
        $userB = 'user-b-uuid-999';

        [, $documentId] = $this->seedTemplateAndDocument($userA);
        $commentId = (string) Str::uuid();

        Comment::query()->forceCreate([
            'id'               => $commentId,
            'commentable_type' => Document::class,
            'commentable_id'   => $documentId,
            'blockable_type'   => null,
            'blockable_id'     => null,
            'parent_id'        => null,
            'author_id'        => $userA,
            'body'             => 'Comentario privado',
            'resolved'         => false,
            'resolved_by'      => null,
            'resolved_at'      => null,
        ]);

        $tokenA = $this->buildAuthTokensForUser($userA);
        $this->getJson(
            "/api/v1/comments/{$commentId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertSuccessful();

        $tokenB = $this->buildAuthTokensForUser($userB);
        auth()->forgetUser();

        $this->getJson(
            "/api/v1/comments/{$commentId}",
            ['Authorization' => 'Bearer '.$tokenB],
        )->assertNotFound();
    }

    public function test_comment_with_invalid_commentable_type_returns_422(): void
    {
        $userA = 'user-a-uuid-123';
        [, $documentId] = $this->seedTemplateAndDocument($userA);
        $commentId = (string) Str::uuid();

        Comment::query()->forceCreate([
            'id'               => $commentId,
            'commentable_type' => 'Invalid\\Type',
            'commentable_id'   => $documentId,
            'blockable_type'   => null,
            'blockable_id'     => null,
            'parent_id'        => null,
            'author_id'        => $userA,
            'body'             => 'Comentario con tipo inválido',
            'resolved'         => false,
            'resolved_by'      => null,
            'resolved_at'      => null,
        ]);

        $tokenA = $this->buildAuthTokensForUser($userA);
        $this->getJson(
            "/api/v1/comments/{$commentId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tipo de recurso de comentario no soportado.');
    }

    public function test_cannot_reply_to_soft_deleted_parent_comment(): void
    {
        $userA = 'user-a-uuid-123';
        [$templateId] = $this->seedTemplateAndDocument($userA);
        $parentId = (string) Str::uuid();

        Comment::query()->forceCreate([
            'id'               => $parentId,
            'commentable_type' => Template::class,
            'commentable_id'   => $templateId,
            'blockable_type'   => null,
            'blockable_id'     => null,
            'parent_id'        => null,
            'author_id'        => $userA,
            'body'             => 'Comentario padre',
            'resolved'         => false,
            'resolved_by'      => null,
            'resolved_at'      => null,
        ]);

        Comment::withoutGlobalScopes()->whereKey($parentId)->firstOrFail()->delete();

        $tokenA = $this->buildAuthTokensForUser($userA);

        $this->postJson(
            "/api/v1/templates/{$templateId}/comments",
            [
                'body' => 'Respuesta inválida',
                'parent_id' => $parentId,
            ],
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_owner_can_create_and_resolve_template_comment_with_polymorphic_contract(): void
    {
        $userA = 'user-a-uuid-123';
        [$templateId] = $this->seedTemplateAndDocument($userA);
        $tokenA = $this->buildAuthTokensForUser($userA);

        $create = $this->postJson(
            "/api/v1/templates/{$templateId}/comments",
            [
                'body' => 'Comentario para validar flujo',
            ],
            ['Authorization' => 'Bearer '.$tokenA],
        );

        $create->assertCreated()
            ->assertJsonPath('data.commentable_type', Template::class)
            ->assertJsonPath('data.commentable_id', $templateId)
            ->assertJsonPath('data.commentable_version', 1);

        $commentId = (string) $create->json('data.id');

        $this->patchJson(
            "/api/v1/comments/{$commentId}/resolve",
            [],
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertOk()
            ->assertJsonPath('data.resolved', true)
            ->assertJsonPath('data.resolved_by', $userA);
    }

    public function test_owner_can_list_document_comments_with_polymorphic_contract(): void
    {
        $userA = 'user-a-uuid-123';
        [, $documentId] = $this->seedTemplateAndDocument($userA);
        $tokenA = $this->buildAuthTokensForUser($userA);
        $commentId = (string) Str::uuid();

        Comment::query()->forceCreate([
            'id'                  => $commentId,
            'commentable_type'    => Document::class,
            'commentable_id'      => $documentId,
            'commentable_version' => 1,
            'blockable_type'      => null,
            'blockable_id'        => null,
            'parent_id'           => null,
            'author_id'           => $userA,
            'body'                => 'Comentario documento',
            'resolved'            => false,
            'resolved_by'         => null,
            'resolved_at'         => null,
        ]);

        $this->getJson(
            "/api/v1/documents/{$documentId}/comments",
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $commentId)
            ->assertJsonPath('data.0.commentable_type', Document::class)
            ->assertJsonPath('data.0.commentable_id', $documentId);
    }

    public function test_template_comments_are_hidden_and_blocked_after_first_publish(): void
    {
        $userA = 'user-a-uuid-123';
        [$templateId] = $this->seedTemplateAndDocument($userA);
        $tokenA = $this->buildAuthTokensForUser($userA);
        $commentId = (string) Str::uuid();

        Comment::query()->forceCreate([
            'id'                  => $commentId,
            'commentable_type'    => Template::class,
            'commentable_id'      => $templateId,
            'commentable_version' => 1,
            'blockable_type'      => null,
            'blockable_id'        => null,
            'parent_id'           => null,
            'author_id'           => $userA,
            'body'                => 'Comentario previo a publicar',
            'resolved'            => false,
            'resolved_by'         => null,
            'resolved_at'         => null,
        ]);

        TemplateVersion::query()->forceCreate([
            'id'             => (string) Str::uuid(),
            'template_id'    => $templateId,
            'version_number' => 1,
            'blocks_snapshot'=> [],
            'changelog'      => 'Publicacion inicial',
            'published_by'   => $userA,
            'published_at'   => now(),
        ]);

        $this->getJson(
            "/api/v1/templates/{$templateId}/comments",
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson(
            "/api/v1/comments/{$commentId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertNotFound();

        $this->postJson(
            "/api/v1/templates/{$templateId}/comments",
            ['body' => 'Nuevo comentario bloqueado'],
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertNotFound();

        $this->patchJson(
            "/api/v1/comments/{$commentId}/resolve",
            [],
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertNotFound();
    }

    public function test_document_comments_are_hidden_and_blocked_after_first_publish(): void
    {
        $userA = 'user-a-uuid-123';
        [, $documentId] = $this->seedTemplateAndDocument($userA);
        $tokenA = $this->buildAuthTokensForUser($userA);
        $commentId = (string) Str::uuid();

        Comment::query()->forceCreate([
            'id'                  => $commentId,
            'commentable_type'    => Document::class,
            'commentable_id'      => $documentId,
            'commentable_version' => 1,
            'blockable_type'      => null,
            'blockable_id'        => null,
            'parent_id'           => null,
            'author_id'           => $userA,
            'body'                => 'Comentario previo a publicar doc',
            'resolved'            => false,
            'resolved_by'         => null,
            'resolved_at'         => null,
        ]);

        DocumentVersion::query()->forceCreate([
            'id'             => (string) Str::uuid(),
            'document_id'    => $documentId,
            'version_number' => 1,
            'trigger_event'  => 'published',
            'triggered_by'   => $userA,
            'snapshot_data'  => ['document' => ['id' => $documentId]],
            'notes'          => 'Publicacion inicial documento',
            'is_immutable'   => true,
            'created_at'     => now(),
        ]);

        $this->getJson(
            "/api/v1/documents/{$documentId}/comments",
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson(
            "/api/v1/comments/{$commentId}",
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertNotFound();

        $this->postJson(
            "/api/v1/documents/{$documentId}/comments",
            ['body' => 'Nuevo comentario bloqueado'],
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertNotFound();

        $this->patchJson(
            "/api/v1/comments/{$commentId}/resolve",
            [],
            ['Authorization' => 'Bearer '.$tokenA],
        )->assertNotFound();
    }

    public function test_comment_store_rejects_multiple_block_identifiers(): void
    {
        $userA = 'user-a-uuid-123';
        [$templateId] = $this->seedTemplateAndDocument($userA);
        $tokenA = $this->buildAuthTokensForUser($userA);

        $this->postJson(
            "/api/v1/templates/{$templateId}/comments",
            [
                'body' => 'Comentario invalido por bloque ambiguo',
                'blockable_id' => (string) Str::uuid(),
                'template_block_id' => (string) Str::uuid(),
            ],
            ['Authorization' => 'Bearer '.$tokenA],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['blockable_id']);
    }

    /**
     * Test de Fail-Closed: Un usuario no autenticado no debe ver NADA
     */
    public function test_unauthenticated_user_returns_nothing(): void
    {
        $userA = 'user-a-uuid-123';
        $this->seedTemplateAndDocument($userA);

        // Sin token de autorización
        $this->getJson("/api/v1/documents")
            ->assertStatus(401);

        // Verificar directamente en el modelo (comportamiento de "fail-closed")
        // Como no hay sesión auth, el scope debe inyectar 1=0
        $this->assertEquals(0, Document::count());
        $this->assertEquals(0, Template::count());
    }
}
