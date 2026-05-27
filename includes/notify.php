<?php
require_once __DIR__ . '/auth.php';

function notify_user($conn, $user_id, $title, $message, $link = null)
{
    if (empty($user_id) || !$conn) return false;
    $stmt = mysqli_prepare($conn, 'INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isss', $user_id, $title, $message, $link);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function notify_role($conn, $role, $title, $message, $link = null)
{
    if (empty($role) || !$conn) return false;
    
    // Fetch all users with the given role to satisfy FK constraint
    $fetch_stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE role = ? LIMIT 100');
    if (!$fetch_stmt) return false;
    
    mysqli_stmt_bind_param($fetch_stmt, 's', $role);
    mysqli_stmt_execute($fetch_stmt);
    $res = mysqli_stmt_get_result($fetch_stmt);
    $users = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($fetch_stmt);
    
    // If no users found with this role, return gracefully without error
    if (empty($users)) {
        return false;
    }
    
    // Insert notification for each valid user with proper FK reference
    $count = 0;
    $ins_stmt = mysqli_prepare($conn, 'INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)');
    if (!$ins_stmt) return false;
    
    foreach ($users as $user) {
        $uid = (int)$user['id'];
        mysqli_stmt_bind_param($ins_stmt, 'isss', $uid, $title, $message, $link);
        if (mysqli_stmt_execute($ins_stmt)) {
            $count++;
        }
    }
    
    mysqli_stmt_close($ins_stmt);
    return $count > 0;
}

function get_unread_count($conn, $user_id, $role)
{
    if (!$conn) return 0;
    $sql = 'SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0 AND (user_id = ? OR role = ? OR role = "All")';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $role);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (int)($row['cnt'] ?? 0);
}

function get_notifications($conn, $user_id, $role, $limit = 10)
{
    if (!$conn) return [];
    $sql = 'SELECT * FROM notifications WHERE user_id = ? OR role = ? OR role = "All" ORDER BY created_at DESC LIMIT ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isi', $user_id, $role, $limit);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function mark_notification_read($conn, $id, $user_id, $role)
{
    if (!$conn) return false;
    $sql = 'UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR role = ? OR role = "All")';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iis', $id, $user_id, $role);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function mark_all_notifications_read($conn, $user_id, $role)
{
    if (!$conn) return false;
    $sql = 'UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (user_id = ? OR role = ? OR role = "All")';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $role);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

?>
