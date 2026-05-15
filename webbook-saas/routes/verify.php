<?php
/**
 * WebBook SaaS — Email Verification & Password Reset Routes
 *
 *  POST  /auth/verify/send         → send verification email
 *  GET   /auth/verify/{token}      → confirm email address
 *  POST  /auth/reset/request       → send password-reset email
 *  POST  /auth/reset/{token}       → set new password with valid token
 */

require_once __DIR__ . '/../lib/MainDB.php';

function route_verify(string $method, array $seg): void
{
    $group = $seg[0] ?? '';   // 'verify' or 'reset'
    $sub   = $seg[1] ?? '';

    match (true) {
        $method === 'POST' && $group === 'verify' && $sub === 'send' => verify_send(),
        $method === 'GET'  && $group === 'verify' && $sub !== ''     => verify_confirm($sub),
        $method === 'POST' && $group === 'reset'  && $sub === 'request' => reset_request(),
        $method === 'POST' && $group === 'reset'  && $sub !== '' && $sub !== 'request' => reset_confirm($sub),
        default => wb_error('Not found', 404),
    };
}

// ── POST /auth/verify/send ────────────────────────────
function verify_send(): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $user = wb_select_one($db, 'SELECT * FROM users WHERE id = ?', [$auth['uid']]);
    if (!$user) wb_error('User not found', 404);
    if ($user['is_verified']) wb_json(['ok' => true, 'message' => 'Email already verified']);

    // Invalidate existing tokens
    wb_exec($db, 'DELETE FROM verify_tokens WHERE user_id = ?', [$user['id']]);

    [$rawToken, $hash] = _make_token();
    $now = wb_ms();
    $exp = $now + (WB_VERIFY_TOKEN_TTL * 1000);

    wb_exec($db,
        'INSERT INTO verify_tokens (id, user_id, token_hash, created_at, expires_at, used)
         VALUES (?,?,?,?,?,0)',
        [wb_uuid(), $user['id'], $hash, $now, $exp]
    );

    $verifyUrl = WB_API_BASE . '/auth/verify/' . $rawToken;

    _send_email(
        $user['email'],
        'تأكيد البريد الإلكتروني — ' . WB_APP_NAME,
        "مرحباً {$user['display_name']},\n\n"
        . "انقر على الرابط التالي لتأكيد بريدك الإلكتروني:\n{$verifyUrl}\n\n"
        . "الرابط صالح لمدة 3 أيام.\n\n"
        . "إذا لم تطلب هذا، تجاهل الرسالة.",
        "<p>مرحباً <strong>{$user['display_name']}</strong>,</p>"
        . "<p>انقر <a href=\"{$verifyUrl}\">هنا</a> لتأكيد بريدك الإلكتروني.</p>"
        . "<p>أو انسخ الرابط: <code>{$verifyUrl}</code></p>"
        . "<p><small>صالح 3 أيام.</small></p>"
    );

    wb_json(['ok' => true, 'message' => 'Verification email sent']);
}

// ── GET /auth/verify/{token} ──────────────────────────
function verify_confirm(string $rawToken): void
{
    $hash = hash('sha256', $rawToken);
    $db   = wb_init_main_db();
    $now  = wb_ms();

    $rec = wb_select_one($db,
        'SELECT * FROM verify_tokens WHERE token_hash = ? AND used = 0 AND expires_at > ?',
        [$hash, $now]
    );
    if (!$rec) wb_error('Invalid or expired verification link', 400);

    // Mark token used + verify user
    wb_exec($db, 'UPDATE verify_tokens SET used = 1 WHERE id = ?', [$rec['id']]);
    wb_exec($db, 'UPDATE users SET is_verified = 1, updated_at = ? WHERE id = ?',
        [$now, $rec['user_id']]);

    // Return JSON (client redirects)
    wb_json(['ok' => true, 'message' => 'Email verified successfully']);
}

