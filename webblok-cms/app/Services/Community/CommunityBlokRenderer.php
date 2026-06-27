<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Renders community (untrusted) bloks through a restricted, allowlisted
 * template language — NOT Blade/PHP. Supports only:
 *   - {{ field.x }}  interpolation (HTML-escaped by default)
 *   - {% if field.x %} … {% endif %}
 *   - {% for item in field.list %} … {% endfor %}
 * over the blok's own config. No filesystem, DB or PHP function access.
 *
 * All interpolation is escaped; CSS is scoped to the blok instance. This is
 * deliberately separate from the trusted Blade-backed BlokRenderer.
 */
final class CommunityBlokRenderer
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function render(string $template, array $config, string $instanceId = 'wb'): string
    {
        $scopeClass = 'wb-c-'.(string) preg_replace('/[^a-z0-9_-]/i', '', $instanceId);

        $html = $this->renderLoops($template, $config);
        $html = $this->renderConditionals($html, $config);
        $html = $this->renderInterpolations($html, $config);

        return '<div class="'.e($scopeClass).'">'.$html.'</div>';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function renderLoops(string $template, array $config): string
    {
        return (string) preg_replace_callback(
            '/\{%\s*for\s+(\w+)\s+in\s+field\.(\w+)\s*%\}(.*?)\{%\s*endfor\s*%\}/s',
            function (array $m) use ($config): string {
                $itemVar = $m[1];
                $listKey = $m[2];
                $body = $m[3];

                $list = $config[$listKey] ?? null;
                if (! is_array($list)) {
                    return '';
                }

                $out = '';
                foreach ($list as $item) {
                    /** @var array<string, mixed> $row */
                    $row = is_array($item) ? $item : [$itemVar => $item];
                    $out .= $this->renderInterpolations($body, $row, $itemVar);
                }

                return $out;
            },
            $template,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function renderConditionals(string $template, array $config): string
    {
        return (string) preg_replace_callback(
            '/\{%\s*if\s+field\.(\w+)\s*%\}(.*?)\{%\s*endif\s*%\}/s',
            function (array $m) use ($config): string {
                $key = $m[1];
                $value = $config[$key] ?? null;
                $truthy = $value !== null && $value !== '' && $value !== false && $value !== 0 && $value !== [];

                return $truthy ? $m[2] : '';
            },
            $template,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function renderInterpolations(string $template, array $config, ?string $itemVar = null): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*(?:field|'.preg_quote((string) $itemVar, '/').')\.(\w+)\s*\}\}/',
            function (array $m) use ($config): string {
                $key = $m[1];
                $value = $config[$key] ?? '';

                if (is_bool($value)) {
                    return $value ? '1' : '';
                }

                if (is_string($value) || is_int($value) || is_float($value)) {
                    return e((string) $value);
                }

                return '';
            },
            $template,
        );
    }
}
