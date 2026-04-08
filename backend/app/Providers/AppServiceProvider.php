<?php

namespace App\Providers;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DocumentRepository;
use App\Repositories\Eloquent\TemplateRepository;
use App\Repositories\Eloquent\UserProfileRepository;
use App\Models\JwtUser;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Services\DocumentService;
use App\Services\HealthCheckService;
use App\Services\TemplateService;
use App\Services\UserProfileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(TemplateRepositoryInterface::class, TemplateRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);

        // Service bindings
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
        $this->app->bind(TemplateServiceInterface::class, TemplateService::class);
        $this->app->bind(HealthCheckServiceInterface::class, HealthCheckService::class);
        $this->app->bind(UserProfileServiceInterface::class, UserProfileService::class);
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
    }
}
