<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\TenantTheme;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Resolves the active theme for the current tenant and compiles its CSS
 * (base bundle + custom CSS-variable overrides) for injection into rendered
 * pages and static exports.
 */
final class ThemeManager
{
    private const DEFAULT_CSS = <<<'CSS'
    :root{--wb-bg:#ffffff;--wb-fg:#111827;--wb-primary:#2563eb;--wb-bg-secondary:#f3f4f6;--wb-radius:10px;--wb-font:Inter,system-ui,sans-serif}
    *{box-sizing:border-box}body{margin:0;font-family:var(--wb-font);color:var(--wb-fg);background:var(--wb-bg);line-height:1.6}
    .wb-section{width:100%}.wb-page{min-height:100vh}
    img{max-width:100%;height:auto}a{color:var(--wb-primary)}
    .webblok-blok{padding:1rem}
    CSS;

    public function activeTheme(): ?TenantTheme
    {
        try {
            return TenantTheme::query()->where('is_active', true)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Compile the active theme's full CSS (base + custom-variable overrides).
     */
    public function activeCss(): string
    {
        $theme = $this->activeTheme();
        $base = self::DEFAULT_CSS;

        if ($theme === null) {
            return $base;
        }

        $bundlePath = base_path("marketplace/official/themes/{$theme->item_slug}/theme.css");
        if (File::exists($bundlePath)) {
            $base = File::get($bundlePath);
        }

        $vars = $theme->custom_vars;
        if (is_array($vars) && $vars !== []) {
            $overrides = ':root{';
            foreach ($vars as $name => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $overrides .= e($name).':'.e((string) $value).';';
                }
            }
            $overrides .= '}';
            $base .= "\n".$overrides;
        }

        return $base;
    }
}
