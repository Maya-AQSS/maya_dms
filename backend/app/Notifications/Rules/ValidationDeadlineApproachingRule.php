<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use App\Support\DocumentHeadSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;

/**
 * Notifica a propietarios sobre documentos cuya fecha de validación
 * vence en menos de 7 días y aún no están validados/publicados.
 */
final class ValidationDeadlineApproachingRule implements ScheduledNotificationRule
{
    private const DAYS_THRESHOLD = 7;

    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int
    {
        $count = 0;
        $days = (int) ($params['days'] ?? self::DAYS_THRESHOLD);

        try {
            $now = now();
            $deadlineStart = $now->copy();
            $deadlineEnd = $now->copy()->addDays($days);

            $expression = DocumentHeadSnapshot::jsonDocumentFieldExpression('ev', 'delivery_deadline');

            $documents = DB::table('documents')
                ->join('entity_versions as ev', 'ev.id', '=', 'documents.head_entity_version_id')
                ->select([
                    'documents.id',
                    'documents.created_at',
                    'ev.snapshot_data',
                ])
                ->whereRaw("({$expression})::TIMESTAMP BETWEEN ? AND ?", [
                    $deadlineStart->toDateTimeString(),
                    $deadlineEnd->toDateTimeString(),
                ])
                ->whereRaw(DocumentHeadSnapshot::jsonDocumentFieldExpression('ev', 'status')." != 'published'")
                ->get();

            foreach ($documents as $doc) {
                try {
                    $snapshot = json_decode($doc->snapshot_data, true, 512, JSON_THROW_ON_ERROR);
                    $docData = $snapshot[DocumentHeadSnapshot::JSON_DOCUMENT_KEY] ?? null;

                    if (! $docData) {
                        continue;
                    }

                    $documentId = $docData['id'] ?? null;
                    $title = $docData['title'] ?? 'Sin título';
                    $ownerId = $docData['owner_id'] ?? null;
                    $deadline = $docData['delivery_deadline'] ?? null;

                    if (! $documentId || ! $ownerId || ! $deadline) {
                        continue;
                    }

                    $publisher->send(
                        type: 'dms.validation_deadline_approaching',
                        recipientId: $ownerId,
                        severity: $severity,
                        titleKey: 'notifications.dms.validation_deadline_approaching.title',
                        bodyKey: 'notifications.dms.validation_deadline_approaching.body',
                        params: [
                            'document_id' => $documentId,
                            'document_title' => $title,
                            'deadline' => $deadline,
                        ],
                        scope: 'user',
                        channels: ['app'],
                        app: 'dms',
                    );

                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('notifications.validation_deadline.document_processing_failed', [
                        'document_id' => $doc->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('notifications.validation_deadline.rule_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }
}
