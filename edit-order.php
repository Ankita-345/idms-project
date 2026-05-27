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

// Fetch order
$o_stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($o_stmt, 'i', $order_id);
mysqli_stmt_execute($o_stmt);
$o_res = mysqli_stmt_get_result($o_stmt);
if (mysqli_num_rows($o_res) === 0) {
    header('Location: orders.php');
    exit;
}
$order = mysqli_fetch_assoc($o_res);
mysqli_stmt_close($o_stmt);

// Fetch clients and addresses
$clients_res = mysqli_query($conn, 'SELECT id, company_name FROM clients WHERE status = "Active" ORDER BY company_name');
$clients = mysqli_fetch_all($clients_res, MYSQLI_ASSOC);
$addresses_by_client = [];
$addr_res = mysqli_query($conn, 'SELECT id, client_id, street_address, city, state, postal_code FROM client_addresses ORDER BY client_id');
while ($row = mysqli_fetch_assoc($addr_res)) {
    $addresses_by_client[$row['client_id']][] = $row;
}

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
    $status = $_POST['status'] ?? $order['status'];

    // Basic validation
    if ($client_id <= 0) {
        $error = 'Select a client.';
    } elseif (!in_array($ice_type, ['Cube','Block','Crushed','Dry Ice'])) {
        $error = 'Select a valid ice type.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be at least 1.';
    } elseif (empty($delivery_date) || empty($delivery_time_slot)) {
        $error = 'Delivery date and time slot are required.';
    } else {
        $old_status = $order['status'];
        $old_inventory_deducted = (int)$order['inventory_deducted'];

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

        $inventory_action = '';
        $new_inventory_deducted = $old_inventory_deducted;

        if (in_array($status, ['Confirmed','Assigned']) && !in_array($old_status, ['Confirmed','Assigned']) && $old_inventory_deducted === 0) {
            $inventory_action = 'deduct';
            $new_inventory_deducted = 1;
        }

        if (in_array($status, ['Cancelled','Failed']) && !in_array($old_status, ['Cancelled','Failed']) && $old_inventory_deducted === 1) {
            $inventory_action = 'restore';
            $new_inventory_deducted = 0;
        }

        if ($inventory_action !== '') {
            mysqli_begin_transaction($conn);
            $inv_stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE ice_type = ? FOR UPDATE');
            mysqli_stmt_bind_param($inv_stmt, 's', $ice_type);
            mysqli_stmt_execute($inv_stmt);
            mysqli_stmt_bind_result($inv_stmt, $inv_qty);
            $has_row = mysqli_stmt_fetch($inv_stmt);
            mysqli_stmt_close($inv_stmt);

            if (!$has_row) {
                mysqli_rollback($conn);
                $error = 'Inventory record not found for selected ice type.';
            } else {
                if ($inventory_action === 'deduct') {
                    if ((int)$inv_qty < (int)$quantity) {
                        mysqli_rollback($conn);
                        $error = 'Insufficient stock to confirm/assign this order.';
                    } else {
                        $dec = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity - ?, updated_at = NOW() WHERE ice_type = ?');
                        mysqli_stmt_bind_param($dec, 'is', $quantity, $ice_type);
                        $dec_ok = mysqli_stmt_execute($dec);
                        mysqli_stmt_close($dec);
                        if (!$dec_ok) {
                            mysqli_rollback($conn);
                            $error = 'Failed to update inventory.';
                        }
                    }
                } else {
                    $inc = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity + ?, updated_at = NOW() WHERE ice_type = ?');
                    mysqli_stmt_bind_param($inc, 'is', $quantity, $ice_type);
                    $inc_ok = mysqli_stmt_execute($inc);
                    mysqli_stmt_close($inc);
                    if (!$inc_ok) {
                        mysqli_rollback($conn);
                        $error = 'Failed to restore inventory.';
                    }
                }
            }
        }

        if (empty($error)) {
            $u_stmt = mysqli_prepare($conn, 'UPDATE orders SET client_id = ?, client_address_id = ?, ice_type = ?, quantity = ?, bulk_order = ?, recurring = ?, delivery_date = ?, delivery_time_slot = ?, delivery_street = ?, delivery_city = ?, delivery_state = ?, delivery_postal_code = ?, special_instructions = ?, inventory_deducted = ?, status = ? WHERE id = ?');
            mysqli_stmt_bind_param($u_stmt, 'iisiiissssssssisi', $client_id, $client_address_id, $ice_type, $quantity, $bulk_order, $recurring, $delivery_date, $delivery_time_slot, $delivery_street, $delivery_city, $delivery_state, $delivery_postal_code, $special_instructions, $new_inventory_deducted, $status, $order_id);

            if (mysqli_stmt_execute($u_stmt)) {
                if ($inventory_action !== '') mysqli_commit($conn);
                $success = 'Order updated.';
                // Refresh order
                $o_stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE id = ? LIMIT 1');
                mysqli_stmt_bind_param($o_stmt, 'i', $order_id);
                mysqli_stmt_execute($o_stmt);
                $o_res = mysqli_stmt_get_result($o_stmt);
                $order = mysqli_fetch_assoc($o_res);
                mysqli_stmt_close($o_stmt);
            } else {
                if ($inventory_action !== '') mysqli_rollback($conn);
                $error = 'Failed to update order.';
            }
            mysqli_stmt_close($u_stmt);
        }
    }
}

