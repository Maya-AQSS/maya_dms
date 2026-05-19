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

        $membersWritable = $this->writableTeamMembersTable();
        if ($membersWritable !== null && isset($data['members'])) {
            $members = array_map(static function (array $row) use ($now): array {
                $row['created_at'] ??= $now;
                $row['updated_at'] ??= $now;

                return $row;
            }, $data['members']);

            DB::table($membersWritable)->insertOrIgnore($members);
        }
    }

    /**
     * Tabla donde se pueden insertar filas del catálogo de equipos (mocks).
     * - Testing: `teams` (tabla física UUID, factories ok).
     * - Local/staging/prod: `teams` es VIEW pass-through sobre FDW Odoo →
     *   no se inserta (los datos reales vienen de Odoo `v_dms_teams`).
     * - Retro-compatible con `teams_source` si alguna instalación legacy
     *   aún la usa.
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
     * Tabla donde se pueden insertar membresías mock.
     * - Testing: `team_members` (tabla física UUID).
     * - Local/staging/prod: `team_members` es VIEW FDW → no-op (datos remotos).
     */
    private function writableTeamMembersTable(): ?string
    {
        if (Schema::hasTable('team_members_source')) {
            return 'team_members_source';
        }

        if (! Schema::hasTable('team_members')) {
            return null;
        }

        return $this->teamsCatalogIsPhysicalTable('team_members') ? 'team_members' : null;
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
