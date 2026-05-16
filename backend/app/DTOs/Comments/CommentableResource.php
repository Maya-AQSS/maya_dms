<?php
declare(strict_types=1);

namespace App\DTOs\Comments;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Model;

readonly class CommentableResource
{
    public function __construct(
        public Model $model,
        public string $class,
        public int $version,
    ) {}

    public function blockableClass(?string $blockableId): ?string
    {
        if ($blockableId === null) {
            return null;
        }

        return match ($this->class) {
            Template::class => TemplateBlock::class,
            Document::class => DocumentBlock::class,
            default => null,
        };
    }
}
