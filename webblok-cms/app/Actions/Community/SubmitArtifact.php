<?php

declare(strict_types=1);

namespace App\Actions\Community;

use App\Enums\ArtifactStatus;
use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;
use App\Services\Community\ArtifactValidator;

/**
 * Transitions a draft artifact into the review pipeline after a hard validation
 * gate. Auto-advances to IN_REVIEW so it surfaces in the admin Review Queue.
 */
final class SubmitArtifact
{
    public function __construct(
        private readonly ArtifactValidator $validator,
    ) {}

    public function execute(CommunityArtifact $artifact): void
    {
        if (! $artifact->status->canTransitionTo(ArtifactStatus::SUBMITTED)) {
            throw new InvalidArtifactException('Artifact cannot be submitted from its current state.');
        }

        // Hard validation gate BEFORE it enters the review queue.
        $this->validator->assertValid($artifact);

        $artifact->status = ArtifactStatus::SUBMITTED;
        $artifact->save();

        // Auto-advance to in_review for the queue.
        $artifact->status = ArtifactStatus::IN_REVIEW;
        $artifact->save();
    }
}
