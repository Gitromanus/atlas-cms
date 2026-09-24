<?php

namespace App\View;

use App\Services\Tenant\TenantContext;
use Illuminate\View\FileViewFinder;

/**
 * Поиск Blade-шаблонов с учётом активной темы витрины.
 *
 * Порядок поиска:
 *  1. ресурсы активной темы магазина (resources/views/themes/{slug})
 *  2. тема по умолчанию (resources/views/themes/default)
 *  3. стандартные представления приложения (admin, email и т.д.)
 */
class ThemeViewFinder extends FileViewFinder
{
    protected function findInPaths($name, $paths)
    {
        $themeSlug = app(TenantContext::class)->themeSlug();
        $themePath = resource_path('views/themes/'.$themeSlug);

        if ($themeSlug !== config('atlas.themes.default')) {
            $paths = array_merge(
                [$themePath, resource_path('views/themes/'.config('atlas.themes.default'))],
                $paths
            );
        } else {
            $paths = array_merge([$themePath], $paths);
        }

        return parent::findInPaths($name, $paths);
    }
}