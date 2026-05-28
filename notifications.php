<?php
require 'db.php';
require_once __DIR__ . '/includes/notify.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$all_notifs = get_notifications($conn, $user_id, $role, 200);

$pageTitle = 'Notifications - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <h1 class="h2">Notifications</h1>
                <?php if ($role === 'Admin'): ?>
                    <form method="post" action="mark-notification.php">
                        <input type="hidden" name="all" value="1">
                        <input type="hidden" name="return" value="notifications.php">
                        <button class="btn btn-sm btn-outline-primary">Mark all read</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (!empty($all_notifs)): ?>
                        <ul class="list-group">
                            <?php foreach ($all_notifs as $n): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start <?= $n['is_read'] ? '' : 'bg-light' ?>">
                                    <div>
                                        <strong><?= htmlspecialchars($n['title']) ?></strong>
                                        <div class="small text-muted"><?= htmlspecialchars($n['message']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($n['created_at']) ?></div>
                                    </div>
                                    <div class="ms-3 text-end">
                                        <?php if (!$n['is_read']): ?>
                                            <form method="post" action="mark-notification.php">
                                                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                                <input type="hidden" name="return" value="notifications.php">
                                                <button class="btn btn-sm btn-primary">Mark read</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-success">Read</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info">No notifications.</div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php'; ?>