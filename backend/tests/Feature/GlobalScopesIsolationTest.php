<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class GlobalScopesIsolationTest extends TestCase
{
    use BuildsTestJwt;
    use RefreshDatabase;

    private function seedTemplateAndDocument(string $creatorId): array
    {
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'              => $templateId,
            'name'            => 'Plantilla Aislada',
            'description'     => null,
            'study_id'        => null,
            'organization_id' => 'org-test',
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
            'organization_id'  => 'org-test',
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

    private function buildAuthTokensForUser(string $userId): string
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-iso-'.$userId, $userId);

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
