<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Actions\Community\SubmitArtifact;
use App\Enums\ArtifactStatus;
use App\Exceptions\InvalidArtifactException;
use App\Http\Controllers\Controller;
use App\Models\Platform\CommunityArtifact;
use App\Models\Platform\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Creator Studio — the author surface for community contributions. Lists the
 * signed-in user's artifacts, lets them create a draft and submit it for review.
 */
final class CreatorStudioController extends Controller
{
    public function __construct(
        private readonly SubmitArtifact $submit,
    ) {}

    public function index(): View
    {
        $userId = $this->userId();

        return view('cms.studio', [
            'artifacts' => CommunityArtifact::query()
                ->where('author_id', $userId)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:blok,template,site,theme'],
            'description' => ['nullable', 'string', 'max:1000'],
            'payload' => ['required', 'string'],
        ]);

        $name = is_string($data['name']) ? $data['name'] : 'Artifact';
        $type = is_string($data['type']) ? $data['type'] : 'template';
        $payload = is_string($data['payload']) ? $data['payload'] : '{}';

        CommunityArtifact::query()->create([
            'id' => (string) Str::uuid(),
            'author_id' => $this->userId(),
            'type' => $type,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => is_string($data['description'] ?? null) ? $data['description'] : null,
            'version' => '1.0.0',
            'payload' => $payload,
            'locale_support' => ['en'],
            'license' => 'free',
            'price' => 0.0,
            'status' => ArtifactStatus::DRAFT,
        ]);

        return back()->with('status', 'Draft created.');
    }

    public function submit(string $artifactId): RedirectResponse
    {
        /** @var CommunityArtifact $artifact */
        $artifact = CommunityArtifact::query()
            ->where('author_id', $this->userId())
            ->findOrFail($artifactId);

        try {
            $this->submit->execute($artifact);
        } catch (InvalidArtifactException $e) {
            return back()->withErrors(['payload' => $e->getMessage()]);
        }

        return back()->with('status', 'Submitted for review.');
    }

    private function userId(): string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->id : '';
    }
}
