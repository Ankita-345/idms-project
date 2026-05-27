<?php
// db.php - Database connection and secure session setup
require_once __DIR__ . '/includes/auth.php';

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'idms';

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $secure ? '1' : '0');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_name('IDMSSESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
} elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_regenerate_id(true);
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

$conn = mysqli_connect($host, $user, $password, $database);
if (!$conn) {
    redirect_with_message('login.php', 'Database connection failed. Please try again later.', 'danger');
}

mysqli_set_charset($conn, 'utf8mb4');

$public_pages = [
    'login.php',
    'register.php',
    'logout.php',
];

$role_requirements = [
    'clients.php' => ['Admin', 'Manager', 'Operations Manager'],
    'add-client.php' => ['Admin', 'Manager', 'Operations Manager'],
    'edit-client.php' => ['Admin', 'Manager', 'Operations Manager'],
    'delete-client.php' => ['Admin', 'Manager', 'Operations Manager'],
    'assign-order.php' => ['Admin', 'Manager'],
    'delivery-teams.php' => ['Admin', 'Manager'],
    'add-delivery-team.php' => ['Admin', 'Manager'],
    'edit-delivery-team.php' => ['Admin', 'Manager'],
    'delete-delivery-team.php' => ['Admin', 'Manager'],
    'inventory.php' => ['Admin', 'Manager'],
    'add-stock.php' => ['Admin', 'Manager'],
    'update-stock.php' => ['Admin', 'Manager'],
    'proof-of-delivery.php' => ['Delivery'],
];

$current_page = basename($_SERVER['SCRIPT_NAME']);
if (!in_array($current_page, $public_pages, true)) {
    if (!is_logged_in()) {
        redirect_to('login.php?error=' . urlencode('Please login to continue.'));
    }

    if (isset($role_requirements[$current_page]) && !in_array(get_current_user_role(), $role_requirements[$current_page], true)) {
        redirect_with_message('dashboard.php', 'Access denied.', 'danger');
    }
}