// ── POST /auth/reset/request ──────────────────────────
function reset_request(): void
{
    $b     = wb_body();
    $email = wb_str($b['email'] ?? '');
    if (!$email) wb_error('email required', 422);

    $db   = wb_init_main_db();
    $user = wb_select_one($db,
        'SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);

    // Always return 200 to prevent user enumeration
    if (!$user) { wb_json(['ok' => true, 'message' => 'If that email exists, a reset link was sent']); }

    // Delete old tokens
    wb_exec($db, 'DELETE FROM reset_tokens WHERE user_id = ?', [$user['id']]);

    [$rawToken, $hash] = _make_token();
    $now = wb_ms();
    $exp = $now + (WB_RESET_TOKEN_TTL * 1000);

    wb_exec($db,
        'INSERT INTO reset_tokens (id, user_id, token_hash, created_at, expires_at, used)
         VALUES (?,?,?,?,?,0)',
        [wb_uuid(), $user['id'], $hash, $now, $exp]
    );

    $resetUrl = WB_APP_URL . '/reset-password?token=' . $rawToken;

    _send_email(
        $user['email'],
        'إعادة تعيين كلمة المرور — ' . WB_APP_NAME,
        "مرحباً {$user['display_name']},\n\n"
        . "انقر على الرابط التالي لإعادة تعيين كلمة المرور:\n{$resetUrl}\n\n"
        . "الرابط صالح لمدة ساعتين.\n\n"
        . "إذا لم تطلب هذا، تجاهل الرسالة.",
        "<p>مرحباً <strong>{$user['display_name']}</strong>,</p>"
        . "<p>انقر <a href=\"{$resetUrl}\">هنا</a> لإعادة تعيين كلمة المرور.</p>"
        . "<p>أو انسخ الرابط: <code>{$resetUrl}</code></p>"
        . "<p><small>صالح لمدة ساعتين.</small></p>"
    );

    wb_json(['ok' => true, 'message' => 'If that email exists, a reset link was sent']);
}

// ── POST /auth/reset/{token} ──────────────────────────
function reset_confirm(string $rawToken): void
{
    $b        = wb_body();
    $password = $b['password'] ?? '';

    if (strlen($password) < 8)
        wb_error('Password must be at least 8 characters', 422);

    $hash = hash('sha256', $rawToken);
    $db   = wb_init_main_db();
    $now  = wb_ms();

    $rec = wb_select_one($db,
        'SELECT * FROM reset_tokens WHERE token_hash = ? AND used = 0 AND expires_at > ?',
        [$hash, $now]
    );
    if (!$rec) wb_error('Invalid or expired reset link', 400);

    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    wb_exec($db, 'UPDATE reset_tokens SET used = 1 WHERE id = ?', [$rec['id']]);
    wb_exec($db,
        'UPDATE users SET password = ?, updated_at = ? WHERE id = ?',
        [$newHash, $now, $rec['user_id']]
    );

    // Revoke all sessions for security
    wb_exec($db, 'DELETE FROM sessions WHERE user_id = ?', [$rec['user_id']]);

    wb_json(['ok' => true, 'message' => 'Password reset successfully. Please log in again.']);
}

// ── Private helpers ────────────────────────────────────

/** Generate a raw token + sha256 hash pair. */
function _make_token(): array
{
    $raw  = bin2hex(random_bytes(32));   // 64-char hex
    $hash = hash('sha256', $raw);
    return [$raw, $hash];
}

/** Send an email (PHP mail() fallback; replace with SMTP/SES in production). */
function _send_email(string $to, string $subject, string $text, string $html = ''): bool
{
    if (!WB_MAIL_ENABLED) {
        error_log("[WB-MAIL] Would send to {$to}: {$subject}");
        return true;
    }

    $from    = WB_MAIL_FROM;
    $headers = implode("\r\n", [
        "From: {$from}",
        "Reply-To: {$from}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "X-Mailer: WebBook-SaaS/" . WB_VERSION,
    ]);

    $body = $html ?: nl2br(htmlspecialchars($text));
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