// statuses list
$statuses = ['Pending','Confirmed','Assigned','Picked','In Transit','Out for Delivery','Delivered','Completed','Cancelled','Failed'];

$pageTitle = 'Edit Order - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="orders.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Edit Order #<?= $order_id ?></h1>
            </div>

            <div class="row">
                <div class="col-12 col-lg-8">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form method="post" action="edit-order.php?id=<?= $order_id ?>" novalidate>
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                    <select id="client_id" name="client_id" class="form-select" required>
                                        <option value="">-- Select client --</option>
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $order['client_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="ice_type" class="form-label">Ice Type <span class="text-danger">*</span></label>
                                        <select id="ice_type" name="ice_type" class="form-select" required>
                                            <option value="">-- Select --</option>
                                            <?php foreach (['Cube','Block','Crushed','Dry Ice'] as $it): ?>
                                                <option value="<?= $it ?>" <?= $order['ice_type'] === $it ? 'selected' : '' ?>><?= $it ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" id="quantity" name="quantity" class="form-control" min="1" value="<?= htmlspecialchars($order['quantity']) ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="delivery_date" class="form-label">Delivery Date <span class="text-danger">*</span></label>
                                        <input type="date" id="delivery_date" name="delivery_date" class="form-control" value="<?= htmlspecialchars($order['delivery_date']) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="delivery_time_slot" class="form-label">Delivery Time Slot <span class="text-danger">*</span></label>
                                        <input type="text" id="delivery_time_slot" name="delivery_time_slot" class="form-control" value="<?= htmlspecialchars($order['delivery_time_slot']) ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="client_address_id" class="form-label">Choose saved address (optional)</label>
                                    <select id="client_address_id" name="client_address_id" class="form-select">
                                        <option value="">-- Use custom address below --</option>
                                        <?php
                                        $cid = $order['client_id'];
                                        if (!empty($addresses_by_client[$cid])):
                                            foreach ($addresses_by_client[$cid] as $a):
                                        ?>
                                            <option value="<?= $a['id'] ?>" <?= $order['client_address_id'] == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['street_address'] . ', ' . $a['city']) ?></option>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="delivery_street" class="form-label">Street Address</label>
                                    <input type="text" id="delivery_street" name="delivery_street" class="form-control" value="<?= htmlspecialchars($order['delivery_street']) ?>">
                                </div>
                                <div class="row">
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="delivery_city" class="form-label">City</label>
                                        <input type="text" id="delivery_city" name="delivery_city" class="form-control" value="<?= htmlspecialchars($order['delivery_city']) ?>">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="delivery_state" class="form-label">State</label>
                                        <input type="text" id="delivery_state" name="delivery_state" class="form-control" value="<?= htmlspecialchars($order['delivery_state'] ?? '') ?>">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="delivery_postal_code" class="form-label">Postal Code</label>
                                        <input type="text" id="delivery_postal_code" name="delivery_postal_code" class="form-control" value="<?= htmlspecialchars($order['delivery_postal_code']) ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="special_instructions" class="form-label">Special Instructions</label>
                                    <textarea id="special_instructions" name="special_instructions" class="form-control" rows="3"><?= htmlspecialchars($order['special_instructions']) ?></textarea>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="bulk_order" name="bulk_order" <?= $order['bulk_order'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="bulk_order">Bulk order</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="recurring" name="recurring" <?= $order['recurring'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="recurring">Recurring order</label>
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select id="status" name="status" class="form-select">
                                        <?php foreach ($statuses as $s): ?>
                                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="d-grid">
                                    <button class="btn btn-primary btn-lg">Save Changes</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
const addressesByClient = <?= json_encode($addresses_by_client) ?>;
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
        opt.textContent = a.street_address + ', ' + a.city + (a.state ? ', ' + a.state : '') + (a.postal_code ? (' ('+a.postal_code+')') : '');
        addrSelect.appendChild(opt);
    });
}

clientSelect.addEventListener('change', function(){
    populateAddresses(this.value);
});

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
