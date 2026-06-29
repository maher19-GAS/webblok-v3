<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CronRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web-triggered scheduler endpoint (no system cron / SSH). An external pinger
 * hits /cron/run?secret=... once a minute.
 */
final class CronController extends Controller
{
    public function __construct(
        private readonly CronRunner $runner,
    ) {}

    public function run(Request $request): JsonResponse
    {
        $secret = config('webblok.cron_secret');

        if (! is_string($secret) || $request->query('secret') !== $secret) {
            abort(403, 'Invalid cron secret.');
        }

        $this->runner->run();

        return response()->json(['ran_at' => now()->toISOString()]);
    }
}
