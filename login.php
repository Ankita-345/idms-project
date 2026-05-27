<?php
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) === 1) {
            mysqli_stmt_bind_result($stmt, $id, $full_name, $user_email, $hash, $role);
            mysqli_stmt_fetch($stmt);

            if (password_verify($password, $hash)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $user_email;
                $_SESSION['role'] = $role;
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                header('Location: dashboard.php');
                exit;
            }
        }

        $error = 'Login failed. Check your email and password.';
        mysqli_stmt_close($stmt);
    }
}

$pageTitle = 'Login - IDMS';
$bodyClass = 'page-auth';
include 'includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-75">
        <div class="col-md-7 col-lg-5">
            <div class="card auth-card shadow-lg">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h4 class="card-title mb-2">Welcome Back</h4>
                        <p class="text-muted auth-note">Sign in to access your Ice Distribution dashboard.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="login.php" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
                    </form>
                    <div class="mt-4 text-center">
                        <small class="text-muted">Don't have an account? <a href="register.php">Register here</a>.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php';
