<?php

declare(strict_types=1);

use App\Enums\ArtifactStatus;

it('allows draft to be submitted', function (): void {
    expect(ArtifactStatus::DRAFT->canTransitionTo(ArtifactStatus::SUBMITTED))->toBeTrue();
});

it('forbids draft jumping straight to published', function (): void {
    expect(ArtifactStatus::DRAFT->canTransitionTo(ArtifactStatus::PUBLISHED))->toBeFalse();
});

it('allows approved to be published', function (): void {
    expect(ArtifactStatus::APPROVED->canTransitionTo(ArtifactStatus::PUBLISHED))->toBeTrue();
});

it('allows rejected to be resubmitted', function (): void {
    expect(ArtifactStatus::REJECTED->canTransitionTo(ArtifactStatus::SUBMITTED))->toBeTrue();
});

it('allows published to be delisted and relisted', function (): void {
    expect(ArtifactStatus::PUBLISHED->canTransitionTo(ArtifactStatus::DELISTED))->toBeTrue()
        ->and(ArtifactStatus::DELISTED->canTransitionTo(ArtifactStatus::PUBLISHED))->toBeTrue();
});

it('forbids illegal transitions', function (): void {
    expect(ArtifactStatus::IN_REVIEW->canTransitionTo(ArtifactStatus::DRAFT))->toBeFalse()
        ->and(ArtifactStatus::SUBMITTED->canTransitionTo(ArtifactStatus::PUBLISHED))->toBeFalse();
});
