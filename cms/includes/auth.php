<?php
require_once __DIR__ . '/db.php';

/**
 * Start session if not already started.
 */
function session_init(): void
{
    if (session_id() === '') {
        // Use a writable path for sessions on shared hosting
        $sess_path = sys_get_temp_dir();
        if (is_writable($sess_path)) {
            session_save_path($sess_path);
        }
        session_set_cookie_params([
            'lifetime' => 86400,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Returns true if a user is currently logged in.
 */
function is_logged_in(): bool
{
    session_init();
    return !empty($_SESSION['user_id']);
}

/**
 * Returns the current user's session data as an array, or null if not logged in.
 */
function get_cms_user(): ?array
{
    session_init();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'           => $_SESSION['user_id'],
        'email'        => $_SESSION['email'],
        'display_name' => $_SESSION['display_name'],
        'role'         => $_SESSION['role'],
    ];
}

/**
 * Redirects to login.php if the user is not logged in.
 */
function require_login(): void
{
    session_init();
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Redirects to dashboard.php if the user is not an admin.
 * Also calls require_login() first.
 */
function require_admin(): void
{
    require_login();
    session_init();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Stores user data in the session after successful login.
 */
function login_user(array $user): void
{
    session_init();
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['email']        = $user['email'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['role']         = $user['role'];
}

/**
 * Destroys the session and clears the cookie.
 */
function logout_user(): void
{
    session_init();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Sets a flash message in the session to be displayed after a redirect.
 */
function set_flash(string $message, string $type = 'success'): void
{
    session_init();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
}

/**
 * Returns and clears the current flash message, or null if none.
 */
function get_flash(): ?array
{
    session_init();
    if (!empty($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type'    => $_SESSION['flash_type'] ?? 'success',
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}
