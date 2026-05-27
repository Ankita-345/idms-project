<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

// Confirm existence
$chk = mysqli_prepare($conn, 'SELECT id FROM orders WHERE id = ?');
mysqli_stmt_bind_param($chk, 'i', $order_id);
mysqli_stmt_execute($chk);
mysqli_stmt_store_result($chk);
if (mysqli_stmt_num_rows($chk) === 0) {
    mysqli_stmt_close($chk);
    header('Location: orders.php');
    exit;
}
mysqli_stmt_close($chk);

$del = mysqli_prepare($conn, 'DELETE FROM orders WHERE id = ?');
mysqli_stmt_bind_param($del, 'i', $order_id);
if (mysqli_stmt_execute($del)) {
    mysqli_stmt_close($del);
    header('Location: orders.php?deleted=1');
    exit;
} else {
    mysqli_stmt_close($del);
    header('Location: orders.php?error=1');
    exit;
}
