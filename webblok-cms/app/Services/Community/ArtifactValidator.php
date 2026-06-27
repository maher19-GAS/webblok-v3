<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Data\SiteBundleData;
use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;
use Throwable;

/**
 * The contribution sandbox gate. Community content is untrusted: this validator
 * rejects executable PHP, scripts, inline event handlers, javascript: URLs,
 * external @import, iframes and other dangerous constructs BEFORE an artifact
 * may enter the review queue, and validates each artifact type's structure.
 */
final class ArtifactValidator
{
    /** @var list<string> */
    private const FORBIDDEN_PATTERNS = [
        '/<\?php/i',
        '/<script\b/i',
        '/\bon\w+\s*=/i',          // inline event handlers (onclick=, onload=, …)
        '/javascript:/i',
        '/@import/i',
        '/<iframe\b/i',
        '/\beval\s*\(/i',
        '/\bbase64_decode\s*\(/i',
    ];

    public function assertValid(CommunityArtifact $artifact): void
    {
        $payload = $artifact->payload;

        if ($payload === '' || json_validate($payload) === false) {
            throw new InvalidArtifactException('Artifact payload is not valid JSON.');
        }

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                throw new InvalidArtifactException(
                    'Artifact contains forbidden content (scripts/PHP/unsafe HTML are not allowed).',
                );
            }
        }

        match ($artifact->type) {
            'blok' => $this->validateBlok($payload),
            'template', 'site' => $this->validateBundle($payload),
            'theme' => $this->validateTheme($payload),
            default => throw new InvalidArtifactException('Unknown artifact type.'),
        };
    }

    private function validateBlok(string $payload): void
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        foreach (['blok_key', 'label', 'schema', 'template'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw new InvalidArtifactException("Blok is missing required field: {$required}");
            }
        }
    }

    private function validateBundle(string $payload): void
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        try {
            SiteBundleData::from($data);
        } catch (Throwable $e) {
            throw new InvalidArtifactException('Bundle does not match the Site Bundle schema: '.$e->getMessage());
        }
    }

    private function validateTheme(string $payload): void
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! isset($data['css_variables']) || ! is_array($data['css_variables'])) {
            throw new InvalidArtifactException('Theme must declare css_variables.');
        }
    }
}
