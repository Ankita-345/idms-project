<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    header('Location: dashboard.php');
    exit;
}

$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($team_id <= 0) {
    header('Location: delivery-teams.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'DELETE FROM delivery_teams WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $team_id);
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header('Location: delivery-teams.php');
    exit;
}
mysqli_stmt_close($stmt);
header('Location: delivery-teams.php?error=1');
exit;
