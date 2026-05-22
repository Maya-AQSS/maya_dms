<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade `icon` (identificador de icono SVG/emoji) y `color` (hex 7 chars) a la
 * tabla `processes`. Ambas columnas son nullable para no romper datos
 * existentes; los seeders se encargan de poblar valores únicos por proceso.
 *
 * `icon` se almacena como string corto (slug de icono) para que el frontend
 * pueda resolverlo a un componente React (catálogo en `processIcons.tsx`).
 * `color` es hex 7 chars (`#RRGGBB`) — cada proceso tendrá un color único
 * para distinguirlos en sidebar, listas y badges.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table): void {
            $table->string('icon', 40)->nullable()->after('alias');
            $table->string('color', 7)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table): void {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
