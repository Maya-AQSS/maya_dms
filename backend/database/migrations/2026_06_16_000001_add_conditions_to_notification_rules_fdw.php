<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maya\Platform\Database\PostgresFdwMigration;

/**
 * Adds the `conditions` column to the notification_rules projection so the
 * generic condition evaluator can read the structured rule payload.
 *
 * - testing: alters the physical stub table (created by the prior migration).
 * - local|staging|prod: drops and recreates the FDW foreign table + pass-through
 *   view with the extra column (schema change on foreign table requires DDL).
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
        if ($this->isTestEnv()) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->json('conditions')->nullable();
            });

            return;
        }

        $this->recreateFdw();
    }

    public function down(): void
    {
        if ($this->isTestEnv()) {
            Schema::table('notification_rules', function (Blueprint $table) {
                $table->dropColumn('conditions');
            });

            return;
        }

        // Restore FDW without conditions column.
        $this->recreateFdwWithoutConditions();
    }

    private function recreateFdw(): void
    {
        $host = (string) config('database.fdw.notification_rules.host', env('DB_HOST', 'maya_infra_postgres'));
        $port = (string) config('database.fdw.notification_rules.port', '5432');
        $database = (string) config('database.fdw.notification_rules.database', 'maya_dashboard');
        $username = (string) config('database.fdw.notification_rules.username', 'maya');
        $password = (string) config('database.fdw.notification_rules.password', 'secret');
        $schema = (string) config('database.fdw.notification_rules.schema', 'public');
        $source = (string) config('database.fdw.notification_rules.table', 'v_notification_rules');

        // Drop and recreate (ALTER FOREIGN TABLE ADD COLUMN also works but
        // recreating is simpler and avoids DDL drift).
        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);

        $foreignColumnsSql = 'id BIGINT, evaluator_key VARCHAR(128), source_app VARCHAR(64), '
            .'params JSONB, conditions JSONB, schedule_cron VARCHAR(64), audience JSONB, severity VARCHAR(20)';

        $viewSelectSql = 'id, evaluator_key, source_app, params, conditions, schedule_cron, audience, severity';

        PostgresFdwMigration::createForeignTableWithPassThroughView(
            self::VIEW_NAME,
            $foreignColumnsSql,
            $viewSelectSql,
            self::FDW_SERVER,
            $schema,
            $source,
        );
    }

    private function recreateFdwWithoutConditions(): void
    {
        $schema = (string) config('database.fdw.notification_rules.schema', 'public');
        $source = (string) config('database.fdw.notification_rules.table', 'v_notification_rules');

        PostgresFdwMigration::dropViewOrTableInPublic(self::VIEW_NAME);
        PostgresFdwMigration::dropForeignTableIfExists(self::FDW_TABLE);

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
