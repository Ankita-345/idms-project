<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin','Manager'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

$msg = '';
$error = '';

// Load order
$ost = mysqli_prepare($conn, 'SELECT id, ice_type, quantity, COALESCE(delivery_city, (SELECT city FROM client_addresses WHERE id = client_address_id)) AS city, assigned_team_id, inventory_deducted FROM orders WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($ost, 'i', $order_id);
mysqli_stmt_execute($ost);
$ores = mysqli_stmt_get_result($ost);
if (!$ores || mysqli_num_rows($ores) === 0) {
    mysqli_stmt_close($ost);
    header('Location: orders.php');
    exit;
}
$order = mysqli_fetch_assoc($ores);
mysqli_stmt_close($ost);

// Fetch candidate teams for selection
$teams_res = mysqli_query($conn, 'SELECT id, driver_name, vehicle_type, vehicle_capacity, availability_status, shift_timing, route_allocation FROM delivery_teams ORDER BY driver_name');
$teams = mysqli_fetch_all($teams_res, MYSQLI_ASSOC);

$current_assigned_name = '-';
foreach ($teams as $t) {
    if ((int)$t['id'] === (int)($order['assigned_team_id'] ?? 0)) {
        $current_assigned_name = $t['driver_name'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_team = isset($_POST['team_id']) && is_numeric($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    $override = isset($_POST['override']) ? 1 : 0;

    if ($selected_team <= 0) {
        $error = 'Select a delivery team.';
    } else {
        // If not override, enforce basic rules
        if (!$override) {
            $check = mysqli_prepare($conn, 'SELECT vehicle_capacity, availability_status, shift_timing, route_allocation FROM delivery_teams WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($check, 'i', $selected_team);
            mysqli_stmt_execute($check);
            mysqli_stmt_bind_result($check, $vcap, $avail, $shift, $route);
            mysqli_stmt_fetch($check);
            mysqli_stmt_close($check);

            if ($avail !== 'Available') {
                $error = 'Selected team is not currently available. Use override to force assign.';
            } elseif ($vcap < (int)$order['quantity']) {
                $error = 'Selected team does not have sufficient vehicle capacity.';
            }
        }

        if (!$error) {
            $needs_inventory = $order['inventory_deducted'] === 0;
            if ($needs_inventory) {
                mysqli_begin_transaction($conn);
                $inv_stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE ice_type = ? FOR UPDATE');
                mysqli_stmt_bind_param($inv_stmt, 's', $order['ice_type']);
                mysqli_stmt_execute($inv_stmt);
                mysqli_stmt_bind_result($inv_stmt, $inv_qty);
                $has_row = mysqli_stmt_fetch($inv_stmt);
                mysqli_stmt_close($inv_stmt);

                if (!$has_row || (int)$inv_qty < (int)$order['quantity']) {
                    mysqli_rollback($conn);
                    $error = 'Insufficient stock to assign this order.';
                } else {
                    $dec = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity - ?, updated_at = NOW() WHERE ice_type = ?');
                    mysqli_stmt_bind_param($dec, 'is', $order['quantity'], $order['ice_type']);
                    $dec_ok = mysqli_stmt_execute($dec);
                    mysqli_stmt_close($dec);

                    if ($dec_ok) {
                        $mark_stmt = mysqli_prepare($conn, 'UPDATE orders SET inventory_deducted = 1 WHERE id = ?');
                        mysqli_stmt_bind_param($mark_stmt, 'i', $order_id);
                        $mark_ok = mysqli_stmt_execute($mark_stmt);
                        mysqli_stmt_close($mark_stmt);
                        if (!$mark_ok) {
                            mysqli_rollback($conn);
                            $error = 'Failed to update order inventory status.';
                        }
                    } else {
                        mysqli_rollback($conn);
                        $error = 'Failed to update inventory.';
                    }
                }
            }

            if (empty($error)) {
                $u = mysqli_prepare($conn, 'UPDATE orders SET assigned_team_id = ?, assigned_at = NOW(), status = "Assigned" WHERE id = ?');
                mysqli_stmt_bind_param($u, 'ii', $selected_team, $order_id);
                if (mysqli_stmt_execute($u)) {
                    if ($needs_inventory) {
                        mysqli_commit($conn);
                    }
                    $msg = 'Order assigned successfully.';
                    // Notifications: notify delivery user and client
                    require_once __DIR__ . '/includes/notify.php';
                    // find delivery user for the team
                    $tstmt = mysqli_prepare($conn, 'SELECT user_id FROM delivery_teams WHERE id = ? LIMIT 1');
                    mysqli_stmt_bind_param($tstmt, 'i', $selected_team);
                    mysqli_stmt_execute($tstmt);
                    $tres = mysqli_stmt_get_result($tstmt);
                    $trow = mysqli_fetch_assoc($tres);
                    mysqli_stmt_close($tstmt);
                    if (!empty($trow['user_id'])) {
                        $du = (int)$trow['user_id'];
                        notify_user($conn, $du, 'New Assigned Delivery', 'You have been assigned Order #' . (int)$order_id, 'view-order.php?id=' . (int)$order_id);
                    }
                    // notify client user if linked
                    $cstmt = mysqli_prepare($conn, 'SELECT c.user_id FROM clients c JOIN orders o ON o.client_id = c.id WHERE o.id = ? LIMIT 1');
                    mysqli_stmt_bind_param($cstmt, 'i', $order_id);
                    mysqli_stmt_execute($cstmt);
                    $cres = mysqli_stmt_get_result($cstmt);
                    $crow = mysqli_fetch_assoc($cres);
                    mysqli_stmt_close($cstmt);
                    if (!empty($crow['user_id'])) {
                        notify_user($conn, (int)$crow['user_id'], 'Order Assigned', 'Your order #' . (int)$order_id . ' has been assigned.', 'view-order.php?id=' . (int)$order_id);
                    }
                } else {
                    if ($needs_inventory) {
                        mysqli_rollback($conn);
                    }
                    $error = 'Failed to assign order.';
                }
                mysqli_stmt_close($u);
            }
        }
    }
}

$pageTitle = 'Assign Order - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="orders.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Assign / Override Order #<?= $order_id ?></h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($msg): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>

                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Current assigned team</label>
                            <div class="mb-3">
                                <strong><?= htmlspecialchars($current_assigned_name) ?></strong>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="team_id" class="form-label">Choose delivery team</label>
                            <select id="team_id" name="team_id" class="form-select" required>
                                <option value="">-- Select team --</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['driver_name'] . ' — ' . $t['vehicle_type'] . ' — cap:' . $t['vehicle_capacity'] . ' — ' . $t['availability_status']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="override" name="override" value="1">
                                <label class="form-check-label" for="override">Override rules (force assign regardless of availability/capacity)</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary">Assign Team</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php';
