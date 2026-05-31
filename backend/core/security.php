<?php
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_base_url(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $backendPos = strpos($scriptDir, '/backend');
    if ($backendPos !== false) {
        $scriptDir = substr($scriptDir, 0, $backendPos);
    }
    $scriptDir = rtrim($scriptDir, '/');
    return ($scriptDir === '' ? '' : $scriptDir) . '/';
}

function url(string $path = ''): string
{
    return app_base_url() . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted)) {
            http_response_code(403);
            exit('Jeton CSRF invalide. Recharge la page puis réessaie.');
        }
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest';
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function finish_response(bool $ok, string $message, string $redirectPath = 'index.php', array $extra = []): never
{
    if (wants_json()) {
        json_response(array_merge(['success' => $ok, 'message' => $message], $extra), $ok ? 200 : 400);
    }
    flash($ok ? 'success' : 'error', $message);
    redirect($redirectPath);
}

function money_format_fr(float $amount): string
{
    return number_format($amount, 2, ',', ' ') . ' €';
}
