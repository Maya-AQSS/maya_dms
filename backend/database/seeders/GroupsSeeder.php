<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupsSeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->mockGroupsData();

        if ($data === []) {
            return;
        }

        $now = Carbon::now();

        if (Schema::hasTable('groups') && isset($data['groups'])) {
            $groups = array_map(static function (array $row) use ($now): array {
                $row['created_at'] ??= $now;
                $row['updated_at'] ??= $now;

                return $row;
            }, $data['groups']);

            DB::table('groups')->insertOrIgnore($groups);
        }

        if (Schema::hasTable('group_members') && isset($data['members'])) {
            $members = array_map(static function (array $row) use ($now): array {
                $row['created_at'] ??= $now;
                $row['updated_at'] ??= $now;

                return $row;
            }, $data['members']);

            DB::table('group_members')->insertOrIgnore($members);
        }
    }

    /**
     * Lee grupos y membresías mock desde database/data/groups_mock.php.
     */
    private function mockGroupsData(): array
    {
        $filePath = database_path('data/groups_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
