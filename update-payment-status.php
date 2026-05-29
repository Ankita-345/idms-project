<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Delivery'])) {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = $_GET['status'] ?? '';
$mode = $_GET['mode'] ?? '';

if ($order_id <= 0 || !in_array($status, ['paid', 'failed'])) {
    header('Location: orders.php');
    exit;
}

if ($status === 'paid' && !in_array($mode, ['online', 'offline'])) {
    header('Location: orders.php');
    exit;
}

$paid_at = $status === 'paid' ? date('Y-m-d H:i:s') : null;

$stmt = mysqli_prepare($conn, 'UPDATE orders SET payment_status = ?, payment_mode = ?, paid_at = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'sssi', $status, $mode, $paid_at, $order_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: orders.php?success=Payment status updated successfully.');
exit;