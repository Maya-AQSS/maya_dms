<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Document;
use App\Models\Process;
use App\Models\Template;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maya\Messaging\Publishers\AuditPublisher;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    private const DEFAULT_PROCESS_ID = '00000000-0000-0000-0000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        // Evita dependencias de RabbitMQ en tests HTTP/Feature cuando se disparan listeners de auditoría.
        $auditPublisher = Mockery::mock(AuditPublisher::class);
        $auditPublisher->shouldIgnoreMissing();
        $this->app->instance(AuditPublisher::class, $auditPublisher);

        // En tests legacy hay forceCreate() de Template sin process_id.
        // Mientras se migra el suite, inyectamos un proceso por defecto.
        if (Schema::hasTable('processes')) {
            DB::table('processes')->updateOrInsert(
                ['id' => self::DEFAULT_PROCESS_ID],
                [
                    'code' => 'DEFAULT_PROCESS',
                    'name' => 'Proceso por defecto',
                    'alias' => 'default',
                ],
            );
        }

        Template::creating(function (Template $template): void {
            if (! empty($template->process_id)) {
                return;
            }

            $processId = Process::query()->value('id');

            if ($processId === null) {
                $processId = self::DEFAULT_PROCESS_ID;

                DB::table('processes')->updateOrInsert(
                    ['id' => $processId],
                    [
                        'code' => 'DEFAULT_PROCESS',
                        'name' => 'Proceso por defecto',
                        'alias' => 'default',
                    ],
                );
            }

            $template->process_id = $processId;
        });

        Document::creating(function (Document $document): void {
            if (! empty($document->process_id)) {
                return;
            }

            $processId = null;

            if (! empty($document->template_id)) {
                $processId = Template::query()
                    ->whereKey($document->template_id)
                    ->value('process_id');
            }

            if ($processId === null) {
                $processId = Process::query()->value('id') ?? self::DEFAULT_PROCESS_ID;
            }

            $document->process_id = $processId;
        });
    }
}
