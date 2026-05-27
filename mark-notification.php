<?php
require 'db.php';
require_once __DIR__ . '/includes/notify.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $id = (int)$_POST['id'];
        mark_notification_read($conn, $id, $user_id, $role);
    } elseif (isset($_POST['all'])) {
        mark_all_notifications_read($conn, $user_id, $role);
    }
}

header('Location: ' . ($_POST['return'] ?? 'notifications.php'));
exit;
