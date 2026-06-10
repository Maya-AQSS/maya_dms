<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\DocumentConstants;
use App\Models\Theme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Siembra el theme por defecto del sistema como registro real en `themes`.
 *
 * A diferencia del fallback {@see DocumentConstants::DEFAULT_THEME} (que el
 * render usa cuando una plantilla no tiene theme asignado), este seeder
 * materializa esa identidad visual en BD para que:
 *   - exista siempre y sea referenciable por las plantillas demo;
 *   - los admins puedan editarla y clonarla como base;
 *   - NO se pueda borrar (`is_system = true`, ver {@see \App\Policies\ThemePolicy::delete}).
 *
 * Idempotente: usa un UUID fijo y no toca el registro si ya existe, de modo que
 * las ediciones de un admin sobreviven a un re-seed. El contenido visual deriva
 * del constante para no duplicar valores a mano.
 */
class DefaultThemeSeeder extends Seeder
{
    /** UUID fijo del theme de sistema — determinista para referencias y reseed. */
    public const DEFAULT_THEME_ID = '00000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        if (! Schema::hasTable('themes')) {
            return;
        }

        // No-op si ya existe: preserva ediciones de admin entre reseeds.
        if (Theme::query()->whereKey(self::DEFAULT_THEME_ID)->exists()) {
            return;
        }

        $default = DocumentConstants::DEFAULT_THEME;

        $theme = new Theme;
        $theme->forceFill([
            'id' => self::DEFAULT_THEME_ID,
            'name' => 'Tema por defecto CEEDCV',
            'description' => 'Identidad visual base del CEEDCV. Editable y clonable por '
                .'administradores, pero permanente: no se puede eliminar.',
            'status' => 'published',
            'is_system' => true,
            'created_by' => $this->systemAuthorId(),
            'team_id' => null,
            'palette' => $default['palette'],
            'typography' => $default['typography'],
            'layout' => $default['layout'],
            'accessibility' => $default['accessibility'],
            'cloned_from_id' => null,
        ]);
        $theme->save();
    }

    /**
     * Autor del theme de sistema. Usa el superadmin dev cuando hay pack FDW;
     * cae a un UUID estable si no está disponible (p. ej. tests sin FDW).
     */
    private function systemAuthorId(): string
    {
        $devUsersFile = database_path('data/maya_dev_users.php');

        if (is_file($devUsersFile)) {
            /** @var array<string, string> $devUsers */
            $devUsers = require $devUsersFile;
            $superadmin = $devUsers['superadmin'] ?? null;
            if (is_string($superadmin) && $superadmin !== '') {
                return $superadmin;
            }
        }

        return self::DEFAULT_THEME_ID;
    }
}
