<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Group;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\DocumentPolicy;
use App\Policies\GroupPolicy;
use App\Policies\TemplatePolicy;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Repositories\Eloquent\AcademicHierarchyRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DocumentRepository;
use App\Repositories\Eloquent\GroupRepository;
use App\Repositories\Eloquent\TemplateRepository;
use App\Repositories\Eloquent\TemplateVersionRepository;
use App\Repositories\Eloquent\UserProfileRepository;
use App\Services\AcademicHierarchyService;
use App\Services\AuditLogService;
use App\Services\CommentService;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\GroupServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use Maya\Auth\Contracts\JwksServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\DocumentService;
use App\Services\GroupService;
use App\Services\HealthCheckService;
use App\Services\TemplateService;
use App\Services\UserProfileService;
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
        $this->app->bind(GroupRepositoryInterface::class, GroupRepository::class);
        $this->app->bind(TemplateRepositoryInterface::class, TemplateRepository::class);
        $this->app->bind(TemplateVersionRepositoryInterface::class, TemplateVersionRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);
        $this->app->bind(AcademicHierarchyRepositoryInterface::class, AcademicHierarchyRepository::class);

        // Service bindings
        $this->app->bind(AuditLogServiceInterface::class, AuditLogService::class);
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
        $this->app->bind(GroupServiceInterface::class, GroupService::class);
        $this->app->bind(CommentServiceInterface::class, CommentService::class);
        $this->app->bind(TemplateServiceInterface::class, TemplateService::class);
        $this->app->bind(HealthCheckServiceInterface::class, HealthCheckService::class);
        $this->app->bind(UserProfileServiceInterface::class, UserProfileService::class);
        $this->app->bind(AcademicHierarchyServiceInterface::class, AcademicHierarchyService::class);
    }

    public function boot(): void
    {
        // Guard JWT stateless: resuelve el usuario desde el atributo 'jwt_user'
        // que JwtMiddleware deposita en el request tras validar el token.
        // Auth::user() / $request->user() lo invocan de forma diferida, sin sesión.
        Auth::viaRequest('jwt-token', function ($request) {
            $profile = $request->attributes->get('jwt_user');

            return $profile ? new JwtUser($profile) : null;
        });

        // Registro de políticas
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
    }
}
