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
    $old_inventory_deducted = (int)$order['inventory_deducted'];
    $inventory_action = '';
    $new_inventory_deducted = $old_inventory_deducted;
    $otp_to_save = null;

    if (!in_array($new_status, $statuses)) {
        $error = 'Invalid status.';
    } else {
        $debug_info[] = 'Old status: ' . $old_status;
        $debug_info[] = 'New status: ' . $new_status;
        $debug_info[] = 'Ice type: ' . $order['ice_type'];
        $debug_info[] = 'Quantity: ' . $order['quantity'];
        $debug_info[] = 'Inventory deducted flag: ' . $old_inventory_deducted;

        if (in_array($new_status, ['Cancelled','Failed']) && !in_array($old_status, ['Cancelled','Failed']) && $old_inventory_deducted === 1) {
            $inventory_action = 'restore';
            $new_inventory_deducted = 0;
            $debug_info[] = 'Inventory action: restore';
        }

        if (in_array($new_status, ['Confirmed','Assigned']) && !in_array($old_status, ['Confirmed','Assigned']) && $old_inventory_deducted === 0) {
            $inventory_action = 'deduct';
            $new_inventory_deducted = 1;
            $debug_info[] = 'Inventory action: deduct';
        }

        if ($new_status === 'Out for Delivery' && $old_status !== 'Out for Delivery') {
            if (empty($order['delivery_otp'])) {
                $otp_to_save = (string)random_int(100000, 999999);
                $debug_info[] = 'Generated delivery OTP: ' . $otp_to_save;
            }
        }

        if ($new_status === 'Delivered' && $order['delivered_at'] === null && empty($order['delivery_proof_image'])) {
            $error = 'Delivery proof or OTP verification is required before marking delivered.';
        }

        if ($inventory_action !== '' && empty($error)) {
            mysqli_begin_transaction($conn);
            $inv_stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE ice_type = ? FOR UPDATE');
            mysqli_stmt_bind_param($inv_stmt, 's', $order['ice_type']);
            mysqli_stmt_execute($inv_stmt);
            mysqli_stmt_bind_result($inv_stmt, $inv_qty);
            $has_row = mysqli_stmt_fetch($inv_stmt);
            mysqli_stmt_close($inv_stmt);

            if (!$has_row) {
                mysqli_rollback($conn);
                $error = 'Inventory record not found.';
                $debug_info[] = 'Inventory row missing for type: ' . $order['ice_type'];
            } else {
                $debug_info[] = 'Current inventory qty: ' . $inv_qty;
                if ($inventory_action === 'restore') {
                    $inc = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity + ?, updated_at = NOW() WHERE ice_type = ?');
                    mysqli_stmt_bind_param($inc, 'is', $order['quantity'], $order['ice_type']);
                    $ok = mysqli_stmt_execute($inc);
                    mysqli_stmt_close($inc);
                    if (!$ok) {
                        mysqli_rollback($conn);
                        $error = 'Failed to restore inventory.';
                        $debug_info[] = 'Inventory restore query failed.';
                    } else {
                        $debug_info[] = 'Inventory restored successfully.';
                    }
                } else {
                    if ((int)$inv_qty < (int)$order['quantity']) {
                        mysqli_rollback($conn);
                        $error = 'Insufficient stock to confirm/assign this order.';
                        $debug_info[] = 'Not enough stock to deduct.';
                    } else {
                        $dec = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity - ?, updated_at = NOW() WHERE ice_type = ?');
                        mysqli_stmt_bind_param($dec, 'is', $order['quantity'], $order['ice_type']);
                        $ok = mysqli_stmt_execute($dec);
                        mysqli_stmt_close($dec);
                        if (!$ok) {
                            mysqli_rollback($conn);
                            $error = 'Failed to deduct inventory.';
                            $debug_info[] = 'Inventory deduct query failed.';
                        } else {
                            $debug_info[] = 'Inventory deducted successfully.';
                        }
                    }
                }
            }
        }

        if (empty($error)) {
            if ($inventory_action !== '' || $otp_to_save !== null) {
                $ust = mysqli_prepare($conn, 'UPDATE orders SET status = ?, inventory_deducted = ?, delivery_otp = COALESCE(?, delivery_otp) WHERE id = ?');
                mysqli_stmt_bind_param($ust, 'siis', $new_status, $new_inventory_deducted, $otp_to_save, $order_id);
            } else {
                $ust = mysqli_prepare($conn, 'UPDATE orders SET status = ? WHERE id = ?');
                mysqli_stmt_bind_param($ust, 'si', $new_status, $order_id);
            }

            if (mysqli_stmt_execute($ust)) {
                mysqli_stmt_close($ust);
                if ($inventory_action !== '') {
                    mysqli_commit($conn);
                }
                if ($debug_mode) {
                    $msg = 'Order status updated successfully. Debug info:<br>' . implode('<br>', array_map('htmlspecialchars', $debug_info));
                } else {
                    header('Location: orders.php?success=' . urlencode('Order status updated successfully.'));
                    exit;
                }
            } else {
                mysqli_stmt_close($ust);
                if ($inventory_action !== '') {
                    mysqli_rollback($conn);
                }
                $error = 'Failed to update status.';
                $debug_info[] = 'Order update query failed.';
            }
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

                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Current status</label>
                            <div class="mb-3"><strong><?= htmlspecialchars($order['status']) ?></strong></div>
                        </div>

                        <div class="col-12">
                            <label for="status" class="form-label">New status</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="">-- Select status --</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php';
