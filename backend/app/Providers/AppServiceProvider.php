<?php

declare(strict_types=1);

namespace App\Providers;

use App\DTOs\Users\JwtProfileDto;
use App\Support\FdwTeardown;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\JwtUser;
use App\Models\Process;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\Theme;
use App\Policies\BlockPolicy;
use App\Policies\CommentPolicy;
use App\Policies\ProcessPolicy;
use App\Policies\DocumentBlockPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\TemplateBlockPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\ThemePolicy;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\AnchoredCommentRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\DocumentVersionRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionBlockLayerRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Repositories\Eloquent\AcademicHierarchyRepository;
use App\Repositories\Eloquent\AnchoredCommentRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DocumentBlockRepository;
use App\Repositories\Eloquent\DocumentRepository;
use App\Repositories\Eloquent\DocumentVersionBlockLayerRepository;
use App\Repositories\Eloquent\DocumentVersionRepository;
use App\Repositories\Eloquent\EntityVersionRepository;
use App\Repositories\Eloquent\ProcessRepository;
use App\Repositories\Eloquent\ResolvedPermissionReader;
use App\Repositories\Eloquent\TeamReadRepository;
use App\Repositories\Eloquent\TemplateBlockRepository;
use App\Repositories\Eloquent\TemplateRepository;
use App\Repositories\Eloquent\TemplateVersionBlockLayerRepository;
use App\Repositories\Eloquent\TemplateVersionRepository;
use App\Repositories\Eloquent\ThemeRepository;
use App\Repositories\Eloquent\UserDirectoryRepository;
use App\Repositories\Eloquent\UserFavoriteRepository;
use App\Repositories\Eloquent\UserProfileRepository;
use App\Repositories\Resolvers\FdwUserProfileResolver;
use App\Services\AnchoredCommentService;
use App\Services\ApiTeamEmbedService;
use App\Services\CommentService;
use App\Services\MediaService;
use App\Services\Contracts\AnchoredCommentServiceInterface;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Contracts\DocumentExportServiceInterface;
use App\Services\Contracts\DocumentPdfServiceInterface;
use App\Services\Contracts\DocumentRenderServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Services\Contracts\ProcessServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\Contracts\TeamReadServiceInterface;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use App\Services\Contracts\ThemeAssetServiceInterface;
use App\Services\Contracts\ThemeServiceInterface;
use App\Services\Contracts\UserDirectoryServiceInterface;
use App\Services\Contracts\UserFavoriteServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\DashboardService;
use App\Services\DocumentDocxExportService;
use App\Services\DocumentExportService;
use App\Services\DocumentPdfService;
use App\Services\Contracts\TemplateRenderServiceInterface;
use App\Services\DocumentRenderService;
use App\Services\DocumentService;
use App\Services\TemplateRenderService;
use App\Services\EntityVersionLifecycleService;
use App\Services\ProcessService;
use App\Services\SnapshotService;
use App\Services\TeamReadService;
use App\Services\TemplateBlockService;
use App\Services\TemplateService;
use App\Services\ThemeAssetService;
use App\Services\ThemeService;
use App\Services\UserDirectoryService;
use App\Services\UserFavoriteService;
use App\Services\UserProfileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Maya\Profile\Migrations as ProfileMigrations;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(DocumentBlockRepositoryInterface::class, DocumentBlockRepository::class);
        $this->app->bind(DocumentVersionRepositoryInterface::class, DocumentVersionRepository::class);
        $this->app->bind(DocumentVersionBlockLayerRepositoryInterface::class, DocumentVersionBlockLayerRepository::class);
        $this->app->bind(TemplateVersionBlockLayerRepositoryInterface::class, TemplateVersionBlockLayerRepository::class);
        $this->app->bind(EntityVersionRepositoryInterface::class, EntityVersionRepository::class);
        $this->app->bind(ProcessRepositoryInterface::class, ProcessRepository::class);
        $this->app->bind(TeamReadRepositoryInterface::class, TeamReadRepository::class);
        $this->app->bind(TemplateRepositoryInterface::class, TemplateRepository::class);
        $this->app->bind(TemplateBlockRepositoryInterface::class, TemplateBlockRepository::class);
        $this->app->bind(TemplateVersionRepositoryInterface::class, TemplateVersionRepository::class);
        $this->app->bind(ThemeRepositoryInterface::class, ThemeRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);
        $this->app->bind(UserFavoriteRepositoryInterface::class, UserFavoriteRepository::class);
        $this->app->bind(UserDirectoryRepositoryInterface::class, UserDirectoryRepository::class);
        $this->app->bind(ResolvedPermissionReaderInterface::class, ResolvedPermissionReader::class);
        $this->app->bind(AcademicHierarchyRepositoryInterface::class, AcademicHierarchyRepository::class);
        $this->app->bind(AnchoredCommentRepositoryInterface::class, AnchoredCommentRepository::class);

        // Service bindings
        $this->app->bind(ApiTeamEmbedServiceInterface::class, ApiTeamEmbedService::class);
        $this->app->bind(SnapshotServiceInterface::class, SnapshotService::class);
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
        $this->app->bind(DocumentRenderServiceInterface::class, DocumentRenderService::class);
        $this->app->bind(DocumentExportServiceInterface::class, DocumentExportService::class);
        $this->app->bind(DocumentDocxExportService::class, DocumentDocxExportService::class);
        $this->app->bind(TemplateRenderServiceInterface::class, TemplateRenderService::class);
        $this->app->bind(DocumentPdfServiceInterface::class, DocumentPdfService::class);
        $this->app->bind(EntityVersionLifecycleServiceInterface::class, EntityVersionLifecycleService::class);
        $this->app->bind(ProcessServiceInterface::class, ProcessService::class);
        $this->app->bind(TeamReadServiceInterface::class, TeamReadService::class);
        $this->app->bind(CommentServiceInterface::class, CommentService::class);
        $this->app->bind(AnchoredCommentServiceInterface::class, AnchoredCommentService::class);
        $this->app->bind(DashboardServiceInterface::class, DashboardService::class);
        $this->app->bind(TemplateServiceInterface::class, TemplateService::class);
        $this->app->bind(TemplateBlockServiceInterface::class, TemplateBlockService::class);
        $this->app->bind(ThemeServiceInterface::class, ThemeService::class);
        $this->app->bind(ThemeAssetServiceInterface::class, ThemeAssetService::class);
        $this->app->bind(UserProfileServiceInterface::class, UserProfileService::class);
        // /me + /me/locale viven en maya/shared-profile-laravel. El paquete
        // bindea por defecto JwtPassthroughResolver; aquí lo sobrescribimos
        // con el resolver que envuelve nuestro UserProfileService (FDW + cache).
        $this->app->bind(
            UserProfileResolverInterface::class,
            FdwUserProfileResolver::class,
        );
        $this->app->bind(UserFavoriteServiceInterface::class, UserFavoriteService::class);
        $this->app->bind(UserDirectoryServiceInterface::class, UserDirectoryService::class);
        $this->app->singleton(MediaService::class);
    }

    public function boot(): void
    {
        // Migraciones compartidas con el resto de apps Maya (paquete
        // `maya/shared-profile-laravel`). dms consume la vista resuelta de
        // permisos vía FDW (`v_dms_user_permissions` en maya_authorization)
        // — eliminada la tabla local `user_permissions`.
                // Broadcasting auth endpoint protegido por JWT y bajo prefijo /api/v1 para
        // consistencia con el resto de la API. Anula el `/broadcasting/auth` que
        // Laravel registra por defecto con middleware `web` (basado en sesión).
        Broadcast::routes([
            'prefix' => 'api/v1',
            'middleware' => ['api', 'jwt'],
        ]);

        $this->loadMigrationsFrom(ProfileMigrations::users());
        $this->loadMigrationsFrom(ProfileMigrations::academicAssignments());
        $this->loadMigrationsFrom(ProfileMigrations::academicCatalogs());
        $this->loadMigrationsFrom(ProfileMigrations::teams());
        $this->loadMigrationsFrom(ProfileMigrations::userPermissions());

        // db:wipe no elimina vistas ni foreign tables FDW (las crea el paquete
        // shared-profile). Las limpiamos antes de migrate:fresh/db:wipe para que
        // la reconstrucción sea reproducible (si no, el rewrite de la vista
        // `teams` falla con «cannot drop columns from view»).
        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            if (in_array($event->command, ['migrate:fresh', 'db:wipe'], true)) {
                FdwTeardown::dropAllInPublicSchema();
            }
        });

        // Guard JWT stateless: resuelve el usuario desde el atributo 'jwt_user'
        // que JwtMiddleware deposita en el request tras validar el token.
        // Auth::user() / $request->user() lo invocan de forma diferida, sin sesión.
        Auth::viaRequest('jwt-token', function ($request) {
            $jwtProfile = $request->attributes->get('jwt_user');

            if (! $jwtProfile) {
                return null;
            }

            $userId = (string) $jwtProfile['id'];

            /** @var array<string, mixed> $fromDb Perfil unificado (FDW o fallback JWT vía {@see UserProfileService}). */
            $fromDb = app(UserProfileServiceInterface::class)->getProfile($userId, JwtProfileDto::fromArray($jwtProfile));

            $profile = array_merge($jwtProfile, [
                'study_type_ids' => $fromDb['study_type_ids'] ?? [],
                'study_type_id' => null,
                'study_ids' => $fromDb['study_ids'] ?? [],
                'study_id' => null,
                'module_ids' => $fromDb['module_ids'] ?? [],
                'module_id' => null,
                'course_module_ids' => null,
                'course_module_id' => null,
                'team_ids' => $fromDb['team_ids'] ?? [],
                'team_id' => null,
            ]);

            $profile['permissions'] = $fromDb['permissions'] ?? [];

            return new JwtUser($profile);
        });

        // Registro de políticas
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Process::class, ProcessPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentBlock::class, DocumentBlockPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(TemplateBlock::class, TemplateBlockPolicy::class);
        Gate::policy(Theme::class, ThemePolicy::class);

        $blockPolicy = BlockPolicy::class;
        Gate::define('listTemplateBlocks', [$blockPolicy, 'listForTemplate']);
        Gate::define('showTemplateBlock', [$blockPolicy, 'showForTemplate']);
        Gate::define('listDocumentBlocks', [$blockPolicy, 'listForDocument']);
        Gate::define('createTemplateBlock', [$blockPolicy, 'createForTemplate']);
        Gate::define('updateTemplateBlock', [$blockPolicy, 'updateForTemplate']);
        Gate::define('deleteTemplateBlock', [$blockPolicy, 'deleteForTemplate']);
        Gate::define('updateDocumentBlock', [$blockPolicy, 'updateForDocument']);
        Gate::define('deleteDocumentBlock', [$blockPolicy, 'deleteForDocument']);
    }
}
