<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\GenerateSite;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V1 AI site generation (offline-safe stub driver by default). Returns a
 * portable Site Bundle that can be imported into a new or existing tenant.
 */
final class SiteGenerationController extends Controller
{
    public function __construct(
        private readonly GenerateSite $generate,
    ) {}

    public function generate(Request $request): JsonResponse
    {
        /** @var array{prompt: string, locales: list<string>|null, logo_media_id: string|null} $v */
        $v = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'locales' => ['nullable', 'array'],
            'locales.*' => ['string'],
            'logo_media_id' => ['nullable', 'string'],
        ]);

        $bundle = $this->generate->execute(
            $v['prompt'],
            $v['locales'] ?? ['en'],
            $v['logo_media_id'] ?? null,
        );

        return response()->json(['data' => $bundle->toArray()], 201);
    }
}
