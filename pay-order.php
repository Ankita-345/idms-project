<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Verify that the order belongs to the logged-in client and get all necessary details
$stmt = mysqli_prepare($conn, "SELECT o.* FROM orders o JOIN clients c ON o.client_id = c.id WHERE o.id = ? AND c.user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    // If order doesn't exist or doesn't belong to the user, redirect
    header('Location: orders.php');
    exit;
}

// Handle payment proof submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    // Only allow upload if status is pending and no proof is submitted yet
    if ($order['payment_status'] === 'pending' && empty($order['payment_proof'])) {
        $file = $_FILES['payment_proof'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];

        if (in_array($file_ext, $allowed_ext)) {
            if ($file_error === 0) {
                if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                    $upload_dir = 'uploads/payment-proofs/';
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    // Create a safe, unique filename
                    $new_file_name = 'order_' . $order_id . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        // Update the database with the file path and submission time
                        $update_stmt = mysqli_prepare($conn, "UPDATE orders SET payment_proof = ?, payment_submitted_at = NOW() WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "si", $destination, $order_id);
                        if (mysqli_stmt_execute($update_stmt)) {
                            $success = "Payment proof submitted successfully. Admin will verify the payment shortly.";
                            // Refresh order data to show the success state immediately
                            $order['payment_proof'] = $destination;
                        } else {
                            $error = "Database error. Failed to save payment proof information.";
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        $error = "Failed to move uploaded file. Check folder permissions.";
                    }
                } else {
                    $error = "File is too large. Maximum size is 5MB.";
                }
            } else {
                $error = "An error occurred during file upload. Please try again.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
        }
    } else {
        $error = "Payment proof has already been submitted or the order is not pending payment.";
    }
}

$pageTitle = 'Pay for Order - IDMS';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="page-header mb-4">
                <h1 class="page-title">Pay for Order #<?= htmlspecialchars($order['id']) ?></h1>
            </div>

            <div class="row justify-content-center">
                <div class="col-12 col-md-10 col-lg-8">
                    <div class="card">
                        <div class="card-body text-center p-4">

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>

                            <h4 class="mb-3">Order Amount: <span class="text-primary fw-bold">₹<?= htmlspecialchars(number_format($order['amount'], 2)) ?></span></h4>

                            <?php if ($order['payment_status'] === 'pending' && empty($order['payment_proof'])): ?>
                                <!-- State 1: Pending payment, no proof submitted -->
                                <p class="text-muted">Scan the QR code to pay, then upload a screenshot of your payment confirmation.</p>
                                <img src="assets/images/payment-qr.jpeg" alt="Payment QR Code" class="img-fluid rounded mb-4" style="max-width: 250px;">

                                <form method="post" enctype="multipart/form-data" class="mt-3">
                                    <div class="mb-3">
                                        <label for="payment_proof" class="form-label fw-bold">Upload Payment Screenshot</label>
                                        <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept="image/png, image/jpeg" required>
                                        <div class="form-text">Allowed formats: JPG, JPEG, PNG. Max size: 5MB.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Submit Payment Proof</button>
                                </form>

                            <?php elseif (!empty($order['payment_proof'])): ?>
                                <!-- State 2: Proof has been submitted -->
                                <div class="alert alert-info mt-4">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    <strong>Payment proof submitted.</strong> We are currently verifying your payment.
                                </div>
                                <a href="orders.php" class="btn btn-secondary mt-3">Back to My Orders</a>

                            <?php else: ?>
                                <!-- State 3: Order is not pending (e.g., already paid, cancelled) -->
                                 <div class="alert alert-secondary mt-4">
                                    This order's payment status is "<?= htmlspecialchars(ucfirst($order['payment_status'])) ?>". No action is needed.
                                </div>
                                <a href="orders.php" class="btn btn-secondary mt-3">Back to My Orders</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>