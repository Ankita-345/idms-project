<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$driver_name = '';
$vehicle_type = 'Truck';
$vehicle_capacity = '';
$availability_status = 'Available';
$shift_timing = '';
$route_allocation = '';
$email = '';
$phone = '';
$error = '';
$success = '';

$vehicle_types = ['Truck', 'Van', 'Mini'];
$statuses = ['Available', 'Busy', 'Offline'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_name = trim($_POST['driver_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $vehicle_type = $_POST['vehicle_type'] ?? 'Truck';
    $vehicle_capacity = trim($_POST['vehicle_capacity'] ?? '');
    $availability_status = $_POST['availability_status'] ?? 'Available';
    $shift_timing = trim($_POST['shift_timing'] ?? '');
    $route_allocation = trim($_POST['route_allocation'] ?? '');

    if (empty($driver_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Driver Name, Email, Phone, and Password are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            // Check for existing email
            $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ?');
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                throw new Exception('Email already exists.');
            }
            mysqli_stmt_close($stmt);

            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Delivery';
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssss', $driver_name, $email, $phone, $hashed_password, $role);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to create user.');
            }
            $new_user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Create delivery team record
            $stmt = mysqli_prepare($conn, 'INSERT INTO delivery_teams (user_id, driver_name, phone, vehicle_type, vehicle_capacity, availability_status, shift_timing, route_allocation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'isssisss', $new_user_id, $driver_name, $phone, $vehicle_type, $vehicle_capacity, $availability_status, $shift_timing, $route_allocation);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to create delivery team.');
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $success = 'Delivery team and user created successfully.';
            // Clear form fields
            $driver_name = $email = $phone = $vehicle_capacity = $shift_timing = $route_allocation = '';

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Add Delivery Team - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="row justify-content-center">
               <div class="col-lg-8 col-md-10">
                   <div class="card">
                       <div class="card-body">
                           <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>

                            <form method="post" action="add-delivery-team.php" novalidate>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="driver_name">Driver Name</label>
                                        <input type="text" class="form-control" id="driver_name" name="driver_name" value="<?= htmlspecialchars($driver_name) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="phone">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="password">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="confirm_password">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <hr class="my-4">
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
                                        <input type="number" class="form-control" id="vehicle_capacity" name="vehicle_capacity" value="<?= htmlspecialchars($vehicle_capacity) ?>" min="1">
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
                                    <input type="text" class="form-control" id="shift_timing" name="shift_timing" value="<?= htmlspecialchars($shift_timing) ?>" placeholder="e.g., 08:00 - 16:00">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="route_allocation">Route Allocation</label>
                                    <input type="text" class="form-control" id="route_allocation" name="route_allocation" value="<?= htmlspecialchars($route_allocation) ?>" placeholder="e.g., East Zone Route">
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <a href="delivery-teams.php" class="btn btn-outline-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Save Delivery Team</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';