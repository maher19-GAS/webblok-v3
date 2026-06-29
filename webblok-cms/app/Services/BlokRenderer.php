<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\BlokDefinition;
use Illuminate\Support\Facades\View;

/**
 * Renders a blok instance to HTML by delegating to its Blade view.
 * Falls back to a generic wrapper when no dedicated view exists.
 */
final class BlokRenderer
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function render(string $blokKey, array $config, string $locale = 'en'): string
    {
        /** @var BlokDefinition|null $def */
        $def = BlokDefinition::query()->where('blok_key', $blokKey)->first();

        $view = $this->viewName($blokKey, $def);

        if (View::exists($view)) {
            return View::make($view, [
                'config' => $config,
                'locale' => $locale,
                'definition' => $def,
            ])->render();
        }

        return $this->fallback($blokKey, $config);
    }

    private function viewName(string $blokKey, ?BlokDefinition $def): string
    {
        $category = $def !== null ? $def->category : 'content';

        return "bloks.{$category}.".str_replace('_', '-', $blokKey);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fallback(string $blokKey, array $config): string
    {
        $label = e($blokKey);
        $json = e((string) json_encode($config, JSON_PRETTY_PRINT));

        return <<<HTML
        <div class="webblok-blok webblok-blok--{$label}" data-blok="{$label}">
            <!-- Blok '{$label}' has no dedicated view; rendering config preview -->
            <pre class="webblok-blok__fallback">{$json}</pre>
        </div>
        HTML;
    }
}
