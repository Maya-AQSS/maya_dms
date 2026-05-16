<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Adjunta `can_clone` desde la policy a uno o varios Templates para evitar
 * Gate por recurso en Resources/listados. Reutilizado entre Template,
 * TemplateState y Reviewers controllers (split de B9).
 */
trait AttachesTemplateCanCloneMeta
{
    /**
     * @param  Template|Collection<int, Template>  $templates
     */
    protected function attachCanCloneMeta(Template|Collection $templates, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $attach = function (Template $template) use ($user): void {
            $template->setAttribute('can_clone', Gate::forUser($user)->allows('clone', $template));
        };

        if ($templates instanceof Template) {
            $attach($templates);

            return;
        }

        foreach ($templates as $template) {
            $attach($template);
        }
    }
}
