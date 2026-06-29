<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Platform\ApiKey;
use App\Services\BlokDataResolver;
use App\Services\UsageTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V1 embed API — returns merged blok data (base JSON + tenant overrides) for a
 * given blok key and language. This is the lightweight endpoint consumed by the
 * embeddable widget.js on third-party sites.
 */
final class BlokDataController extends Controller
{
    public function __construct(
        private readonly BlokDataResolver $resolver,
        private readonly UsageTracker $usage,
    ) {}

    public function show(string $blokKey, Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'en');
        $lang = is_string($lang) ? $lang : 'en';

        $start = microtime(true);
        $data = $this->resolver->resolve($blokKey, $lang);
        $renderMs = (int) ((microtime(true) - $start) * 1000);

        $apiKey = $request->attributes->get('api_key');
        if ($apiKey instanceof ApiKey) {
            $this->usage->recordApiRequest($apiKey->tenant_id, $blokKey, 'json', $lang, $apiKey->id, $renderMs);
        }

        return response()->json([
            'blok_key' => $blokKey,
            'lang' => $lang,
            'data' => $data,
        ])->header('Access-Control-Allow-Origin', '*');
    }
}
