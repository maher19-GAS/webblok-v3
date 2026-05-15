<?php
/**
 * WebBook SaaS — Webhook Routes
 *
 *  GET    /webhooks              → list user's webhooks
 *  POST   /webhooks              → create webhook
 *  GET    /webhooks/{id}         → get webhook
 *  PATCH  /webhooks/{id}         → update webhook
 *  DELETE /webhooks/{id}         → delete webhook
 *  POST   /webhooks/{id}/test    → fire a test ping
 *  GET    /webhooks/{id}/logs    → delivery log
 */

require_once __DIR__ . '/../lib/MainDB.php';

function route_webhooks(string $method, array $seg): void
{
    $id     = $seg[0] ?? null;
    $action = $seg[1] ?? null;

    match (true) {
        !$id && $method === 'GET'                          => wh_list(),
        !$id && $method === 'POST'                         => wh_create(),
        $id  && $method === 'GET'  && !$action             => wh_get($id),
        $id  && $method === 'PATCH' && !$action            => wh_update($id),
        $id  && $method === 'DELETE' && !$action           => wh_delete($id),
        $id  && $method === 'POST'  && $action === 'test'  => wh_test($id),
        $id  && $method === 'GET'   && $action === 'logs'  => wh_logs($id),
        default => wb_error('Not found', 404),
    };
}

// ── GET /webhooks ──────────────────────────────────────
function wh_list(): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $rows = wb_select($db,
        'SELECT id, book_id, url, events, is_active, created_at, last_fired, fail_count
         FROM webhooks WHERE user_id = ? ORDER BY created_at DESC',
        [$auth['uid']]
    );
    foreach ($rows as &$r) {
        $r['events'] = json_decode($r['events'] ?? '[]', true);
    }
    unset($r);
    wb_json(['webhooks' => $rows, 'total' => count($rows)]);
}

// ── POST /webhooks ─────────────────────────────────────
function wh_create(): void
{
    $auth = wb_require_auth();
    $b    = wb_body();
    $db   = wb_init_main_db();

    $url    = wb_str($b['url'] ?? '');
    $events = $b['events'] ?? WB_WEBHOOK_EVENTS;
    $bookId = wb_str($b['book_id'] ?? '');
    $secret = wb_str($b['secret']  ?? bin2hex(random_bytes(16)));

    if (!filter_var($url, FILTER_VALIDATE_URL))
        wb_error('Invalid webhook URL', 422);

    // Validate events
    $events = array_values(array_intersect((array)$events, WB_WEBHOOK_EVENTS));
    if (empty($events)) wb_error('At least one valid event required', 422);

    // Verify book ownership if specified
    if ($bookId) {
        $book = wb_select_one($db, 'SELECT id FROM books WHERE id = ? AND owner_id = ?',
            [$bookId, $auth['uid']]);
        if (!$book) wb_error('Book not found or access denied', 404);
    }

    $id  = wb_uuid();
    $now = wb_ms();
    wb_exec($db,
        'INSERT INTO webhooks (id, user_id, book_id, url, events, secret, is_active, created_at)
         VALUES (?,?,?,?,?,?,1,?)',
        [$id, $auth['uid'], $bookId ?: null, $url, json_encode($events), $secret, $now]
    );

    $row = wb_select_one($db, 'SELECT * FROM webhooks WHERE id = ?', [$id]);
    $row['events'] = json_decode($row['events'], true);
    wb_json($row, 201);
}

// ── GET /webhooks/{id} ────────────────────────────────
function wh_get(string $id): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $row  = wb_select_one($db,
        'SELECT * FROM webhooks WHERE id = ? AND user_id = ?', [$id, $auth['uid']]);
    if (!$row) wb_error('Webhook not found', 404);
    $row['events'] = json_decode($row['events'], true);
    wb_json($row);
}

// ── PATCH /webhooks/{id} ──────────────────────────────
function wh_update(string $id): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $wh   = wb_select_one($db,
        'SELECT id FROM webhooks WHERE id = ? AND user_id = ?', [$id, $auth['uid']]);
    if (!$wh) wb_error('Webhook not found', 404);

    $b      = wb_body();
    $fields = [];
    $params = [];

    if (isset($b['url'])) {
        $url = wb_str($b['url']);
        if (!filter_var($url, FILTER_VALIDATE_URL)) wb_error('Invalid URL', 422);
        $fields[] = 'url = ?'; $params[] = $url;
    }
    if (isset($b['events'])) {
        $ev = array_values(array_intersect((array)$b['events'], WB_WEBHOOK_EVENTS));
        if (empty($ev)) wb_error('At least one valid event required', 422);
        $fields[] = 'events = ?'; $params[] = json_encode($ev);
    }
    if (isset($b['is_active'])) {
        $fields[] = 'is_active = ?'; $params[] = $b['is_active'] ? 1 : 0;
    }
    if (isset($b['secret'])) {
        $fields[] = 'secret = ?'; $params[] = wb_str($b['secret'], 128);
    }
    if (empty($fields)) wb_error('No valid fields to update', 422);

    $params[] = $id;
    wb_exec($db, 'UPDATE webhooks SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

    $row = wb_select_one($db, 'SELECT * FROM webhooks WHERE id = ?', [$id]);
    $row['events'] = json_decode($row['events'], true);
    wb_json($row);
}

