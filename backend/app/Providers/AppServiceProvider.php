<?php

namespace App\Providers;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DocumentRepository;
use App\Repositories\Eloquent\TemplateRepository;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\HealthCheckServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use App\Services\DocumentService;
use App\Services\HealthCheckService;
use App\Services\TemplateService;
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

        // Service bindings
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
        $this->app->bind(TemplateServiceInterface::class, TemplateService::class);
        $this->app->bind(HealthCheckServiceInterface::class, HealthCheckService::class);
    }

    public function boot(): void
    {
        //
    }
}
