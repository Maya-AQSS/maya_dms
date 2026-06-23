<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DMS-B11: el batch findNamesByIds resuelve N nombres en una sola consulta
 * y conserva exactamente la semántica de findNameById (null para inexistentes
 * o nombre vacío), pero como claves ausentes en el mapa.
 */
final class UserDirectoryFindNamesByIdsTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): UserDirectoryRepositoryInterface
    {
        return app(UserDirectoryRepositoryInterface::class);
    }

    private function seedUser(string $id, string $name): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => substr($id, 0, 8).'@test.local',
            'is_active' => true,
        ]);
    }

    public function test_resolves_multiple_names_in_one_map(): void
    {
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();
        $this->seedUser($a, 'Ana Pérez');
        $this->seedUser($b, '  Bruno  ');

        $names = $this->repo()->findNamesByIds([$a, $b]);

        $this->assertSame('Ana Pérez', $names[$a]);
        $this->assertSame('Bruno', $names[$b]); // trim aplicado, como findNameById
    }

    public function test_omits_missing_and_empty_names_matching_find_name_by_id(): void
    {
        $present = (string) Str::uuid();
        $blank = (string) Str::uuid();
        $missing = (string) Str::uuid();
        $this->seedUser($present, 'Carla');
        $this->seedUser($blank, '   ');

        $names = $this->repo()->findNamesByIds([$present, $blank, $missing, '', 'x', 'x']);

        // Claves ausentes => el caller las interpreta como null (igual que findNameById).
        $this->assertSame('Carla', $names[$present] ?? null);
        $this->assertNull($names[$blank] ?? null);
        $this->assertNull($names[$missing] ?? null);
        $this->assertArrayNotHasKey('', $names);

        // Paridad explícita con el método de uno-en-uno para los mismos IDs.
        foreach ([$present, $blank, $missing] as $id) {
            $this->assertSame($this->repo()->findNameById($id), $names[$id] ?? null);
        }
    }

    public function test_empty_input_returns_empty_array_without_query(): void
    {
        $this->assertSame([], $this->repo()->findNamesByIds([]));
    }
}
