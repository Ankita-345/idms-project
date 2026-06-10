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

$sql = 'SELECT o.*, c.company_name, c.email AS client_email, c.phone AS client_phone, c.business_type, c.category, ca.street_address AS addr_street, ca.city AS addr_city, ca.state AS addr_state, ca.postal_code AS addr_postal_code, o.delivery_state, dt.driver_name AS assigned_driver, dt.vehicle_type AS assigned_vehicle, dt.route_allocation AS assigned_route, dt.shift_timing AS assigned_shift, dt.phone AS delivery_phone, dt.user_id AS assigned_user_id, o.delivery_proof, o.delivery_proof_image, o.delivery_otp, o.delivered_at FROM orders o LEFT JOIN clients c ON o.client_id = c.id LEFT JOIN client_addresses ca ON o.client_address_id = ca.id LEFT JOIN delivery_teams dt ON o.assigned_team_id = dt.id WHERE o.id = ?';
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Order #<?= htmlspecialchars($order['id']) ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="orders.php" class="btn btn-sm btn-outline-secondary">Back to Orders</a>
                </div>
            </div>

            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>

            <!-- Main Details Row -->
            <div class="row g-4 mb-4">
                <!-- Order Details Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">Order Details</h5>
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Ice Type:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['ice_type']) ?></dd>

                                <dt class="col-sm-5">Quantity:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['quantity'] . ' ' . ($unit_map[$order['ice_type']] ?? 'KG')) ?></dd>

                                <dt class="col-sm-5">Delivery Date:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_date']) ?></dd>

                                <dt class="col-sm-5">Time Slot:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_time_slot']) ?></dd>

                                <dt class="col-sm-5">Status:</dt>
                                <dd class="col-sm-7"><span class="badge bg-<?= $status_badge ?>"><?= htmlspecialchars($order['status']) ?></span></dd>

                                <dt class="col-sm-5">Bulk Order:</dt>
                                <dd class="col-sm-7"><?= $order['bulk_order'] ? 'Yes' : 'No' ?></dd>

                                <dt class="col-sm-5">Recurring:</dt>
                                <dd class="col-sm-7"><?= $order['recurring'] ? 'Yes' : 'No' ?></dd>

                                <?php if(!empty($order['special_instructions'])): ?>
                                <dt class="col-sm-5">Instructions:</dt>
                                <dd class="col-sm-7"><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">Delivery Address</h5>
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Street:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_street'] ?? $order['addr_street'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">City:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_city'] ?? $order['addr_city'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">State:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_state'] ?? $order['addr_state'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Postal Code:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_postal_code'] ?? $order['addr_postal_code'] ?? 'N/A') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Secondary Details Row -->
            <div class="row g-4 mb-4">
                <!-- Client Info Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">Client Info</h5>
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Company:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['company_name'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Email:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['client_email'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Phone:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['client_phone'] ?? 'N/A') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Status Tracking Card -->
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">Order Status Tracking</h5>
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

            <!-- Tertiary Details Row for Delivery and Proof -->
            <div class="row g-4">
                <?php if (!empty($order['assigned_driver'])): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-3">Assigned Delivery Team</h5>
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Driver:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['assigned_driver']) ?></dd>

                                <dt class="col-sm-5">Phone:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['delivery_phone'] ?? 'Not available') ?></dd>

                                <dt class="col-sm-5">Vehicle:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['assigned_vehicle'] ?? '') ?></dd>

                                <dt class="col-sm-5">Route:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['assigned_route'] ?? '') ?></dd>

                                <dt class="col-sm-5">Shift:</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($order['assigned_shift'] ?? '') ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($order['status'] === 'Delivered' && !empty($order['delivery_proof'])): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <h5 class="card-title mb-3">Delivery Proof</h5>
                            <p>Proof of delivery has been submitted.</p>
                            <a href="<?= htmlspecialchars($order['delivery_proof']) ?>" class="btn btn-success" target="_blank">View Delivery Proof</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

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