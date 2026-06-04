<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QA harness (non-production): seeds pending document reviews for a recipient so
 * the `dms.pending_validations_threshold` rule can be exercised through the real
 * FDW → evaluator → publish path. One review per existing document (UNIQUE
 * document_id+reviewer_id caps it at the number of documents); to fire, set the
 * rule's `threshold` below that count from the dashboard (level B configurable).
 */
class SeedRuleData extends Command
{
    protected $signature = 'notifications:seed-rule-data {--recipient= : Keycloak id of the reviewer}';

    protected $description = 'Siembra revisiones pendientes para disparar la regla de validaciones (solo no-producción)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('No disponible en producción.');

            return self::FAILURE;
        }

        $recipient = (string) ($this->option('recipient') ?? '');
        if ($recipient === '') {
            $recipient = (string) (User::query()->where('is_active', true)->value('id') ?? '');
        }
        if ($recipient === '') {
            $this->error('Indica --recipient=<keycloak_id>.');

            return self::FAILURE;
        }

        $docIds = DB::table('documents')->whereNull('deleted_at')->pluck('id');
        if ($docIds->isEmpty()) {
            $this->error('No hay documentos para asociar revisiones. Carga datos demo primero.');

            return self::FAILURE;
        }

        // Re-seed limpio para este revisor.
        DB::table('document_reviews')->where('reviewer_id', $recipient)->delete();

        $now = now();
        $inserted = 0;
        foreach ($docIds as $docId) {
            DB::table('document_reviews')->insert([
                'id' => (string) Str::uuid(),
                'document_id' => $docId,
                'reviewer_id' => $recipient,
                'stage' => 1,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        $this->info("Insertadas {$inserted} revisiones pendientes para {$recipient}.");
        $this->line("Para disparar: pon el threshold de 'dms.pending_validations_threshold' por debajo de {$inserted} (pestaña Reglas) y ejecuta: php artisan notifications:evaluate-rules");

        return self::SUCCESS;
    }
}
