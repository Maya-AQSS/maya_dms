<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->mockTeamsData();

        if ($data === []) {
            return;
        }

        $now = Carbon::now();

        $teamsWrittable = $this->writableTeamsCatalogTable();
        if ($teamsWrittable !== null && isset($data['teams'])) {
            $teams = array_map(static function (array $row) use ($now): array {
                $row['created_at'] ??= $now;
                $row['updated_at'] ??= $now;

                return $row;
            }, $data['teams']);

            DB::table($teamsWrittable)->insertOrIgnore($teams);
        }

        if (Schema::hasTable('team_members') && isset($data['members'])) {
            $members = array_map(static function (array $row) use ($now): array {
                $row['created_at'] ??= $now;
                $row['updated_at'] ??= $now;

                return $row;
            }, $data['members']);

            DB::table('team_members')->insertOrIgnore($members);
        }
    }

    /**
     * Tabla donde se pueden insertar filas del catálogo de equipos (mocks).
     * - Local FDW: `teams_source`.
     * - Testing: `teams` (tabla física).
     * - Producción con solo vista `teams`: null (catálogo remoto, sin mocks desde aquí).
     */
    private function writableTeamsCatalogTable(): ?string
    {
        if (Schema::hasTable('teams_source')) {
            return 'teams_source';
        }

        if (! Schema::hasTable('teams')) {
            return null;
        }

        return $this->teamsCatalogIsPhysicalTable('teams') ? 'teams' : null;
    }

    private function teamsCatalogIsPhysicalTable(string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return true;
        }

        if ($driver !== 'pgsql') {
            return true;
        }

        $row = DB::selectOne(
            'SELECT table_type FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            ['public', $name]
        );

        if ($row === null) {
            return false;
        }

        return strtoupper((string) $row->table_type) === 'BASE TABLE';
    }

    /**
     * Lee equipos y membresías mock desde database/data/teams_mock.php.
     */
    private function mockTeamsData(): array
    {
        $filePath = database_path('data/teams_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
