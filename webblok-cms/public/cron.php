<?php

/**
 * WebBlok no-SSH cron entrypoint.
 *
 * Many shared/managed hosts let you schedule a PHP file (cPanel "Cron Jobs",
 * or a URL pinger) but do not give you SSH to run `php artisan schedule:run`.
 * Point such a scheduler at this file once per minute, e.g.:
 *
 *   * * * * * php /path/to/public/cron.php >/dev/null 2>&1
 *   or hit https://your-domain/cron.php?secret=YOUR_SECRET
 *
 * It boots the framework and forwards to the same CronRunner used by the
 * /cron/run web route, guarded by WEBBLOK_CRON_SECRET.
 */

declare(strict_types=1);

use App\Services\CronRunner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$expected = config('webblok.cron_secret');
$provided = $_GET['secret'] ?? ($argv[1] ?? null);

// When invoked from a real CLI cron job (no web request), allow without secret.
$isCli = PHP_SAPI === 'cli';

if (! $isCli) {
    if (! is_string($expected) || $provided !== $expected) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid cron secret.']);
        exit(1);
    }
}

/** @var CronRunner $runner */
$runner = $app->make(CronRunner::class);
$runner->run();

$payload = ['ran_at' => date(DATE_ATOM)];

if ($isCli) {
    fwrite(STDOUT, json_encode($payload).PHP_EOL);
} else {
    header('Content-Type: application/json');
    echo json_encode($payload);
}
