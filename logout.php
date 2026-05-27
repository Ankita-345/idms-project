<?php
require 'db.php';

// Clear all session data and destroy session securely.
if (session_status() === PHP_SESSION_ACTIVE) {
    // Unset all session variables
    session_unset();

    // Clear session cookie if used
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $name = session_name();
        $expire = time() - 42000;

        // PHP 7.3+ supports options array for setcookie
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            setcookie($name, '', [
                'expires' => $expire,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => !empty($params['secure']),
                'httponly' => !empty($params['httponly']),
                'samesite' => 'Lax',
            ]);
        } else {
            // Fallback for older PHP versions: use legacy signature
            setcookie($name, '', $expire, $params['path'] ?? '/', $params['domain'] ?? '', !empty($params['secure']), !empty($params['httponly']));
            // Attempt to append SameSite using header if available
            if (!headers_sent()) {
                $cookie = rawurlencode($name) . '=; Expires=' . gmdate('D, d-M-Y H:i:s T', $expire) . '; Path=' . ($params['path'] ?? '/') . (isset($params['domain']) && $params['domain'] ? '; Domain=' . $params['domain'] : '') . (!empty($params['secure']) ? '; Secure' : '') . (!empty($params['httponly']) ? '; HttpOnly' : '') . '; SameSite=Lax';
                header('Set-Cookie: ' . $cookie, false);
            }
        }
    }

    // Destroy the session data on the server
    session_destroy();
}

// Prevent browser from caching authenticated pages so back button won't show protected content
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect to login page
header('Location: login.php');
exit;
