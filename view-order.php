<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

// Role-restricted order lookup
$user_role = $_SESSION['role'] ?? '';
$user_id_session = $_SESSION['user_id'] ?? 0;

$sql = 'SELECT o.*, c.company_name, c.email AS client_email, c.phone AS client_phone, c.business_type, c.category, ca.street_address AS addr_street, ca.city AS addr_city, ca.state AS addr_state, ca.postal_code AS addr_postal_code, o.delivery_state, dt.driver_name AS assigned_driver, dt.vehicle_type AS assigned_vehicle, dt.route_allocation AS assigned_route, dt.shift_timing AS assigned_shift, dt.user_id AS assigned_user_id, o.delivery_proof_image, o.delivery_otp, o.delivered_at FROM orders o LEFT JOIN clients c ON o.client_id = c.id LEFT JOIN client_addresses ca ON o.client_address_id = ca.id LEFT JOIN delivery_teams dt ON o.assigned_team_id = dt.id WHERE o.id = ?';
$types = 'i';
$params = [$order_id];

if ($user_role === 'Client' && $user_id_session) {
    $sql .= ' AND o.client_id = (SELECT id FROM clients WHERE user_id = ? LIMIT 1)';
    $types .= 'i';
    $params[] = $user_id_session;
}

if ($user_role === 'Delivery' && $user_id_session) {
    $sql .= ' AND o.assigned_team_id IN (SELECT id FROM delivery_teams WHERE user_id = ?)';
    $types .= 'i';
    $params[] = $user_id_session;
}

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    header('Location: orders.php');
    exit;
}
if (!empty($params)) {
    $bindParams = array_merge([$types], $params);
    $refs = [];
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$order) {
    header('Location: orders.php');
    exit;
}

$status_colors = [
    'Pending' => 'secondary',
    'Confirmed' => 'primary',
    'Assigned' => 'info',
    'Picked' => 'info',
    'In Transit' => 'warning',
    'Out for Delivery' => 'warning',
    'Delivered' => 'success',
    'Completed' => 'dark',
    'Cancelled' => 'danger',
    'Failed' => 'danger'
];
$status_badge = $status_colors[$order['status']] ?? 'secondary';

$unit_map = [
    'Cube' => 'KG',
    'Crushed' => 'KG',
    'Dry Ice' => 'KG',
    'Block' => 'Pieces',
    'Tube Ice' => 'KG',
];

