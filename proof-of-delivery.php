<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Delivery') {
    header('Location: orders.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, 'SELECT o.id, o.status, o.delivery_otp, o.delivery_proof_image, o.assigned_team_id FROM orders o JOIN delivery_teams dt ON o.assigned_team_id = dt.id WHERE o.id = ? AND dt.user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    header('Location: orders.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($order['status'] !== 'Out for Delivery') {
        $error = 'Proof of delivery can only be submitted when the order is Out for Delivery.';
    } elseif ($action === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        if ($otp === '') {
            $error = 'Enter the OTP provided by the client.';
        } elseif (empty($order['delivery_otp'])) {
            $error = 'No delivery OTP has been generated for this order.';
        } elseif ($otp !== $order['delivery_otp']) {
            $error = 'Invalid OTP provided.';
        } else {
            $ust = mysqli_prepare($conn, 'UPDATE orders SET status = "Delivered", delivered_at = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($ust, 'i', $order_id);
            if (mysqli_stmt_execute($ust)) {
                mysqli_stmt_close($ust);
                header('Location: view-order.php?id=' . $order_id . '&success=' . urlencode('OTP verified. Order marked as Delivered.'));
                exit;
            }
            mysqli_stmt_close($ust);
            $error = 'Failed to update order status after OTP verification.';
        }
    } elseif ($action === 'upload_photo') {
        if (!isset($_FILES['delivery_proof_image']) || !is_uploaded_file($_FILES['delivery_proof_image']['tmp_name'])) {
            $error = 'Select a proof photo to upload.';
        } else {
            $file = $_FILES['delivery_proof_image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Photo upload failed. Please try again.';
            } else {
                $maxFileSize = 2 * 1024 * 1024; // 2 MB
                if ($file['size'] > $maxFileSize) {
                    $error = 'Photo file is too large. Maximum size is 2MB.';
                } elseif (!is_valid_image_file($file['tmp_name'])) {
                    $error = 'Invalid file type. Use JPG or PNG only.';
                } else {
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true)) {
                    $error = 'Invalid file type. Use JPG or PNG only.';
                } else {
                    $upload_dir = __DIR__ . '/uploads/delivery_proofs';
                    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                        $error = 'Failed to create upload directory.';
                    } else {
                            $filename = sanitize_filename('proof_' . $order_id . '_' . time() . '.' . $ext);
                        $target_path = $upload_dir . '/' . $filename;
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            $stored_path = 'uploads/delivery_proofs/' . $filename;
                            $ust = mysqli_prepare($conn, 'UPDATE orders SET delivery_proof_image = ?, status = "Delivered", delivered_at = NOW() WHERE id = ?');
                            mysqli_stmt_bind_param($ust, 'si', $stored_path, $order_id);
                            if (mysqli_stmt_execute($ust)) {
                                mysqli_stmt_close($ust);
                                header('Location: view-order.php?id=' . $order_id . '&success=' . urlencode('Proof photo uploaded. Order marked as Delivered.'));
                                exit;
                            }
                            mysqli_stmt_close($ust);
                            $error = 'Failed to update order after uploading proof photo.';
                        } else {
                            $error = 'Failed to move uploaded file.';
                        }
                    }
                }
            }
        }
    } else {
        $error = 'Invalid proof submission action.';
    }
}

$pageTitle = 'Proof of Delivery - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2">Proof of Delivery for Order #<?= htmlspecialchars($order_id) ?></h1>
                    <p class="text-muted mb-0">Upload a photo or verify OTP to complete the delivery.</p>
                </div>
                <a href="view-order.php?id=<?= $order_id ?>" class="btn btn-outline-secondary">Back to Order</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Verify OTP</h5>
                            <p>Enter the OTP provided by the client to confirm delivery.</p>
                            <form method="post">
                                <input type="hidden" name="action" value="verify_otp">
                                <div class="mb-3">
                                    <label for="otp" class="form-label">Client OTP</label>
                                    <input type="text" id="otp" name="otp" class="form-control" required>
                                </div>
                                <button class="btn btn-success">Verify OTP</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Upload Proof Photo</h5>
                            <p>Upload a photo of the delivered ice to complete delivery.</p>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_photo">
                                <div class="mb-3">
                                    <label for="delivery_proof_image" class="form-label">Proof photo</label>
                                    <input type="file" id="delivery_proof_image" name="delivery_proof_image" class="form-control" accept="image/png,image/jpeg" required>
                                </div>
                                <button class="btn btn-secondary">Upload Photo</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';
