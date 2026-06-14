<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Contracts\UserDirectoryServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Mockery;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Endpoints de búsqueda en el directorio de usuarios
 * (UserController: index, owner-candidates, reviewer-candidates,
 * document-reviewer-candidates).
 *
 * Cubre la remediación Wave 1: autorización vía FormRequest (R6) y
 * validación de input vía FormRequest (R7).
 */
final class UserDirectorySearchTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    private string $userId;

    /** @var array<string, string> */
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->userId = (string) Str::uuid();
    }

    private function authenticate(array $permissions): void
    {
        $this->assignUserPermissions($this->userId, $permissions);

        auth()->forgetUser();
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($this->userId, 0, 8),
            $this->userId,
            'test-issuer',
            'test-audience',
            [],
            [],
        );
        $this->authHeaders = ['Authorization' => 'Bearer '.$token];
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/users?search=ab')->assertUnauthorized();
    }

    public function test_index_forbidden_without_template_or_document_show(): void
    {
        // dms.login con withAppLogin desactivado: sin template.show ni document.show.
        $this->assignUserPermissions($this->userId, ['dms.login'], withAppLogin: false);
        $this->authenticateWithoutAppLogin();

        $this->getJson('/api/v1/users?search=ab', $this->authHeaders)
            ->assertForbidden();
    }

    private function authenticateWithoutAppLogin(): void
    {
        auth()->forgetUser();
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($this->userId, 0, 8),
            $this->userId,
            'test-issuer',
            'test-audience',
            [],
            [],
        );
        $this->authHeaders = ['Authorization' => 'Bearer '.$token];
    }

    public function test_index_rejects_non_uuid_exclude_user_id(): void
    {
        $this->authenticate([]);

        $this->getJson('/api/v1/users?search=alice&exclude_user_id=not-a-uuid', $this->authHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('exclude_user_id');
    }

    public function test_index_returns_empty_for_short_term(): void
    {
        $this->authenticate([]);

        $this->getJson('/api/v1/users?search=a', $this->authHeaders)
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_index_delegates_to_service_with_validated_input(): void
    {
        $this->authenticate([]);

        $mock = Mockery::mock(UserDirectoryServiceInterface::class);
        $mock->shouldReceive('searchUsers')
            ->once()
            ->with('alice', 20, null)
            ->andReturn([
                ['id' => (string) Str::uuid(), 'name' => 'Alice', 'email' => 'a@x.test', 'role' => null],
            ]);
        $this->app->instance(UserDirectoryServiceInterface::class, $mock);

        $this->getJson('/api/v1/users?search=alice', $this->authHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_reviewer_candidates_forbidden_without_template_show(): void
    {
        $this->assignUserPermissions($this->userId, ['document.show'], withAppLogin: false);
        $this->authenticateWithoutAppLogin();

        $this->getJson('/api/v1/users/reviewer-candidates', $this->authHeaders)
            ->assertForbidden();
    }

    public function test_document_reviewer_candidates_forbidden_without_document_show(): void
    {
        $this->assignUserPermissions($this->userId, ['template.show'], withAppLogin: false);
        $this->authenticateWithoutAppLogin();

        $this->getJson('/api/v1/users/document-reviewer-candidates', $this->authHeaders)
            ->assertForbidden();
    }

    public function test_reviewer_candidates_passes_academic_filter_from_validated(): void
    {
        $this->authenticate([]);

        $studyTypeId = (string) Str::uuid();

        $mock = Mockery::mock(UserDirectoryServiceInterface::class);
        $mock->shouldReceive('searchTemplateReviewerCandidates')
            ->once()
            ->withArgs(function (string $search, int $limit, ?string $exclude, $filter) use ($studyTypeId) {
                return $search === ''
                    && $limit === 20
                    && $exclude === null
                    && $filter->studyTypeId === $studyTypeId;
            })
            ->andReturn([]);
        $this->app->instance(UserDirectoryServiceInterface::class, $mock);

        $this->getJson('/api/v1/users/reviewer-candidates?study_type_id='.$studyTypeId, $this->authHeaders)
            ->assertOk();
    }
}
