<?php
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$full_name = '';
$email = '';
$phone = '';
$role = 'Client';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'Client';

    if (empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (!preg_match('/^[0-9\s\-\+]{10,15}$/', $phone)) {
        $error = 'Enter a valid phone number (10-15 digits, numbers, and symbols like +, - allowed).';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = 'This email is already registered.';
        } else {
            mysqli_stmt_close($stmt);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssss', $full_name, $email, $phone, $hash, $role);

            if (mysqli_stmt_execute($stmt)) {
                $new_user_id = mysqli_insert_id($conn);
                if ($role === 'Client') {
                    $cstmt = mysqli_prepare($conn, 'INSERT INTO clients (user_id, company_name, email, phone, business_type, category) VALUES (?, ?, ?, ?, ?, ?)');
                    $default_bus = 'Cafe';
                    $default_cat = 'Regular';
                    mysqli_stmt_bind_param($cstmt, 'isssss', $new_user_id, $full_name, $email, $phone, $default_bus, $default_cat);
                    @mysqli_stmt_execute($cstmt);
                    mysqli_stmt_close($cstmt);
                } elseif ($role === 'Delivery') {
                    $vehicle_type_default = 'Van';
                    $default_capacity = 1000;
                    $default_shift = '08:00 - 16:00';
                    $default_route = 'Unassigned';
                    $availability_status = 'Available';
                    $dstmt = mysqli_prepare($conn, 'INSERT INTO delivery_teams (driver_name, phone, vehicle_type, vehicle_capacity, availability_status, shift_timing, route_allocation, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($dstmt, 'sssisssi', $full_name, $phone, $vehicle_type_default, $default_capacity, $availability_status, $default_shift, $default_route, $new_user_id);
                    @mysqli_stmt_execute($dstmt);
                    mysqli_stmt_close($dstmt);
                }

                $success = 'Registration successful. You may now <a href="login.php">login</a>.';
                $full_name = '';
                $email = '';
                $phone = '';
                $role = 'Client';
            } else {
                $error = 'Unable to register at this time. Please try again later.';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$pageTitle = 'Register - IDMS';
$bodyClass = 'page-auth';
include 'includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-75">
        <div class="col-md-8 col-lg-6">
            <div class="card auth-card shadow-lg">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h4 class="card-title mb-2">Create Your Account</h4>
                        <p class="text-muted auth-note">Start managing deliveries with modern tools and clear insights.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <form method="post" action="register.php" novalidate>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="role" class="form-label">Select Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <?php $roles = ['Admin', 'Manager', 'Delivery', 'Client']; ?>
                                <?php foreach ($roles as $value): ?>
                                    <option value="<?= $value ?>" <?= $role === $value ? 'selected' : '' ?>><?= $value ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Register</button>
                    </form>
                    <div class="mt-4 text-center">
                        <small class="text-muted">Already registered? <a href="login.php">Login here</a>.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php';