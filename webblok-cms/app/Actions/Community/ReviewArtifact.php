<?php

declare(strict_types=1);

namespace App\Actions\Community;

use App\Enums\ArtifactStatus;
use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;

/**
 * Super-admin review decisions: approve (→ APPROVED) or reject (→ REJECTED),
 * recording the reviewer and any notes.
 */
final class ReviewArtifact
{
    public function approve(CommunityArtifact $artifact, string $reviewerId, ?string $notes = null): void
    {
        if (! $artifact->status->canTransitionTo(ArtifactStatus::APPROVED)) {
            throw new InvalidArtifactException('Artifact cannot be approved from its current state.');
        }

        $artifact->status = ArtifactStatus::APPROVED;
        $artifact->reviewed_by = $reviewerId;
        $artifact->review_notes = $notes;
        $artifact->save();
    }

    public function reject(CommunityArtifact $artifact, string $reviewerId, ?string $notes = null): void
    {
        if (! $artifact->status->canTransitionTo(ArtifactStatus::REJECTED)) {
            throw new InvalidArtifactException('Artifact cannot be rejected from its current state.');
        }

        $artifact->status = ArtifactStatus::REJECTED;
        $artifact->reviewed_by = $reviewerId;
        $artifact->review_notes = $notes;
        $artifact->save();
    }
}
