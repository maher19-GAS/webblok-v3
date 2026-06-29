<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Community artifact publishing lifecycle (state machine).
 *
 * draft → submitted → in_review → approved → published → delisted
 * with reject paths back to submitted, and delisted → published.
 */
enum ArtifactStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PUBLISHED = 'published';
    case DELISTED = 'delisted';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::SUBMITTED],
            self::SUBMITTED => [self::IN_REVIEW, self::REJECTED],
            self::IN_REVIEW => [self::APPROVED, self::REJECTED],
            self::APPROVED => [self::PUBLISHED, self::REJECTED],
            self::PUBLISHED => [self::DELISTED],
            self::REJECTED => [self::SUBMITTED],
            self::DELISTED => [self::PUBLISHED],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }
}
