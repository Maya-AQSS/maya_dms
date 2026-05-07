<?php

use App\Models\Document;
use App\Support\DocumentHeadSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'head_entity_version_id')) {
                $table->uuid('head_entity_version_id')->nullable()->after('template_version_id');
            }
        });

        if (! Schema::hasTable('entity_versions')) {
            return;
        }

        foreach (DB::table('documents')->orderBy('id')->cursor() as $documentRow) {
            if ($documentRow->head_entity_version_id !== null) {
                continue;
            }

            $snapshot = DocumentHeadSnapshot::buildPayloadFromLegacyRow(
                $documentRow,
                (string) $documentRow->id,
                (string) $documentRow->process_id,
                (string) $documentRow->template_id,
            );

            $now = now();
            $headId = (string) Str::uuid();

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => Document::class,
                'versionable_id' => $documentRow->id,
                'version_number' => 0,
                'base_version_id' => null,
                'change_set' => null,
                'status' => (string) ($documentRow->status ?? 'draft'),
                'created_by' => (string) ($documentRow->created_by ?? ''),
                'published_by' => null,
                'published_at' => null,
                'changelog' => null,
                'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('documents')->where('id', $documentRow->id)->update([
                'head_entity_version_id' => $headId,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasColumn('documents', 'head_entity_version_id')) {
            return;
        }

        if (! DB::table('documents')->whereNull('head_entity_version_id')->exists()) {
            Schema::table('documents', function (Blueprint $table) {
                try {
                    $table->foreign('head_entity_version_id')
                        ->references('id')
                        ->on('entity_versions')
                        ->restrictOnDelete();
                } catch (\Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'head_entity_version_id')) {
                try {
                    $table->dropForeign(['head_entity_version_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('head_entity_version_id');
            }
        });

        if (Schema::hasTable('entity_versions')) {
            DB::table('entity_versions')
                ->where('versionable_type', Document::class)
                ->where('version_number', 0)
                ->delete();
        }
    }
};
