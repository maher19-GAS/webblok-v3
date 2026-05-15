<?php
/**
 * WebBook SaaS — Bootstrap
 * Included by every API entry-point.
 */

require_once __DIR__ . '/config.php';

// ── Error handling ─────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', WB_DATA . '/error.log');

// ── Ensure data directories exist ──────────────────────
foreach ([WB_DATA, WB_DB_BOOKS, WB_MD_ROOT, WB_RATE_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}

// ── CORS headers ───────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
$allowed = WB_ALLOWED_ORIGINS;
if (in_array('*', $allowed) || in_array($origin, $allowed)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://2030b.com');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WB-Book, X-WB-User');
header('Access-Control-Max-Age: 86400');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── Helpers ────────────────────────────────────────────

/** Send JSON response and exit */
function wb_json(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Send error and exit */
function wb_error(string $message, int $code = 400, array $extra = []): never {
    wb_json(array_merge(['error' => $message, 'code' => $code], $extra), $code);
}

/** Get request body as decoded JSON array */
function wb_body(): array {
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?? []) : [];
    }
    return $body;
}

/** Sanitise a string input */
function wb_str(mixed $v, int $max = 255): string {
    return mb_substr(trim((string)($v ?? '')), 0, $max);
}

/** Generate a UUID v4 */
function wb_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Current Unix timestamp (ms) */
function wb_ms(): int { return (int)(microtime(true) * 1000); }

// ── Token helpers ──────────────────────────────────────

/** Create a signed token: base64(payload).base64(hmac) */
function wb_token_create(array $payload): string {
    $payload['iat'] = time();
    $payload['exp'] = time() + WB_TOKEN_TTL;
    $p = base64_encode(json_encode($payload));
    $sig = base64_encode(hash_hmac('sha256', $p, WB_TOKEN_SECRET, true));
    return $p . '.' . $sig;
}

/** Verify token, return payload array or null */
function wb_token_verify(string $token): ?array {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    [$p, $sig] = $parts;
    $expected = base64_encode(hash_hmac('sha256', $p, WB_TOKEN_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64_decode($p), true);
    if (!is_array($payload)) return null;
    if (isset($payload['exp']) && $payload['exp'] < time()) return null;
    return $payload;
}

/** Extract Bearer token from Authorization header */
function wb_bearer(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $h, $m)) return $m[1];
    return null;
}

/** Require valid auth; return payload or send 401 */
function wb_require_auth(): array {
    $tok = wb_bearer();
    if (!$tok) wb_error('Missing Authorization header', 401);
    $payload = wb_token_verify($tok);
    if (!$payload) wb_error('Invalid or expired token', 401);
    return $payload;
}

// ── Rate limiting ──────────────────────────────────────
function wb_rate_check(string $key = ''): void {
    $ip  = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $id  = md5($ip . $key);
    $f   = WB_RATE_DIR . '/' . $id . '.json';
    $now = time();
    $data = ['hits' => 0, 'window_start' => $now];
    if (is_file($f)) {
        $data = json_decode(file_get_contents($f), true) ?? $data;
    }
    if ($now - $data['window_start'] > WB_RATE_WINDOW) {
        $data = ['hits' => 0, 'window_start' => $now];
    }
    $data['hits']++;
    file_put_contents($f, json_encode($data), LOCK_EX);
    if ($data['hits'] > WB_RATE_LIMIT) {
        wb_error('Rate limit exceeded', 429);
    }
}

// ── Auto-load lib files ────────────────────────────────
foreach (glob(__DIR__ . '/lib/*.php') as $_wbLib) {
    require_once $_wbLib;
}

// ── Database helpers ───────────────────────────────────

/** Open (and optionally create) a SQLite database */
function wb_db(string $path): PDO {
    static $cache = [];
    if (!isset($cache[$path])) {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA synchronous=NORMAL;');
        $cache[$path] = $pdo;
    }
    return $cache[$path];
}

/** Open the main (users + books registry) database */
function wb_main_db(): PDO { return wb_db(WB_DB_MAIN); }

/** Open a per-book database (creates file if absent) */
function wb_book_db(string $bookId): PDO {
    $path = WB_DB_BOOKS . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $bookId) . '.sqlite';
    return wb_db($path);
}

/** Quick SELECT helper → array of rows */
function wb_select(PDO $db, string $sql, array $params = []): array {
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Quick SELECT one row */
function wb_select_one(PDO $db, string $sql, array $params = []): ?array {
    $rows = wb_select($db, $sql, $params);
    return $rows[0] ?? null;
}

/** Quick INSERT/UPDATE/DELETE → lastInsertId or rowCount */
function wb_exec(PDO $db, string $sql, array $params = []): string|int {
    $st = $db->prepare($sql);
    $st->execute($params);
    $lid = $db->lastInsertId();
    return $lid ?: $st->rowCount();
}
