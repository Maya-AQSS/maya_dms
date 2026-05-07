<?php

use App\Models\Template;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('templates')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            if (! Schema::hasColumn('templates', 'head_entity_version_id')) {
                $table->uuid('head_entity_version_id')->nullable()->after('process_id');
            }
        });

        if (! Schema::hasTable('entity_versions')) {
            return;
        }

        foreach (DB::table('templates')->orderBy('id')->cursor() as $templateRow) {
            if ($templateRow->head_entity_version_id !== null) {
                continue;
            }

            $snapshot = TemplateHeadSnapshot::buildPayloadFromLegacyRow(
                $templateRow,
                (string) $templateRow->id,
                (string) $templateRow->process_id,
            );

            $now = now();
            $headId = (string) Str::uuid();

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => Template::class,
                'versionable_id' => $templateRow->id,
                'version_number' => 0,
                'base_version_id' => null,
                'change_set' => null,
                'status' => (string) ($templateRow->status ?? 'draft'),
                'created_by' => (string) ($templateRow->created_by ?? ''),
                'published_by' => null,
                'published_at' => null,
                'changelog' => null,
                'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('templates')->where('id', $templateRow->id)->update([
                'head_entity_version_id' => $headId,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasColumn('templates', 'head_entity_version_id')) {
            return;
        }

        if (! DB::table('templates')->whereNull('head_entity_version_id')->exists()) {
            Schema::table('templates', function (Blueprint $table) {
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
        if (! Schema::hasTable('templates')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'head_entity_version_id')) {
                try {
                    $table->dropForeign(['head_entity_version_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('head_entity_version_id');
            }
        });

        if (Schema::hasTable('entity_versions')) {
            DB::table('entity_versions')
                ->where('versionable_type', Template::class)
                ->where('version_number', 0)
                ->delete();
        }
    }
};
