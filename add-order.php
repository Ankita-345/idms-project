<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch clients and their addresses
$clients_res = mysqli_query($conn, 'SELECT id, company_name, email FROM clients WHERE status = "Active" ORDER BY company_name');
$clients = mysqli_fetch_all($clients_res, MYSQLI_ASSOC);

$addresses_by_client = [];
$addr_res = mysqli_query($conn, 'SELECT id, client_id, street_address, city, state, postal_code FROM client_addresses ORDER BY client_id');
$address_seen = [];
while ($row = mysqli_fetch_assoc($addr_res)) {
    $key = $row['client_id'] . '|' . mb_strtolower(trim($row['street_address'])) . '|' . mb_strtolower(trim($row['city'])) . '|' . mb_strtolower(trim($row['state'])) . '|' . mb_strtolower(trim($row['postal_code']));
    if (!isset($address_seen[$key])) {
        $address_seen[$key] = true;
        $addresses_by_client[$row['client_id']][] = $row;
    }
}

$past_res = mysqli_query($conn, 'SELECT client_id, delivery_street, delivery_city, delivery_state, delivery_postal_code FROM orders WHERE client_id IS NOT NULL AND (delivery_street IS NOT NULL AND delivery_street <> "" OR delivery_city IS NOT NULL AND delivery_city <> "" OR delivery_state IS NOT NULL AND delivery_state <> "" OR delivery_postal_code IS NOT NULL AND delivery_postal_code <> "") ORDER BY delivery_date DESC');
while ($row = mysqli_fetch_assoc($past_res)) {
    $client_id = (int)$row['client_id'];
    $key = $client_id . '|' . mb_strtolower(trim($row['delivery_street'])) . '|' . mb_strtolower(trim($row['delivery_city'])) . '|' . mb_strtolower(trim($row['delivery_state'])) . '|' . mb_strtolower(trim($row['delivery_postal_code']));
    if ($row['delivery_street'] === '' && $row['delivery_city'] === '' && $row['delivery_postal_code'] === '' && $row['delivery_state'] === '') {
        continue;
    }
    if (!isset($address_seen[$key])) {
        $address_seen[$key] = true;
        $row['id'] = 'hist_' . md5($key);
        $row['address_type'] = 'Previous order';
        $row['street_address'] = $row['delivery_street'];
        $row['state'] = $row['delivery_state'];
        $row['postal_code'] = $row['delivery_postal_code'];
        $addresses_by_client[$client_id][] = $row;
    }
}

