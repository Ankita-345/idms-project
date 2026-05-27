<?php
function h($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function get_current_user_role()
{
    return $_SESSION['role'] ?? '';
}

function redirect_to($url)
{
    header('Location: ' . $url);
    exit;
}

function redirect_with_message($url, $message, $type = 'danger')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
    redirect_to($url);
}

function require_login()
{
    if (!is_logged_in()) {
        redirect_to('login.php?error=' . urlencode('Please login to continue.'));
    }
}

function require_role(array $allowed_roles, $redirect = 'dashboard.php')
{
    require_login();
    if (!in_array(get_current_user_role(), $allowed_roles, true)) {
        redirect_with_message($redirect, 'Unauthorized access.', 'danger');
    }
}

function display_flash_messages()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $flash) {
            $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
            echo '<div class="container py-2"><div class="alert alert-' . h($type) . ' alert-dismissible fade show" role="alert">' . h($flash['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
        }
        unset($_SESSION['flash']);
    }
}

function sanitize_filename($filename)
{
    $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($filename));
    return substr($filename, 0, 200);
}

function is_valid_image_file($tmpPath)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return false;
    }
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);
    return in_array($mime, ['image/jpeg', 'image/png'], true);
}
