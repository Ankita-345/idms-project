<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin','Manager','Delivery'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

$order = null;

if (($_SESSION['role'] ?? '') === 'Delivery') {
    $user_id = $_SESSION['user_id'];
    $vstmt = mysqli_prepare($conn, 'SELECT o.id, o.status, o.ice_type, o.quantity, o.inventory_deducted, o.delivery_proof_image, o.delivery_otp, o.delivered_at FROM orders o JOIN delivery_teams dt ON o.assigned_team_id = dt.id WHERE o.id = ? AND dt.user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($vstmt, 'ii', $order_id, $user_id);
    mysqli_stmt_execute($vstmt);
    $vres = mysqli_stmt_get_result($vstmt);
    if ($vres && mysqli_num_rows($vres) === 1) {
        $order = mysqli_fetch_assoc($vres);
    }
    mysqli_stmt_close($vstmt);
} else {
    $ost = mysqli_prepare($conn, 'SELECT id, status, ice_type, quantity, inventory_deducted, delivery_proof_image, delivery_otp, delivered_at FROM orders WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($ost, 'i', $order_id);
    mysqli_stmt_execute($ost);
    $ores = mysqli_stmt_get_result($ost);
    if ($ores && mysqli_num_rows($ores) === 1) {
        $order = mysqli_fetch_assoc($ores);
    }
    mysqli_stmt_close($ost);
}

if (!$order) {
    header('Location: orders.php');
    exit;
}

$error = '';
$msg = '';
$debug_info = [];
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

$statuses = ['Pending','Confirmed','Assigned','Out for Delivery','Delivered','Completed','Cancelled','Failed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = trim($_POST['status'] ?? '');
    $old_status = $order['status'];
    $delivery_proof_file = $_FILES['delivery_proof'] ?? null;
    $error = '';

    if (!in_array($new_status, $statuses)) {
        $error = 'Invalid status selected.';
    }

    // --- Delivery Proof Upload Logic ---
    $delivery_proof_path = null;
    $is_delivery_role = ($_SESSION['role'] ?? '') === 'Delivery';

    if ($new_status === 'Delivered' && $is_delivery_role) {
        if (isset($delivery_proof_file) && $delivery_proof_file['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($delivery_proof_file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            if (!in_array($file_ext, $allowed_ext)) {
                $error = 'Invalid file type for delivery proof. Only JPG, JPEG, and PNG are allowed.';
            } elseif ($delivery_proof_file['size'] > 5 * 1024 * 1024) { // 5MB
                $error = 'Delivery proof file is too large. Maximum size is 5MB.';
            } else {
                $upload_dir = 'uploads/delivery-proofs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $new_file_name = 'delivery_' . $order_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($delivery_proof_file['tmp_name'], $destination)) {
                    $delivery_proof_path = $destination;
                } else {
                    $error = 'Failed to move uploaded delivery proof. Check folder permissions.';
                }
            }
        } else {
            $error = 'A delivery proof is mandatory for Delivery personnel to mark an order as Delivered.';
        }
    }

    if (empty($error)) {
        // --- Database Update ---
        mysqli_begin_transaction($conn);

        // Existing inventory logic can be here...
        // For this task, we focus on the status and proof update.

        $sql = '';
        $params = [];
        $types = '';

        if ($new_status === 'Delivered') {
            if ($delivery_proof_path) { // Proof was uploaded by Delivery person
                $sql = "UPDATE orders SET status = ?, delivery_proof = ?, delivery_proof_uploaded_at = NOW(), delivered_at = NOW() WHERE id = ?";
                $params = [$new_status, $delivery_proof_path, $order_id];
                $types = 'ssi';
            } else { // Admin is marking as delivered without proof
                $sql = "UPDATE orders SET status = ?, delivered_at = NOW() WHERE id = ?";
                $params = [$new_status, $order_id];
                $types = 'si';
            }
        } else {
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $params = [$new_status, $order_id];
            $types = 'si';
        }

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_commit($conn);
                header('Location: orders.php?success=Order status updated successfully.');
                exit;
            } else {
                mysqli_rollback($conn);
                $error = 'Failed to update order status in the database.';
            }
            mysqli_stmt_close($stmt);
        } else {
            mysqli_rollback($conn);
            $error = 'Database statement preparation failed.';
        }
    }
}

$pageTitle = 'Update Order Status - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="orders.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Update Status for Order #<?= $order_id ?></h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($msg): ?>
                        <div class="alert alert-success"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="row g-3" id="updateStatusForm">
                        <div class="col-12">
                            <label class="form-label">Current status</label>
                            <div class="mb-3"><strong><?= htmlspecialchars($order['status']) ?></strong></div>
                        </div>

                        <div class="col-12">
                            <label for="status" class="form-label">New status</label>
                            <select name="status" id="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= ($order['status'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12" id="deliveryProofContainer" style="display: none;">
                            <label for="delivery_proof" class="form-label">Delivery Proof</label>
                            <input type="file" name="delivery_proof" id="delivery_proof" class="form-control" accept="image/png, image/jpeg">
                            <div class="form-text">Mandatory for "Delivered" status. Max 5MB. JPG, PNG only.</div>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const proofContainer = document.getElementById('deliveryProofContainer');

    const isDeliveryRole = <?= json_encode(($_SESSION['role'] ?? '') === 'Delivery') ?>;

    function toggleProofContainer() {
        if (isDeliveryRole && statusSelect.value === 'Delivered') {
            proofContainer.style.display = 'block';
        } else {
            proofContainer.style.display = 'none';
        }
    }

    statusSelect.addEventListener('change', toggleProofContainer);
    toggleProofContainer(); // Initial check
});
</script>

<?php include 'includes/footer.php';