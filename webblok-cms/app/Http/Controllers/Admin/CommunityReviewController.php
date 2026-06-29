<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Community\PublishArtifact;
use App\Actions\Community\ReviewArtifact;
use App\Enums\ArtifactStatus;
use App\Exceptions\InvalidArtifactException;
use App\Http\Controllers\Controller;
use App\Models\Platform\CommunityArtifact;
use App\Models\Platform\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Super-admin Review Queue for community artifacts: approve/reject with notes,
 * then publish (mirrors into the marketplace).
 */
final class CommunityReviewController extends Controller
{
    public function __construct(
        private readonly ReviewArtifact $review,
        private readonly PublishArtifact $publish,
    ) {}

    public function index(): View
    {
        return view('admin.community', [
            'queue' => CommunityArtifact::query()
                ->with('author')
                ->whereIn('status', [ArtifactStatus::SUBMITTED->value, ArtifactStatus::IN_REVIEW->value])
                ->orderBy('created_at')
                ->get(),
            'approved' => CommunityArtifact::query()
                ->with('author')
                ->where('status', ArtifactStatus::APPROVED->value)
                ->get(),
        ]);
    }

    public function approve(Request $request, string $artifactId): RedirectResponse
    {
        $artifact = $this->find($artifactId);
        $this->review->approve($artifact, $this->reviewerId(), $this->notes($request));

        return back()->with('status', 'Approved.');
    }

    public function reject(Request $request, string $artifactId): RedirectResponse
    {
        $artifact = $this->find($artifactId);
        $this->review->reject($artifact, $this->reviewerId(), $this->notes($request));

        return back()->with('status', 'Rejected.');
    }

    public function publish(string $artifactId): RedirectResponse
    {
        $artifact = $this->find($artifactId);

        try {
            $this->publish->execute($artifact);
        } catch (InvalidArtifactException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return back()->with('status', 'Published to marketplace.');
    }

    private function find(string $artifactId): CommunityArtifact
    {
        /** @var CommunityArtifact $artifact */
        $artifact = CommunityArtifact::query()->findOrFail($artifactId);

        return $artifact;
    }

    private function notes(Request $request): ?string
    {
        $notes = $request->input('notes');

        return is_string($notes) && $notes !== '' ? $notes : null;
    }

    private function reviewerId(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->id : '';
    }
}