$pageTitle = 'Order Details - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3 d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2">Order #<?= htmlspecialchars($order['id']) ?></h1>
                    <p class="text-muted mb-0">Track order status and review details.</p>
                </div>
                <a href="orders.php" class="btn btn-outline-secondary">Back to Orders</a>
            </div>
            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Order Details</h5>
                            <p><strong>Ice Type:</strong> <?= htmlspecialchars($order['ice_type']) ?></p>
                            <p><strong>Quantity:</strong> <?= htmlspecialchars($order['quantity'] . ' ' . ($unit_map[$order['ice_type']] ?? 'KG')) ?></p>
                            <p><strong>Delivery Date:</strong> <?= htmlspecialchars($order['delivery_date']) ?></p>
                            <p><strong>Time Slot:</strong> <?= htmlspecialchars($order['delivery_time_slot']) ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?= $status_badge ?>"><?= htmlspecialchars($order['status']) ?></span></p>
                            <p><strong>Bulk Order:</strong> <?= $order['bulk_order'] ? 'Yes' : 'No' ?></p>
                            <p><strong>Recurring Order:</strong> <?= $order['recurring'] ? 'Yes' : 'No' ?></p>
                            <p><strong>Special Instructions:</strong><br><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Delivery Address</h5>
                            <p><strong>Street:</strong> <?= htmlspecialchars($order['delivery_street'] ?? $order['addr_street'] ?? 'N/A') ?></p>
                            <p><strong>City:</strong> <?= htmlspecialchars($order['delivery_city'] ?? $order['addr_city'] ?? 'N/A') ?></p>
                            <p><strong>State:</strong> <?= htmlspecialchars($order['delivery_state'] ?? $order['addr_state'] ?? 'N/A') ?></p>
                            <p><strong>Postal Code:</strong> <?= htmlspecialchars($order['delivery_postal_code'] ?? $order['addr_postal_code'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-3">
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Client Info</h5>
                            <p><strong>Company:</strong> <?= htmlspecialchars($order['company_name'] ?? 'N/A') ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order['client_email'] ?? 'N/A') ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($order['client_phone'] ?? 'N/A') ?></p>
                            <p><strong>Business Type:</strong> <?= htmlspecialchars($order['business_type'] ?? 'N/A') ?></p>
                            <p><strong>Category:</strong> <?= htmlspecialchars($order['category'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Order Status Tracking</h5>
                            <div class="list-group">
                                <?php foreach ($status_colors as $status_name => $badge_color): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center <?= $order['status'] === $status_name ? 'active text-white' : '' ?>">
                                        <?= htmlspecialchars($status_name) ?>
                                        <?php if ($order['status'] === $status_name): ?>
                                            <span class="badge bg-light text-dark">Current</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($order['assigned_driver'])): ?>
            <div class="row g-4 mt-3">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Assigned Delivery Team</h5>
                            <p><strong>Driver:</strong> <?= htmlspecialchars($order['assigned_driver']) ?></p>
                            <p><strong>Vehicle:</strong> <?= htmlspecialchars($order['assigned_vehicle'] ?? '') ?></p>
                            <p><strong>Route:</strong> <?= htmlspecialchars($order['assigned_route'] ?? '') ?></p>
                            <p><strong>Shift:</strong> <?= htmlspecialchars($order['assigned_shift'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (($_SESSION['role'] ?? '') === 'Delivery' && $order['assigned_user_id'] == $_SESSION['user_id'] && $order['status'] === 'Out for Delivery'): ?>
            <div class="row g-4 mt-3">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Proof of Delivery</h5>
                            <?php if (!empty($order['delivery_proof_image'])): ?>
                                <div class="mb-3">
                                    <strong>Proof photo uploaded:</strong>
                                    <a href="<?= htmlspecialchars($order['delivery_proof_image']) ?>" target="_blank">View image</a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($order['delivery_otp'])): ?>
                                <div class="alert alert-info">Delivery OTP has been generated. Ask the client for the OTP and verify it below.</div>
                            <?php else: ?>
                                <div class="alert alert-warning">Delivery OTP will be generated when this order is marked Out for Delivery.</div>
                            <?php endif; ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proofDeliveryModal">Submit Proof / Verify OTP</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php if (($_SESSION['role'] ?? '') === 'Delivery' && $order['assigned_user_id'] == $_SESSION['user_id'] && $order['status'] === 'Out for Delivery'): ?>
<div class="modal fade" id="proofDeliveryModal" tabindex="-1" aria-labelledby="proofDeliveryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofDeliveryModalLabel">Proof of Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p class="mb-2">Use one of the following methods to complete delivery:</p>
                    <ul>
                        <li>Verify the OTP provided by the client.</li>
                        <li>Upload a proof photo and mark the order as Delivered.</li>
                    </ul>
                </div>

                <div class="row gx-4 gy-4">
                    <div class="col-12 col-md-6">
                        <div class="card border-secondary h-100">
                            <div class="card-body">
                                <h5 class="card-title">Verify OTP</h5>
                                <form method="post" action="proof-of-delivery.php?id=<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="action" value="verify_otp">
                                    <div class="mb-3">
                                        <label for="otp" class="form-label">Client OTP</label>
                                        <input type="text" id="otp" name="otp" class="form-control" placeholder="Enter OTP" required>
                                    </div>
                                    <button class="btn btn-success">Verify OTP</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="card border-secondary h-100">
                            <div class="card-body">
                                <h5 class="card-title">Upload Proof Photo</h5>
                                <form method="post" action="proof-of-delivery.php?id=<?= (int)$order['id'] ?>" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_photo">
                                    <div class="mb-3">
                                        <label for="delivery_proof_image" class="form-label">Select photo</label>
                                        <input type="file" id="delivery_proof_image" name="delivery_proof_image" class="form-control" accept="image/png,image/jpeg" required>
                                    </div>
                                    <button class="btn btn-secondary">Upload Photo</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php';
