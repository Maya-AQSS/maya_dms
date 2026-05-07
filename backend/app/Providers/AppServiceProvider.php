<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Comment;
use App\Models\Template;
use App\Policies\CommentPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\TemplatePolicy;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Repositories\Contracts\TemplateBlockRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Repositories\Contracts\UserFavoriteRepositoryInterface;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use App\Repositories\Eloquent\AcademicHierarchyRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DocumentRepository;
use App\Repositories\Eloquent\EntityVersionRepository;
use App\Repositories\Eloquent\ProcessRepository;
use App\Repositories\Eloquent\TeamReadRepository;
use App\Repositories\Eloquent\TemplateBlockRepository;
use App\Repositories\Eloquent\TemplateRepository;
use App\Repositories\Eloquent\TemplateVersionRepository;
use App\Repositories\Eloquent\UserPermissionRepository;
use App\Repositories\Eloquent\UserProfileRepository;
use App\Repositories\Eloquent\UserFavoriteRepository;
use App\Repositories\Eloquent\UserDirectoryRepository;
use App\Services\AcademicHierarchyService;
use App\Services\AuditLogService;
use App\Services\CommentService;
use App\Services\ApiTeamEmbedService;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use App\Services\Contracts\ProcessServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use App\Services\Contracts\TeamReadServiceInterface;
use Maya\Auth\Contracts\JwksServiceInterface;
use App\Services\Contracts\SnapshotServiceInterface;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\Contracts\UserFavoriteServiceInterface;
use App\Services\Contracts\UserDirectoryServiceInterface;
use App\Services\DocumentService;
use App\Services\EntityVersionLifecycleService;
use App\Services\ProcessService;
use App\Services\DashboardService;
use App\Services\HealthCheckService;
use App\Services\TeamReadService;
use App\Services\SnapshotService;
use App\Services\TemplateBlockService;
use App\Services\TemplateService;
use App\Services\UserProfileService;
use App\Services\UserFavoriteService;
use App\Services\UserDirectoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(EntityVersionRepositoryInterface::class, EntityVersionRepository::class);
        $this->app->bind(ProcessRepositoryInterface::class, ProcessRepository::class);
        $this->app->bind(TeamReadRepositoryInterface::class, TeamReadRepository::class);
        $this->app->bind(TemplateRepositoryInterface::class, TemplateRepository::class);
        $this->app->bind(TemplateBlockRepositoryInterface::class, TemplateBlockRepository::class);
        $this->app->bind(TemplateVersionRepositoryInterface::class, TemplateVersionRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);
        $this->app->bind(UserFavoriteRepositoryInterface::class, UserFavoriteRepository::class);
        $this->app->bind(UserDirectoryRepositoryInterface::class, UserDirectoryRepository::class);
        $this->app->bind(UserPermissionRepositoryInterface::class, UserPermissionRepository::class);
        $this->app->bind(AcademicHierarchyRepositoryInterface::class, AcademicHierarchyRepository::class);

        // Service bindings
        $this->app->bind(AuditLogServiceInterface::class, AuditLogService::class);
        $this->app->bind(ApiTeamEmbedServiceInterface::class, ApiTeamEmbedService::class);
        $this->app->bind(SnapshotServiceInterface::class, SnapshotService::class);
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
        $this->app->bind(EntityVersionLifecycleServiceInterface::class, EntityVersionLifecycleService::class);
        $this->app->bind(ProcessServiceInterface::class, ProcessService::class);
        $this->app->bind(TeamReadServiceInterface::class, TeamReadService::class);
        $this->app->bind(CommentServiceInterface::class, CommentService::class);
        $this->app->bind(DashboardServiceInterface::class, DashboardService::class);
        $this->app->bind(TemplateServiceInterface::class, TemplateService::class);
        $this->app->bind(TemplateBlockServiceInterface::class, TemplateBlockService::class);
        $this->app->bind(HealthCheckServiceInterface::class, HealthCheckService::class);
        $this->app->bind(UserProfileServiceInterface::class, UserProfileService::class);
        $this->app->bind(UserFavoriteServiceInterface::class, UserFavoriteService::class);
        $this->app->bind(UserDirectoryServiceInterface::class, UserDirectoryService::class);
        $this->app->bind(AcademicHierarchyServiceInterface::class, AcademicHierarchyService::class);
    }

    public function boot(): void
    {
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
            $fromDb = app(UserProfileServiceInterface::class)->getProfile($userId, $jwtProfile);

            $profile = array_merge($jwtProfile, [
                'study_type_ids'    => $fromDb['study_type_ids'] ?? [],
                'study_type_id'     => null,
                'study_ids'         => $fromDb['study_ids'] ?? [],
                'study_id'          => null,
                'module_ids'        => $fromDb['module_ids'] ?? [],
                'module_id'         => null,
                'course_module_ids' => null,
                'course_module_id'  => null,
                'team_ids'          => $fromDb['team_ids'] ?? [],
                'team_id'           => null,
            ]);

            $profile['permissions'] = $fromDb['permissions'] ?? [];

            return new JwtUser($profile);
        });

        // Registro de políticas
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
    }
}
