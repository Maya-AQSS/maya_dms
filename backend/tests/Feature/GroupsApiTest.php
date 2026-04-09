<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Services\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class GroupsApiTest extends TestCase
{
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
            'auth.group_management_roles' => ['manager', 'admin'],
        ]);

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * @param  list<string>  $realmRoles
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $realmRoles = []): array
    {
        auth()->forgetUser();

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
            $realmRoles,
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_manager_can_create_list_show_update_and_delete_group(): void
    {
        $managerId = (string) Str::uuid();
        $headers = $this->authHeaders($managerId, ['admin']);

        $create = $this->postJson('/api/v1/groups', [
            'name' => 'Grupo QA',
            'description' => 'Desc',
        ], $headers);

        $create->assertCreated();
        $groupId = $create->json('data.id');
        $this->assertNotEmpty($groupId);

        $this->getJson('/api/v1/groups', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Grupo QA');

        $this->getJson("/api/v1/groups/{$groupId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Grupo QA');

        $this->putJson("/api/v1/groups/{$groupId}", [
            'name' => 'Grupo QA 2',
            'description' => null,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Grupo QA 2')
            ->assertJsonPath('data.description', null);

        $this->deleteJson("/api/v1/groups/{$groupId}", [], $headers)
            ->assertNoContent();

        $this->assertSoftDeleted('groups', ['id' => $groupId]);
    }

    public function test_viewer_cannot_create_or_manage_members_foreign_mutations_are_404(): void
    {
        $ownerId = (string) Str::uuid();
        $outsiderId = (string) Str::uuid();

        $group = Group::withoutGlobalScopes()->create([
            'name' => 'G propietario',
            'description' => null,
            'owner_id' => $ownerId,
        ]);

        $this->postJson('/api/v1/groups', ['name' => 'Nuevo'], $this->authHeaders($outsiderId, ['viewer']))
            ->assertForbidden();

        // Sin pertenencia al grupo: el global scope devuelve 404 (no IDOR).
        $this->putJson("/api/v1/groups/{$group->id}", ['name' => 'Hack'], $this->authHeaders($outsiderId, ['viewer']))
            ->assertNotFound();

        $this->deleteJson("/api/v1/groups/{$group->id}", [], $this->authHeaders($outsiderId, ['viewer']))
            ->assertNotFound();

        // Con CACHE_STORE=redis el perfil jwt_user:* puede interferir entre peticiones del mismo test.
        auth()->forgetUser();
        Cache::flush();

        $newMember = (string) Str::uuid();
        // Propietario sin rol de gestión no puede tocar miembros.
        $this->postJson("/api/v1/groups/{$group->id}/members", [
            'user_id' => $newMember,
        ], $this->authHeaders($ownerId, ['viewer']))
            ->assertForbidden();

        $this->deleteJson("/api/v1/groups/{$group->id}/members/{$newMember}", [], $this->authHeaders($ownerId, ['viewer']))
            ->assertForbidden();
    }

    public function test_member_without_management_role_cannot_update_or_delete_visible_group(): void
    {
        $ownerId = (string) Str::uuid();
        $memberId = (string) Str::uuid();

        $group = Group::withoutGlobalScopes()->create([
            'name' => 'Compartido',
            'description' => null,
            'owner_id' => $ownerId,
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $memberId,
            'role' => 'member',
        ]);

        $this->putJson("/api/v1/groups/{$group->id}", ['name' => 'Cambio'], $this->authHeaders($memberId, ['viewer']))
            ->assertForbidden();

        $this->deleteJson("/api/v1/groups/{$group->id}", [], $this->authHeaders($memberId, ['viewer']))
            ->assertForbidden();
    }

    public function test_authenticated_user_can_list_groups_they_belong_to_without_management_role(): void
    {
        $ownerId = (string) Str::uuid();
        $memberId = (string) Str::uuid();

        $group = Group::withoutGlobalScopes()->create([
            'name' => 'Solo miembro',
            'description' => null,
            'owner_id' => $ownerId,
        ]);

        GroupMember::query()->create([
            'group_id' => $group->id,
            'user_id' => $memberId,
            'role' => 'member',
        ]);

        $headers = $this->authHeaders($memberId, ['viewer']);

        $this->getJson('/api/v1/groups', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_view_foreign_group_returns_404(): void
    {
        $ownerA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $group = Group::withoutGlobalScopes()->create([
            'name' => 'Privado A',
            'description' => null,
            'owner_id' => $ownerA,
        ]);

        $headersB = $this->authHeaders($userB, ['viewer']);

        $this->getJson("/api/v1/groups/{$group->id}", $headersB)
            ->assertNotFound();
    }

    public function test_add_and_remove_members_nn_relation(): void
    {
        $managerId = (string) Str::uuid();
        $u1 = (string) Str::uuid();
        $u2 = (string) Str::uuid();
        $headers = $this->authHeaders($managerId, ['admin']);

        $create = $this->postJson('/api/v1/groups', ['name' => 'G NN'], $headers);
        $groupId = $create->json('data.id');

        $this->postJson("/api/v1/groups/{$groupId}/members", [
            'user_ids' => [$u1, $u2],
        ], $headers)->assertOk();

        $this->assertDatabaseCount('group_members', 2);

        $this->deleteJson("/api/v1/groups/{$groupId}/members/{$u1}", [], $headers)
            ->assertNoContent();

        $this->assertDatabaseCount('group_members', 1);
        $this->assertDatabaseHas('group_members', [
            'group_id' => $groupId,
            'user_id' => $u2,
        ]);
    }

    public function test_user_can_belong_to_multiple_groups(): void
    {
        $managerId = (string) Str::uuid();
        $sharedUser = (string) Str::uuid();
        $mgr = $this->authHeaders($managerId, ['admin']);

        $g1 = $this->postJson('/api/v1/groups', ['name' => 'G1'], $mgr)->json('data.id');
        $g2 = $this->postJson('/api/v1/groups', ['name' => 'G2'], $mgr)->json('data.id');

        $this->postJson("/api/v1/groups/{$g1}/members", ['user_id' => $sharedUser], $mgr)->assertOk();
        $this->postJson("/api/v1/groups/{$g2}/members", ['user_id' => $sharedUser], $mgr)->assertOk();

        $userHeaders = $this->authHeaders($sharedUser, ['viewer']);
        $this->getJson('/api/v1/groups', $userHeaders)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_delete_group_removes_member_rows_but_does_not_touch_other_groups(): void
    {
        $managerId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $mgr = $this->authHeaders($managerId, ['admin']);

        $g1 = $this->postJson('/api/v1/groups', ['name' => 'A'], $mgr)->json('data.id');
        $g2 = $this->postJson('/api/v1/groups', ['name' => 'B'], $mgr)->json('data.id');

        $this->postJson("/api/v1/groups/{$g1}/members", ['user_id' => $memberId], $mgr)->assertOk();
        $this->postJson("/api/v1/groups/{$g2}/members", ['user_id' => $memberId], $mgr)->assertOk();

        $this->deleteJson("/api/v1/groups/{$g1}", [], $mgr)->assertNoContent();

        $this->assertSoftDeleted('groups', ['id' => $g1]);
        $this->assertDatabaseMissing('group_members', ['group_id' => $g1]);
        $this->assertDatabaseHas('group_members', ['group_id' => $g2, 'user_id' => $memberId]);
    }

    public function test_index_with_many_members_stays_fast_on_sqlite(): void
    {
        $managerId = (string) Str::uuid();
        $headers = $this->authHeaders($managerId, ['admin']);

        $create = $this->postJson('/api/v1/groups', ['name' => 'G grande'], $headers);
        $groupId = $create->json('data.id');

        for ($i = 0; $i < 200; $i++) {
            GroupMember::query()->create([
                'group_id' => $groupId,
                'user_id' => (string) Str::uuid(),
                'role' => 'member',
            ]);
        }

        $start = microtime(true);
        $response = $this->getJson('/api/v1/groups?per_page=1', $headers);
        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertArrayHasKey('members', $response->json('data.0'));
        $this->assertCount(200, $response->json('data.0.members'));
        // Meta producción: &lt; 200 ms; en CI con SQLite in-memory dejamos margen para no ser frágil.
        $this->assertLessThan(800, $elapsedMs, 'El listado con miembros eager-load no debe degradarse de forma extrema.');
    }
}
