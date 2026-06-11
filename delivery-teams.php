<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$sql = 'SELECT dt.*, u.full_name AS assigned_user_name, COUNT(o.id) AS assigned_orders_count FROM delivery_teams dt LEFT JOIN users u ON dt.user_id = u.id LEFT JOIN orders o ON dt.id = o.assigned_team_id AND o.status IN (\'Assigned\', \'Picked\', \'In Transit\', \'Out for Delivery\')';
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(dt.driver_name LIKE ? OR dt.vehicle_type LIKE ? OR dt.route_allocation LIKE ? OR dt.shift_timing LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}
if ($status_filter !== '') {
    $where[] = 'dt.availability_status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' GROUP BY dt.id ORDER BY dt.id DESC';

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        $bindParams = array_merge([$types], $params);
        $refs = [];
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $teams = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $teams = [];
}

$statuses = ['Available', 'Busy', 'Offline'];
$vehicle_types = ['Truck', 'Van', 'Mini'];

$pageTitle = 'Delivery Teams - IDMS';
include 'includes/header.php';
?>
<style>
@media (max-width: 768px) {
    td:last-child {
        min-width: 180px;
    }

    td:last-child .btn {
        display: block;
        width: 100%;
        margin-bottom: 4px;
    }
}
</style>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

<main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-3 px-md-4 py-4">     
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 border-bottom pb-3">                <h1 class="h2">Delivery Teams</h1>
                <a href="add-delivery-team.php" class="btn btn-success">+ Add Team</a>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" class="row g-2">
                        <div class="col-12 col-md-5">
                            <input type="text" name="search" class="form-control" placeholder="Search driver, vehicle or route" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <button class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-12 col-md-2">
                            <a href="delivery-teams.php" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body table-responsive">
                    <?php if (!empty($teams)): ?>
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Driver</th>
                                    <th>Vehicle</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th>Assigned Orders</th>
                                    <th>Shift</th>
                                    <th>Route</th>
                                    <th>Delivery User</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $team): ?>
                                    <?php
                                        $status_colors = ['Available' => 'success', 'Busy' => 'warning', 'Offline' => 'secondary'];
                                        $badge = $status_colors[$team['availability_status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($team['driver_name']) ?></td>
                                        <td><?= htmlspecialchars($team['vehicle_type']) ?></td>
                                        <td><?= htmlspecialchars($team['vehicle_capacity']) ?> Kg</td>
                                        <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($team['availability_status']) ?></span></td>
                                        <td>
                                            <?php if ($team['assigned_orders_count'] > 0): ?>
                                                <span class="badge bg-primary"><?= $team['assigned_orders_count'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($team['shift_timing']) ?></td>
                                        <td><?= htmlspecialchars($team['route_allocation']) ?></td>
                                        <td><?= htmlspecialchars($team['assigned_user_name'] ?? 'Unmapped') ?></td>
                                        <td>
                                            <a href="orders.php?team_id=<?= $team['id'] ?>" class="btn btn-sm btn-info">View Orders</a>
                                            <a href="edit-delivery-team.php?id=<?= $team['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="delete-delivery-team.php?id=<?= $team['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this delivery team?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">No delivery teams found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';