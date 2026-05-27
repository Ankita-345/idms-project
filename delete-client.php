<?php
require 'db.php';

require_role(['Admin', 'Manager', 'Operations Manager']);

// Get client ID from URL
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($client_id === 0) {
    header('Location: clients.php');
    exit;
}

// Verify client exists
$check_stmt = mysqli_prepare($conn, 'SELECT id FROM clients WHERE id = ?');
mysqli_stmt_bind_param($check_stmt, 'i', $client_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) === 0) {
    mysqli_stmt_close($check_stmt);
    header('Location: clients.php');
    exit;
}
mysqli_stmt_close($check_stmt);

// Delete client and associated addresses (due to CASCADE)
$delete_stmt = mysqli_prepare($conn, 'DELETE FROM clients WHERE id = ?');
mysqli_stmt_bind_param($delete_stmt, 'i', $client_id);

if (mysqli_stmt_execute($delete_stmt)) {
    mysqli_stmt_close($delete_stmt);
    header('Location: clients.php?deleted=1');
    exit;
} else {
    mysqli_stmt_close($delete_stmt);
    header('Location: clients.php?error=1');
    exit;
}
