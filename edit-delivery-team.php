<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($team_id <= 0) {
    header('Location: delivery-teams.php');
    exit;
}

$vehicle_types = ['Truck', 'Van', 'Mini'];
$statuses = ['Available', 'Busy', 'Offline'];
$error = '';
$success = '';
$delivery_users = [];
$user_stmt = mysqli_prepare($conn, "SELECT id, full_name, email FROM users WHERE role = 'Delivery' ORDER BY full_name");
if ($user_stmt) {
    mysqli_stmt_execute($user_stmt);
    $user_res = mysqli_stmt_get_result($user_stmt);
    if ($user_res) {
        $delivery_users = mysqli_fetch_all($user_res, MYSQLI_ASSOC);
    }
    mysqli_stmt_close($user_stmt);
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM delivery_teams WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $team_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$team = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$team) {
    header('Location: delivery-teams.php');
    exit;
}

$driver_name = $team['driver_name'];
$vehicle_type = $team['vehicle_type'];
$vehicle_capacity = $team['vehicle_capacity'];
$availability_status = $team['availability_status'];
$shift_timing = $team['shift_timing'];
$route_allocation = $team['route_allocation'];
$user_id = $team['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_name = trim($_POST['driver_name'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? 'Truck';
    $vehicle_capacity = trim($_POST['vehicle_capacity'] ?? '');
    $availability_status = $_POST['availability_status'] ?? 'Available';
    $shift_timing = trim($_POST['shift_timing'] ?? '');
    $route_allocation = trim($_POST['route_allocation'] ?? '');
    $user_id = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : null;

    if (empty($driver_name)) {
        $error = 'Driver name is required.';
    } elseif (!in_array($vehicle_type, $vehicle_types)) {
        $error = 'Please select a valid vehicle type.';
    } elseif (!is_numeric($vehicle_capacity) || (int)$vehicle_capacity <= 0) {
        $error = 'Vehicle capacity must be a positive number.';
    } elseif (!in_array($availability_status, $statuses)) {
        $error = 'Please select a valid availability status.';
    } elseif (empty($shift_timing)) {
        $error = 'Shift timing is required.';
    } elseif ($user_id !== null && !array_search($user_id, array_column($delivery_users, 'id'), true)) {
        $error = 'Selected delivery user is invalid.';
    } else {
        $update = mysqli_prepare($conn, 'UPDATE delivery_teams SET driver_name = ?, vehicle_type = ?, vehicle_capacity = ?, availability_status = ?, shift_timing = ?, route_allocation = ?, user_id = ? WHERE id = ?');
        mysqli_stmt_bind_param($update, 'ssisssii', $driver_name, $vehicle_type, $vehicle_capacity, $availability_status, $shift_timing, $route_allocation, $user_id, $team_id);
        if (mysqli_stmt_execute($update)) {
            $success = 'Delivery team updated successfully.';
        } else {
            $error = 'Unable to update delivery team. Please try again.';
        }
        mysqli_stmt_close($update);
    }
}

$pageTitle = 'Edit Delivery Team - IDMS';
include 'includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <main class="col-md-9 col-lg-7 px-md-4 py-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title mb-4">Edit Delivery Team</h4>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="post" action="edit-delivery-team.php?id=<?= $team_id ?>" novalidate>
                        <div class="mb-3">
                            <label class="form-label" for="driver_name">Driver Name</label>
                            <input type="text" class="form-control" id="driver_name" name="driver_name" value="<?= htmlspecialchars($driver_name) ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="vehicle_type">Vehicle Type</label>
                                <select id="vehicle_type" name="vehicle_type" class="form-select">
                                    <?php foreach ($vehicle_types as $type): ?>
                                        <option value="<?= $type ?>" <?= $vehicle_type === $type ? 'selected' : '' ?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="vehicle_capacity">Vehicle Capacity (Kg)</label>
                                <input type="number" class="form-control" id="vehicle_capacity" name="vehicle_capacity" value="<?= htmlspecialchars($vehicle_capacity) ?>" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="availability_status">Availability Status</label>
                            <select id="availability_status" name="availability_status" class="form-select">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $availability_status === $status ? 'selected' : '' ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="shift_timing">Shift Timing</label>
                            <input type="text" class="form-control" id="shift_timing" name="shift_timing" value="<?= htmlspecialchars($shift_timing) ?>" placeholder="e.g., 08:00 - 16:00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="route_allocation">Route Allocation</label>
                            <input type="text" class="form-control" id="route_allocation" name="route_allocation" value="<?= htmlspecialchars($route_allocation) ?>" placeholder="e.g., East Zone Route" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user_id">Delivery User</label>
                            <select id="user_id" name="user_id" class="form-select">
                                <option value="">None / Unmapped</option>
                                <?php foreach ($delivery_users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $user_id === (int)$user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Delivery Team</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';