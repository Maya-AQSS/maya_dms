<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maya\Platform\Database\PostgresFdwMigration;

/**
 * Read-only projection of the dashboard's scheduled-rule contract
 * (maya_dashboard.public.v_notification_rules) via postgres_fdw (level B),
 * plus a local run-state table to compute cron due-ness without writing back.
 *
 * - testing: physical `notification_rules` table so the cron can be tested.
 * - local|staging|prod: FDW server + foreign table + pass-through view.
 */
return new class extends Migration
{
    private const VIEW_NAME = 'notification_rules';

    private const FDW_TABLE = 'notification_rules_fdw';

    private const FDW_SERVER = 'dashboard_server';

    private function isTestEnv(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }
        $db = config('database.connections.pgsql.database');

        return is_string($db) && str_ends_with($db, '_test');
    }

    public function up(): void
    {
        // Local run-state (always a physical local table; never written cross-DB).
        Schema::create('notification_rule_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('rule_id')->primary();
            $table->timestampTz('last_run_at');
        });

        if ($this->isTestEnv()) {
            $this->createTestingTable();

            return;
        }

        $this->setupFdw();
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rule_runs');

        if ($this->isTestEnv()) {
            DB::statement('DROP TABLE IF EXISTS '.self::VIEW_NAME);

            return;
        }

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);
        PostgresFdwMigration::dropFdwServerAndUserMapping(self::FDW_SERVER);
    }

    private function createTestingTable(): void
    {
        // Portable physical table for tests (sqlite/pgsql). The FDW view replaces
        // it in non-test environments.
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('evaluator_key', 128);
            $table->string('source_app', 64);
            $table->json('params')->nullable();
            $table->string('schedule_cron', 64);
            $table->json('audience')->nullable();
            $table->string('severity', 20)->nullable();
        });
    }

    private function setupFdw(): void
    {
        $host = (string) config('database.fdw.notification_rules.host', env('DB_HOST', 'maya_infra_postgres'));
        $port = (string) config('database.fdw.notification_rules.port', '5432');
        $database = (string) config('database.fdw.notification_rules.database', 'maya_dashboard');
        $username = (string) config('database.fdw.notification_rules.username', 'maya');
        $password = (string) config('database.fdw.notification_rules.password', 'secret');
        $schema = (string) config('database.fdw.notification_rules.schema', 'public');
        $source = (string) config('database.fdw.notification_rules.table', 'v_notification_rules');

        if (! PostgresFdwMigration::ensurePostgresFdwExtension('notification_rules')) {
            return;
        }

        PostgresFdwMigration::createFdwServerWithUserMapping(
            self::FDW_SERVER,
            $host,
            $port,
            $database,
            $username,
            $password,
        );

        $foreignColumnsSql = 'id BIGINT, evaluator_key VARCHAR(128), source_app VARCHAR(64), '
            .'params JSONB, schedule_cron VARCHAR(64), audience JSONB, severity VARCHAR(20)';

        $viewSelectSql = 'id, evaluator_key, source_app, params, schedule_cron, audience, severity';

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            $foreignColumnsSql,
            $viewSelectSql,
            self::FDW_SERVER,
            $schema,
            $source,
        );
    }
};