// ── DELETE /webhooks/{id} ─────────────────────────────
function wh_delete(string $id): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $n    = wb_exec($db,
        'DELETE FROM webhooks WHERE id = ? AND user_id = ?', [$id, $auth['uid']]);
    if (!$n) wb_error('Webhook not found', 404);
    http_response_code(204);
    exit;
}

// ── POST /webhooks/{id}/test ──────────────────────────
function wh_test(string $id): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $wh   = wb_select_one($db,
        'SELECT * FROM webhooks WHERE id = ? AND user_id = ?', [$id, $auth['uid']]);
    if (!$wh) wb_error('Webhook not found', 404);

    $payload = [
        'event'   => 'ping',
        'test'    => true,
        'ts'      => wb_ms(),
        'webhook' => $id,
        'user_id' => $auth['uid'],
    ];

    $result = wb_fire_webhook($wh, 'ping', $payload);
    wb_json($result);
}

// ── GET /webhooks/{id}/logs ───────────────────────────
function wh_logs(string $id): void
{
    $auth  = wb_require_auth();
    $db    = wb_init_main_db();
    $wh    = wb_select_one($db,
        'SELECT id FROM webhooks WHERE id = ? AND user_id = ?', [$id, $auth['uid']]);
    if (!$wh) wb_error('Webhook not found', 404);

    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $logs  = wb_select($db,
        'SELECT id, event, status_code, success, fired_at
         FROM webhook_deliveries WHERE webhook_id = ?
         ORDER BY fired_at DESC LIMIT ?',
        [$id, $limit]
    );
    wb_json(['logs' => $logs, 'total' => count($logs)]);
}

// ── Shared: fire a webhook (used by books.php publish trigger) ──
function wb_fire_webhook(array $wh, string $event, array $payload): array
{
    $db      = wb_init_main_db();
    $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ts      = (string)wb_ms();
    $sig     = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $wh['secret'] ?: WB_WEBHOOK_SECRET);

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'X-WB-Event: ' . $event,
            'X-WB-Signature: ' . $sig,
            'X-WB-Timestamp: ' . $ts,
            'User-Agent: WebBook-SaaS/' . WB_VERSION,
        ]),
        'content'       => $body,
        'timeout'       => WB_WEBHOOK_TIMEOUT,
        'ignore_errors' => true,
    ]]);

    $now        = wb_ms();
    $response   = '';
    $statusCode = 0;
    $success    = false;

    try {
        $resp = @file_get_contents($wh['url'], false, $ctx);
        $response = substr((string)$resp, 0, 500);
        // Parse HTTP status from $http_response_header
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $statusCode = (int)($m[1] ?? 0);
        }
        $success = $statusCode >= 200 && $statusCode < 300;
    } catch (\Throwable $e) {
        $response = $e->getMessage();
    }

    // Log delivery
    $deliveryId = wb_uuid();
    wb_exec($db,
        'INSERT INTO webhook_deliveries
            (id, webhook_id, event, payload, status_code, response, fired_at, success)
         VALUES (?,?,?,?,?,?,?,?)',
        [$deliveryId, $wh['id'], $event, $body, $statusCode, $response, $now, $success ? 1 : 0]
    );

    // Update webhook stats
    if ($success) {
        wb_exec($db,
            'UPDATE webhooks SET last_fired = ?, fail_count = 0 WHERE id = ?',
            [$now, $wh['id']]);
    } else {
        wb_exec($db,
            'UPDATE webhooks SET last_fired = ?, fail_count = fail_count + 1 WHERE id = ?',
            [$now, $wh['id']]);
        // Auto-disable after 10 consecutive failures
        wb_exec($db,
            'UPDATE webhooks SET is_active = 0 WHERE id = ? AND fail_count >= 10',
            [$wh['id']]);
    }

    return [
        'ok'          => $success,
        'status_code' => $statusCode,
        'event'       => $event,
        'fired_at'    => $now,
        'delivery_id' => $deliveryId,
    ];
}

/** Fire all matching webhooks for a given book/user event (called from books.php). */
function wb_dispatch_event(string $userId, ?string $bookId, string $event, array $payload): void
{
    $db  = wb_init_main_db();
    $sql = 'SELECT * FROM webhooks WHERE user_id = ? AND is_active = 1
            AND (book_id IS NULL OR book_id = ?)
            AND events LIKE ?';
    $rows = wb_select($db, $sql, [$userId, $bookId, '%"' . $event . '"%']);
    foreach ($rows as $wh) {
        try { wb_fire_webhook($wh, $event, $payload); } catch (\Throwable) {}
    }
}
