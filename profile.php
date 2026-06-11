<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check and add 'address_type' column if it doesn't exist
$res = mysqli_query($conn, "SHOW COLUMNS FROM `client_addresses` LIKE 'address_type'");
if ($res && mysqli_num_rows($res) === 0) {
    mysqli_query($conn, "ALTER TABLE client_addresses ADD COLUMN address_type VARCHAR(50) DEFAULT 'Other'");
}

$user_role = $_SESSION['role'] ?? '';
$user_id_session = $_SESSION['user_id'] ?? 0;
$profile_client = null;
$addresses = [];
$profile_success = '';
$profile_error = '';
$user_phone = '';

// Fetch user's phone from users table
if ($user_id_session) {
    $stmt = mysqli_prepare($conn, 'SELECT phone FROM users WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $user_id_session);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $user_phone = $row['phone'] ?? '';
    }
    mysqli_stmt_close($stmt);
}

if ($user_role === 'Client' && $user_id_session) {
    $cstmt = mysqli_prepare($conn, 'SELECT id, company_name FROM clients WHERE user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($cstmt, 'i', $user_id_session);
    mysqli_stmt_execute($cstmt);
    $cres = mysqli_stmt_get_result($cstmt);
    if ($cres && mysqli_num_rows($cres) === 1) {
        $profile_client = mysqli_fetch_assoc($cres);
    } else {
        $name = $_SESSION['full_name'] ?? '';
        $email_user = $_SESSION['email'] ?? '';
        $ins = mysqli_prepare($conn, 'INSERT INTO clients (user_id, company_name, email, phone, business_type, category) VALUES (?, ?, ?, ?, ?, ?)');
        $phone_empty = '';
        $default_bus = 'Cafe';
        $default_cat = 'Regular';
        mysqli_stmt_bind_param($ins, 'isssss', $user_id_session, $name, $email_user, $phone_empty, $default_bus, $default_cat);
        if (mysqli_stmt_execute($ins)) {
            $new_id = mysqli_insert_id($conn);
            $profile_client = ['id' => $new_id, 'company_name' => $name];
        }
        mysqli_stmt_close($ins);
    }
    mysqli_stmt_close($cstmt);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_action'])) {
        $action = $_POST['address_action'];
        if ($action === 'add_address') {
            $address_type = trim($_POST['address_type'] ?? 'Home');
            $street_address = trim($_POST['street_address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            if ($profile_client && $street_address !== '' && $city !== '') {
                $client_id = (int)$profile_client['id'];
$address_type_safe = mysqli_real_escape_string($conn, $address_type);
$street_safe = mysqli_real_escape_string($conn, $street_address);
$city_safe = mysqli_real_escape_string($conn, $city);
$state_safe = mysqli_real_escape_string($conn, $state);
$postal_safe = mysqli_real_escape_string($conn, $postal_code);

$insert_sql = "
    INSERT INTO client_addresses 
    (client_id, address_type, street_address, city, state, postal_code, is_default)
    VALUES 
    ($client_id, '$address_type_safe', '$street_safe', '$city_safe', '$state_safe', '$postal_safe', 0)
";

if (mysqli_query($conn, $insert_sql)) {
    $profile_success = 'Address saved successfully.';
} else {
    $profile_error = 'Failed to save address: ' . mysqli_error($conn);
}
            } else {
                $profile_error = 'Street address and city are required.';
            }
        } elseif ($action === 'delete_address' && !empty($_POST['address_id'])) {
            $address_id = (int)$_POST['address_id'];
            if ($profile_client) {
                $del_stmt = mysqli_prepare($conn, 'DELETE FROM client_addresses WHERE id = ? AND client_id = ?');
                mysqli_stmt_bind_param($del_stmt, 'ii', $address_id, $profile_client['id']);
                if (mysqli_stmt_execute($del_stmt)) {
                    $profile_success = 'Address removed.';
                } else {
                    $profile_error = 'Failed to remove address.';
                }
                mysqli_stmt_close($del_stmt);
            }
        }
    }

    if ($profile_client) {
        $addr_stmt = mysqli_prepare($conn, 'SELECT id, address_type, street_address, city, state, postal_code, is_default FROM client_addresses WHERE client_id = ? ORDER BY id DESC');
        mysqli_stmt_bind_param($addr_stmt, 'i', $profile_client['id']);
        mysqli_stmt_execute($addr_stmt);
        $addr_res = mysqli_stmt_get_result($addr_stmt);
        $addresses = mysqli_fetch_all($addr_res, MYSQLI_ASSOC);
        mysqli_stmt_close($addr_stmt);
    }
}

$pageTitle = 'Profile - IDMS';
include 'includes/header.php';
?>
<style>
@media (max-width: 768px) {
    .text-end {
        text-align: left !important;
    }

    .table-responsive .btn {
        width: 100%;
    }
}
</style>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

<main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-3 px-md-4 py-4">   
<div class="page-header mb-3">
                    <div>
                    <h1 class="page-title">Profile</h1>
                    <p class="page-subtitle">Your account details and contact information.</p>
                </div>
            </div>

            <?php $role = $_SESSION['role'] ?? ''; ?>
            <div class="row g-4">
                <div class="col-12">
                    <div class="card form-card">
                        <div class="card-body">
                            <h5 class="form-section-title">Account Information</h5>
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user_phone ?: 'Not provided') ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($role === 'Client'): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card form-card">
                            <div class="card-body">
                                <h5 class="form-section-title">Saved Delivery Addresses</h5>
                                <?php if ($profile_success): ?>
                                    <div class="alert alert-success"><?= htmlspecialchars($profile_success) ?></div>
                                <?php endif; ?>
                                <?php if ($profile_error): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($profile_error) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($addresses)): ?>
                                    <div class="table-responsive mb-4">
                                        <table class="table table-borderless align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Address</th>
                                                    <th>City</th>
                                                    <th>State</th>
                                                    <th>Postal</th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($addresses as $address): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($address['address_type'] ?: 'Other') ?></td>
                                                        <td><?= htmlspecialchars($address['street_address']) ?></td>
                                                        <td><?= htmlspecialchars($address['city']) ?></td>
                                                        <td><?= htmlspecialchars($address['state'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($address['postal_code']) ?></td>
                                                        <td class="text-end">
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="address_action" value="delete_address">
                                                                <input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No saved delivery addresses yet. Add one below to speed up checkout.</p>
                                <?php endif; ?>
                                <form method="post" class="row g-3">
                                    <input type="hidden" name="address_action" value="add_address">
                                    <div class="col-12 col-md-3">
                                        <label class="form-label">Address Type</label>
                                        <select name="address_type" class="form-select">
                                            <option value="Home">Home</option>
                                            <option value="Office">Office</option>
                                            <option value="Shop">Shop</option>
                                            <option value="Warehouse">Warehouse</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label class="form-label">Street Address</label>
                                        <input type="text" name="street_address" class="form-control" placeholder="Building / Street" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">City</label>
                                        <input type="text" name="city" class="form-control" placeholder="City" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">State</label>
                                        <input type="text" name="state" class="form-control" placeholder="State">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" name="postal_code" class="form-control" placeholder="Postal Code">
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary">Save Address</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';