<?php
/**
 * WebBook SaaS — Media Upload Routes
 *
 *  POST  /upload/cover          → upload book cover image
 *  POST  /upload/avatar         → upload user avatar
 *  GET   /upload/list           → list user's uploaded files
 *  DELETE /upload/{filename}    → delete an uploaded file
 */

require_once __DIR__ . '/../lib/MainDB.php';

function route_upload(string $method, array $seg): void
{
    $action = $seg[0] ?? '';

    match (true) {
        $method === 'POST'   && $action === 'cover'     => upload_cover(),
        $method === 'POST'   && $action === 'avatar'    => upload_avatar(),
        $method === 'GET'    && $action === 'list'      => upload_list(),
        $method === 'DELETE' && $action !== ''          => upload_delete($action),
        default => wb_error('Not found', 404),
    };
}

// ── POST /upload/cover ────────────────────────────────
function upload_cover(): void
{
    $auth = wb_require_auth();
    _ensure_upload_dir($auth['uid']);

    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK)
        wb_error('No file uploaded or upload error', 422);

    _validate_upload($file, $auth['uid']);

    $ext  = _safe_ext($file['name']);
    $name = 'cover-' . wb_uuid() . '.' . $ext;
    $dest = _user_upload_dir($auth['uid']) . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest))
        wb_error('Failed to save file', 500);

    // Optionally link to a book
    $bookId = wb_str(wb_body()['book_id'] ?? $_POST['book_id'] ?? '');
    if ($bookId) {
        $db   = wb_init_main_db();
        $book = wb_select_one($db, 'SELECT id FROM books WHERE id = ? AND owner_id = ?',
            [$bookId, $auth['uid']]);
        if ($book) {
            $url = _upload_url($auth['uid'], $name);
            wb_exec($db, 'UPDATE books SET cover_url = ?, updated_at = ? WHERE id = ?',
                [$url, wb_ms(), $bookId]);
        }
    }

    wb_json([
        'ok'       => true,
        'filename' => $name,
        'url'      => _upload_url($auth['uid'], $name),
        'size'     => $file['size'],
        'mime'     => $file['type'],
    ], 201);
}

// ── POST /upload/avatar ───────────────────────────────
function upload_avatar(): void
{
    $auth = wb_require_auth();
    _ensure_upload_dir($auth['uid']);

    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK)
        wb_error('No file uploaded or upload error', 422);

    _validate_upload($file, $auth['uid']);

    $ext  = _safe_ext($file['name']);
    $name = 'avatar-' . wb_uuid() . '.' . $ext;
    $dest = _user_upload_dir($auth['uid']) . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest))
        wb_error('Failed to save file', 500);

    $url = _upload_url($auth['uid'], $name);

    // Update user avatar_url
    $db = wb_init_main_db();
    wb_exec($db, 'UPDATE users SET avatar_url = ?, updated_at = ? WHERE id = ?',
        [$url, wb_ms(), $auth['uid']]);

    wb_json([
        'ok'       => true,
        'filename' => $name,
        'url'      => $url,
        'size'     => $file['size'],
    ], 201);
}

// ── GET /upload/list ──────────────────────────────────
function upload_list(): void
{
    $auth = wb_require_auth();
    $dir  = _user_upload_dir($auth['uid']);

    $files = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $path    = $dir . '/' . $f;
            $mime    = mime_content_type($path) ?: 'application/octet-stream';
            $files[] = [
                'filename'   => $f,
                'url'        => _upload_url($auth['uid'], $f),
                'size'       => filesize($path),
                'mime'       => $mime,
                'modified'   => (int)(filemtime($path) * 1000),
            ];
        }
    }

    // Sort newest first
    usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);

    wb_json(['files' => $files, 'total' => count($files)]);
}

// ── DELETE /upload/{filename} ─────────────────────────
function upload_delete(string $filename): void
{
    $auth = wb_require_auth();

    // Strict filename validation — no path traversal
    $safe = basename($filename);
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $safe))
        wb_error('Invalid filename', 422);

    $path = _user_upload_dir($auth['uid']) . '/' . $safe;
    if (!is_file($path)) wb_error('File not found', 404);

    @unlink($path);
    http_response_code(204);
    exit;
}

// ── Private helpers ────────────────────────────────────

function _user_upload_dir(string $userId): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    return WB_UPLOADS . '/' . $safe;
}

function _upload_url(string $userId, string $filename): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    return WB_UPLOAD_URL_BASE . '/' . $safe . '/' . $filename;
}

function _ensure_upload_dir(string $userId): void
{
    $dir = _user_upload_dir($userId);
    if (!is_dir($dir)) mkdir($dir, 0750, true);
}

function _safe_ext(string $filename): string
{
    $parts = explode('.', strtolower($filename));
    $ext   = end($parts);
    $allowed = ['jpg','jpeg','png','webp','gif','svg'];
    return in_array($ext, $allowed) ? $ext : 'bin';
}

function _validate_upload(array $file, string $userId): void
{
    if ($file['size'] > WB_UPLOAD_MAX_BYTES)
        wb_error('File too large. Max ' . (WB_UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB', 422);

    // MIME check (finfo)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, WB_UPLOAD_ALLOWED))
        wb_error("File type not allowed: {$mime}", 422);

    // Plan upload_mb limit (rough: count existing files)
    $dir  = _user_upload_dir($userId);
    $used = 0;
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $used += filesize($dir . '/' . $f);
        }
    }
    $limitBytes = wb_plan_limit($userId, 'upload_mb') * 1024 * 1024;
    if ($used + $file['size'] > $limitBytes)
        wb_error('Storage limit reached for your plan', 403);
}