// If logged in user is a Client, try to resolve their client record by user_id.
$user_role = $_SESSION['role'] ?? '';
$user_id_session = $_SESSION['user_id'] ?? 0;
$resolved_client = null;
if ($user_role === 'Client' && $user_id_session) {
    $cstmt = mysqli_prepare($conn, 'SELECT id, company_name FROM clients WHERE user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($cstmt, 'i', $user_id_session);
    mysqli_stmt_execute($cstmt);
    $cres = mysqli_stmt_get_result($cstmt);
    if ($cres && mysqli_num_rows($cres) === 1) {
        $resolved_client = mysqli_fetch_assoc($cres);
    } else {
        // Create a client record automatically for this user (basic)
        $name = $_SESSION['full_name'] ?? '';
        $email_user = $_SESSION['email'] ?? '';
        $ins = mysqli_prepare($conn, 'INSERT INTO clients (user_id, company_name, email, phone, business_type, category) VALUES (?, ?, ?, ?, ?, ?)');
        $phone_empty = '';
        $default_bus = 'Cafe';
        $default_cat = 'Regular';
        mysqli_stmt_bind_param($ins, 'isssss', $user_id_session, $name, $email_user, $phone_empty, $default_bus, $default_cat);
        if (mysqli_stmt_execute($ins)) {
            $new_id = mysqli_insert_id($conn);
            $resolved_client = ['id' => $new_id, 'company_name' => $name];
            $clients[] = ['id' => $new_id, 'company_name' => $name, 'email' => $email_user];
        }
        mysqli_stmt_close($ins);
    }
    mysqli_stmt_close($cstmt);
}

$unit_map = [
    'Cube' => 'KG',
    'Crushed' => 'KG',
    'Dry Ice' => 'KG',
    'Block' => 'Pieces',
    'Tube Ice' => 'KG',
];

$time_slots = [
    '6 AM - 9 AM',
    '9 AM - 12 PM',
    '12 PM - 3 PM',
    '3 PM - 6 PM',
    '6 PM - 9 PM',
];

$inventory_quantities = [];
$inv_stock_res = mysqli_query($conn, 'SELECT ice_type, quantity FROM inventory');
while ($row = mysqli_fetch_assoc($inv_stock_res)) {
    $inventory_quantities[$row['ice_type']] = (int)$row['quantity'];
}

$ice_type = $_POST['ice_type'] ?? '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$quantity_unit = $unit_map[$ice_type] ?? '';
$available_stock = $ice_type ? ($inventory_quantities[$ice_type] ?? null) : null;
if ($ice_type) {
    $stock_info_text = isset($inventory_quantities[$ice_type])
        ? 'Available Stock: ' . $available_stock . ' ' . $quantity_unit
        : 'No inventory record found for selected ice type.';
} else {
    $stock_info_text = 'Select an ice type to view available stock.';
}
$quantity_placeholder = 'Enter quantity' . ($quantity_unit ? ' in ' . $quantity_unit : '');
$delivery_date = $_POST['delivery_date'] ?? '';
$delivery_time_slot = $_POST['delivery_time_slot'] ?? '';
$client_address_id = !empty($_POST['client_address_id']) ? (int)$_POST['client_address_id'] : null;
$delivery_street = $_POST['delivery_street'] ?? '';
$delivery_city = $_POST['delivery_city'] ?? '';
$delivery_state = $_POST['delivery_state'] ?? '';
$delivery_postal_code = $_POST['delivery_postal_code'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $ice_type = $_POST['ice_type'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $delivery_date = $_POST['delivery_date'] ?? '';
    $delivery_time_slot = trim($_POST['delivery_time_slot'] ?? '');
    $client_address_id = !empty($_POST['client_address_id']) ? (int)$_POST['client_address_id'] : null;
    $delivery_street = trim($_POST['delivery_street'] ?? '');
    $delivery_city = trim($_POST['delivery_city'] ?? '');
    $delivery_state = trim($_POST['delivery_state'] ?? '');
    $delivery_postal_code = trim($_POST['delivery_postal_code'] ?? '');
    $special_instructions = trim($_POST['special_instructions'] ?? '');
    $bulk_order = isset($_POST['bulk_order']) ? 1 : 0;
    $recurring = isset($_POST['recurring']) ? 1 : 0;

    // Validation
    if ($user_role === 'Client' && $resolved_client) {
        $client_id = (int)$resolved_client['id'];
    } else {
        $client_id = (int)($_POST['client_id'] ?? 0);
    }

    if ($client_id <= 0) {
        $error = 'Select a client.';
    } elseif (!in_array($ice_type, ['Cube','Block','Crushed','Dry Ice'])) {
        $error = 'Select a valid ice type.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be at least 1.';
    } elseif (empty($delivery_date) || empty($delivery_time_slot)) {
        $error = 'Delivery date and delivery time slot are required.';
    } elseif (!in_array($delivery_time_slot, $time_slots, true)) {
        $error = 'Select a valid delivery time slot.';
    } else {
        // Check inventory stock availability BEFORE attempting to create order
        $inv_check_stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE ice_type = ? LIMIT 1');
        mysqli_stmt_bind_param($inv_check_stmt, 's', $ice_type);
        mysqli_stmt_execute($inv_check_stmt);
        mysqli_stmt_bind_result($inv_check_stmt, $available_qty);
        $inv_exists = mysqli_stmt_fetch($inv_check_stmt);
        mysqli_stmt_close($inv_check_stmt);

        if (!$inv_exists) {
            $error = 'No inventory record found for selected ice type.';
        } elseif ((int)$available_qty < (int)$quantity) {
            $error = 'Insufficient stock available. Only ' . $available_qty . ' ' . ($unit_map[$ice_type] ?? 'units') . ' available.';
        }
    }

    if (empty($error)) {
        // If client_address_id is provided, try to pull address snapshot
        if ($client_address_id) {
            $a_stmt = mysqli_prepare($conn, 'SELECT street_address, city, state, postal_code FROM client_addresses WHERE id = ? AND client_id = ? LIMIT 1');
            mysqli_stmt_bind_param($a_stmt, 'ii', $client_address_id, $client_id);
            mysqli_stmt_execute($a_stmt);
            mysqli_stmt_bind_result($a_stmt, $sa, $scity, $sstate, $spost);
            if (mysqli_stmt_fetch($a_stmt)) {
                $delivery_street = $sa;
                $delivery_city = $scity;
                $delivery_state = $sstate;
                $delivery_postal_code = $spost;
            }
            mysqli_stmt_close($a_stmt);
        }

        // Begin transaction to atomically create order and deduct inventory
        mysqli_begin_transaction($conn);

        // Lock and re-check inventory with FOR UPDATE to avoid race conditions
        $inv_lock_stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE ice_type = ? FOR UPDATE');
        mysqli_stmt_bind_param($inv_lock_stmt, 's', $ice_type);
        mysqli_stmt_execute($inv_lock_stmt);
        mysqli_stmt_bind_result($inv_lock_stmt, $final_qty);
        $lock_exists = mysqli_stmt_fetch($inv_lock_stmt);
        mysqli_stmt_close($inv_lock_stmt);

        if (!$lock_exists || (int)$final_qty < (int)$quantity) {
            mysqli_rollback($conn);
            $error = 'Insufficient stock available';
        } else {
            // Create order
            $inv_deducted = 0;
            $stmt = mysqli_prepare($conn, 'INSERT INTO orders (client_id, client_address_id, ice_type, quantity, bulk_order, recurring, delivery_date, delivery_time_slot, delivery_street, delivery_city, delivery_state, delivery_postal_code, special_instructions, inventory_deducted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            // types: client_id(i), client_address_id(i), ice_type(s), quantity(i), bulk_order(i), recurring(i), delivery_date(s), delivery_time_slot(s), delivery_street(s), delivery_city(s), delivery_state(s), delivery_postal_code(s), special_instructions(s), inventory_deducted(i)
            mysqli_stmt_bind_param($stmt, 'iisiiisssssssi', $client_id, $client_address_id, $ice_type, $quantity, $bulk_order, $recurring, $delivery_date, $delivery_time_slot, $delivery_street, $delivery_city, $delivery_state, $delivery_postal_code, $special_instructions, $inv_deducted);

            if (mysqli_stmt_execute($stmt)) {
                $new_order_id = mysqli_insert_id($conn);

                // Deduct inventory
                $dec_stmt = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity - ?, updated_at = NOW() WHERE ice_type = ?');
                mysqli_stmt_bind_param($dec_stmt, 'is', $quantity, $ice_type);
                if (mysqli_stmt_execute($dec_stmt)) {
                    // Mark order as inventory deducted
                    $mark_stmt = mysqli_prepare($conn, 'UPDATE orders SET inventory_deducted = 1 WHERE id = ?');
                    mysqli_stmt_bind_param($mark_stmt, 'i', $new_order_id);
                    mysqli_stmt_execute($mark_stmt);
                    mysqli_stmt_close($mark_stmt);
                    mysqli_stmt_close($dec_stmt);
                    mysqli_commit($conn);
                } else {
                    mysqli_stmt_close($dec_stmt);
                    mysqli_rollback($conn);
                    $error = 'Failed to update inventory.';
                    $new_order_id = 0;
                }
            } else {
                mysqli_rollback($conn);
                $error = 'Failed to create order.';
                $new_order_id = 0;
            }
            
            // Notify managers/admins about new order (only if order was created successfully)
            if (!empty($new_order_id) && (int)$new_order_id > 0) {
                require_once __DIR__ . '/includes/notify.php';
                $title = 'New Order Placed';
                $msg_body = 'Order #' . (int)$new_order_id . ' has been placed.';
                // Gracefully handle notification failures - don't crash order creation
                @notify_role($conn, 'Manager', $title, $msg_body, 'view-order.php?id=' . (int)$new_order_id);
                @notify_role($conn, 'Admin', $title, $msg_body, 'view-order.php?id=' . (int)$new_order_id);
            }
            
            mysqli_stmt_close($stmt);
        }

        // Proceed to auto-assignment only if order was created successfully
        if (empty($error) && isset($new_order_id) && $new_order_id > 0) {
            // Auto-assignment: find best available delivery team (inventory already deducted)
            $assign_msg = '';
            $assign_success = false;

            // Determine city for route matching
            $assign_city = trim($delivery_city ?? '');

            // Build base SQL for candidate teams
            $base_sql = "SELECT dt.id FROM delivery_teams dt WHERE dt.availability_status = 'Available' AND dt.vehicle_capacity >= ?";

            $params = [];
            $types = '';
            $params[] = $quantity;
            $types .= 'i';

            if ($assign_city !== '') {
                $base_sql .= " AND (LOWER(dt.route_allocation) = LOWER(?) OR LOWER(dt.route_allocation) LIKE CONCAT('%', LOWER(?), '%') OR LOWER(?) LIKE CONCAT('%', LOWER(dt.route_allocation), '%'))";
                $params[] = $assign_city;
                $params[] = $assign_city;
                $params[] = $assign_city;
                $types .= 'sss';
            }

            // Try to match shift_timing loosely
            if (!empty($delivery_time_slot)) {
                $base_sql .= " AND (dt.shift_timing LIKE ? OR ? LIKE CONCAT('%', dt.shift_timing, '%'))";
                $params[] = $delivery_time_slot;
                $params[] = $delivery_time_slot;
                $types .= 'ss';
            }

            $base_sql .= " ORDER BY (SELECT COUNT(*) FROM orders o2 WHERE o2.assigned_team_id = dt.id AND o2.status IN ('Assigned','Out for Delivery','Picked','In Transit')) ASC LIMIT 1";

            $a_stmt = mysqli_prepare($conn, $base_sql);
            if ($a_stmt) {
                if (!empty($params)) {
                    $bind = array_merge([$types], $params);
                    $refs = [];
                    foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
                    call_user_func_array([$a_stmt, 'bind_param'], $refs);
                }
                mysqli_stmt_execute($a_stmt);
                $a_res = mysqli_stmt_get_result($a_stmt);
                $team = $a_res ? mysqli_fetch_assoc($a_res) : null;
                mysqli_stmt_close($a_stmt);

                if ($team && isset($team['id'])) {
                    // Inventory already deducted during order creation; just assign order to team
                    $tid = (int)$team['id'];
                    $u_stmt = mysqli_prepare($conn, 'UPDATE orders SET assigned_team_id = ?, assigned_at = NOW(), status = "Assigned" WHERE id = ?');
                    mysqli_stmt_bind_param($u_stmt, 'ii', $tid, $new_order_id);
                    if (mysqli_stmt_execute($u_stmt)) {
                        $assign_success = true;
                        $assign_msg = 'Order auto-assigned to delivery team. <a href="orders.php">View orders</a>.';
                    } else {
                        $assign_msg = 'Failed to assign order.';
                    }
                    mysqli_stmt_close($u_stmt);
                } else {
                    $assign_msg = 'No delivery team currently available';
                }
            } else {
                $assign_msg = 'No delivery team currently available';
            }

            if ($assign_success) {
                $success = 'Order created and assigned successfully. <a href="orders.php">View orders</a>.';
            } else {
                // keep as pending; do not show delivery assignment errors to clients
                $success = 'Order created successfully. <a href="orders.php">View orders</a>.';
                if (!empty($assign_msg) && !in_array($user_role, ['Client'], true)) {
                    $error = $assign_msg;
                }
            }
        }
    }
}

$pageTitle = 'New Order - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="orders.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Create New Order</h1>
            </div>

            <div class="row">
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= $success ?></div>
                            <?php endif; ?>

                            <form method="post" action="add-order.php" novalidate>
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select id="client_id" name="client_id" class="form-select" required <?= $user_role === 'Client' ? 'disabled' : '' ?>>
                                        <option value="">-- Select client --</option>
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($resolved_client && $resolved_client['id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($user_role === 'Client' && $resolved_client): ?>
                                        <input type="hidden" name="client_id" value="<?= (int)$resolved_client['id'] ?>">
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="ice_type" class="form-label">Ice Type <span class="text-danger">*</span></label>
                                        <select id="ice_type" name="ice_type" class="form-select" required>
                                            <option value="">-- Select --</option>
                                            <option value="Cube" data-unit="KG" data-stock="<?= $inventory_quantities['Cube'] ?? 0 ?>" <?= $ice_type === 'Cube' ? 'selected' : '' ?>>Cube</option>
                                            <option value="Block" data-unit="Pieces" data-stock="<?= $inventory_quantities['Block'] ?? 0 ?>" <?= $ice_type === 'Block' ? 'selected' : '' ?>>Block</option>
                                            <option value="Crushed" data-unit="KG" data-stock="<?= $inventory_quantities['Crushed'] ?? 0 ?>" <?= $ice_type === 'Crushed' ? 'selected' : '' ?>>Crushed</option>
                                            <option value="Dry Ice" data-unit="KG" data-stock="<?= $inventory_quantities['Dry Ice'] ?? 0 ?>" <?= $ice_type === 'Dry Ice' ? 'selected' : '' ?>>Dry Ice</option>
                                        </select>
                                        <div id="stockInfo" class="form-text"><?= htmlspecialchars($stock_info_text) ?></div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" id="quantity" name="quantity" class="form-control" min="1" value="<?= htmlspecialchars($quantity ?: 1) ?>" placeholder="<?= htmlspecialchars($quantity_placeholder) ?>" required>
                                                <span class="input-group-text" id="quantityUnit"><?= htmlspecialchars($quantity_unit ?: 'unit') ?></span>
                                            </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="delivery_date" class="form-label">Delivery Date <span class="text-danger">*</span></label>
                                        <input type="date" id="delivery_date" name="delivery_date" class="form-control" value="<?= htmlspecialchars($delivery_date) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="delivery_time_slot" class="form-label">Delivery Time Slot <span class="text-danger">*</span></label>
                                        <select id="delivery_time_slot" name="delivery_time_slot" class="form-select" required>
                                            <option value="">-- Select time slot --</option>
                                            <?php foreach ($time_slots as $slot): ?>
                                                <option value="<?= htmlspecialchars($slot) ?>" <?= $delivery_time_slot === $slot ? 'selected' : '' ?>><?= htmlspecialchars($slot) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <h6 class="mt-3">Delivery Address</h6>
                                <div class="mb-3">
                                    <label for="client_address_id" class="form-label">Choose saved address (optional)</label>
                                    <select id="client_address_id" name="client_address_id" class="form-select">
                                        <option value="">-- Use custom address below --</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="delivery_street" class="form-label">Street Address</label>
                                    <input type="text" id="delivery_street" name="delivery_street" class="form-control" value="<?= htmlspecialchars($delivery_street) ?>">
                                </div>
                                <div class="row">
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="delivery_city" class="form-label">City</label>
                                        <input type="text" id="delivery_city" name="delivery_city" class="form-control" value="<?= htmlspecialchars($delivery_city) ?>">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="delivery_state" class="form-label">State</label>
                                        <input type="text" id="delivery_state" name="delivery_state" class="form-control" value="<?= htmlspecialchars($delivery_state) ?>">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="delivery_postal_code" class="form-label">Postal Code</label>
                                        <input type="text" id="delivery_postal_code" name="delivery_postal_code" class="form-control" value="<?= htmlspecialchars($delivery_postal_code) ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="special_instructions" class="form-label">Special Instructions</label>
                                    <textarea id="special_instructions" name="special_instructions" class="form-control" rows="3"></textarea>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="bulk_order" name="bulk_order">
                                    <label class="form-check-label" for="bulk_order">Bulk order</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="recurring" name="recurring">
                                    <label class="form-check-label" for="recurring">Recurring order</label>
                                </div>

                                <div class="d-grid">
                                    <button class="btn btn-success btn-lg">Create Order</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var iceTypeSelect = document.getElementById('ice_type');
                    var quantityInput = document.getElementById('quantity');
                    var stockInfo = document.getElementById('stockInfo');

                    function updateStockInfo() {
                        var option = iceTypeSelect.options[iceTypeSelect.selectedIndex];
                        var unit = option.dataset.unit || 'units';
                        var stock = parseInt(option.dataset.stock || '0', 10);

                        if (!iceTypeSelect.value) {
                            stockInfo.textContent = 'Select an ice type to view available stock.';
                            quantityInput.placeholder = 'Enter quantity';
                            document.getElementById('quantityUnit').textContent = 'unit';
                            quantityInput.removeAttribute('max');
                            return;
                        }

                        quantityInput.placeholder = 'Enter quantity in ' + unit;
                        document.getElementById('quantityUnit').textContent = unit;

                        if (stock > 0) {
                            stockInfo.textContent = 'Available Stock: ' + stock + ' ' + unit;
                            quantityInput.max = stock;
                        } else {
                            stockInfo.textContent = 'Available Stock: 0 ' + unit;
                            quantityInput.removeAttribute('max');
                        }
                    }

                    iceTypeSelect.addEventListener('change', updateStockInfo);
                    updateStockInfo();
                });
            </script>
        </main>
    </div>
</div>

<script>
// Prepare addresses map
const addressesByClient = <?= json_encode($addresses_by_client) ?>;
const selectedAddressId = <?= json_encode($client_address_id) ?>;
const clientSelect = document.getElementById('client_id');
const addrSelect = document.getElementById('client_address_id');
const streetInput = document.getElementById('delivery_street');
const cityInput = document.getElementById('delivery_city');
const stateInput = document.getElementById('delivery_state');
const postalInput = document.getElementById('delivery_postal_code');

function populateAddresses(clientId) {
    addrSelect.innerHTML = '<option value="">-- Use custom address below --</option>';
    if (!clientId || !addressesByClient[clientId]) return;
    addressesByClient[clientId].forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.street_address + ', ' + a.city + (a.state ? ', ' + a.state : '') + (a.postal_code ? (' (' + a.postal_code + ')') : '');
        addrSelect.appendChild(opt);
    });
    if (selectedAddressId) {
        addrSelect.value = selectedAddressId;
        addrSelect.dispatchEvent(new Event('change'));
    }
}

clientSelect.addEventListener('change', function(){
    populateAddresses(this.value);
});

// If a client is already selected on page load, populate their saved addresses immediately.
if (clientSelect.value) {
    populateAddresses(clientSelect.value);
}

addrSelect.addEventListener('change', function(){
    const val = this.value;
    if (!val) {
        streetInput.value = '';
        cityInput.value = '';
        stateInput.value = '';
        postalInput.value = '';
        return;
    }
    const clientId = clientSelect.value;
    const list = addressesByClient[clientId] || [];
    const found = list.find(a => a.id == val);
    if (found) {
        streetInput.value = found.street_address || '';
        cityInput.value = found.city || '';
        stateInput.value = found.state || '';
        postalInput.value = found.postal_code || '';
    }
});
</script>

<?php include 'includes/footer.php';
