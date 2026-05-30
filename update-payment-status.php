<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Delivery'])) {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

if ($order_id > 0 && in_array($status, ['paid', 'failed'])) {
    $sql = '';
    $params = [];
    $types = '';

    if ($status === 'paid') {
        if (in_array($mode, ['online', 'offline'])) {
            // Admins can mark both, Delivery can only mark offline
            if ($_SESSION['role'] === 'Admin' || ($_SESSION['role'] === 'Delivery' && $mode === 'offline')) {
                $sql = "UPDATE orders SET payment_status = 'paid', payment_mode = ?, paid_at = NOW() WHERE id = ?";
                $params = [$mode, $order_id];
                $types = 'si';
            }
        }
    } elseif ($status === 'failed') {
        // When a payment fails, clear the previous proof to allow re-upload
        $sql = "UPDATE orders SET payment_status = 'failed', payment_proof = NULL, payment_submitted_at = NULL WHERE id = ?";
        $params = [$order_id];
        $types = 'i';
    }

    if (!empty($sql)) {
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success_message = "Payment status updated successfully.";
            header('Location: orders.php?success=' . urlencode($success_message));
            exit;
        }
    }
}

$error_message = "Invalid action or missing parameters.";
header('Location: orders.php?error=' . urlencode($error_message));
exit;