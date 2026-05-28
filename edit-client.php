<?php
require 'db.php';

require_role(['Admin', 'Operations Manager']);

// Get client ID from URL
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($client_id === 0) {
    header('Location: clients.php');
    exit;
}

$error = '';
$success = '';

// Fetch client details
$stmt = mysqli_prepare($conn, 'SELECT company_name, business_type, category, contact_person, email, phone, credit_limit, payment_terms, status FROM clients WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $client_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    header('Location: clients.php');
    exit;
}

$client = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Initialize with existing values
$company_name = $client['company_name'];
$business_type = $client['business_type'];
$category = $client['category'];
$contact_person = $client['contact_person'];
$email = $client['email'];
$phone = $client['phone'];
$credit_limit = $client['credit_limit'];
$payment_terms = $client['payment_terms'];
$status = $client['status'];

// Fetch existing addresses
$addr_stmt = mysqli_prepare($conn, 'SELECT id, street_address, city, postal_code, address_type, is_default FROM client_addresses WHERE client_id = ?');
mysqli_stmt_bind_param($addr_stmt, 'i', $client_id);
mysqli_stmt_execute($addr_stmt);
$addr_result = mysqli_stmt_get_result($addr_stmt);
$addresses = mysqli_fetch_all($addr_result, MYSQLI_ASSOC);
mysqli_stmt_close($addr_stmt);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $business_type = $_POST['business_type'] ?? '';
    $category = $_POST['category'] ?? '';
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $credit_limit = $_POST['credit_limit'] ?? '0.00';
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    // Validate
    if (empty($company_name)) {
        $error = 'Company name is required.';
    } elseif (empty($business_type)) {
        $error = 'Business type is required.';
    } elseif (empty($category)) {
        $error = 'Client category is required.';
    } elseif (empty($phone)) {
        $error = 'Phone number is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email format is invalid.';
    } elseif ($credit_limit < 0) {
        $error = 'Credit limit cannot be negative.';
    } else {
        // Update client
        $update_stmt = mysqli_prepare($conn, 'UPDATE clients SET company_name = ?, business_type = ?, category = ?, contact_person = ?, email = ?, phone = ?, credit_limit = ?, payment_terms = ?, status = ? WHERE id = ?');
        mysqli_stmt_bind_param($update_stmt, 'ssssssdssi', $company_name, $business_type, $category, $contact_person, $email, $phone, $credit_limit, $payment_terms, $status, $client_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $success = 'Client updated successfully.';
        } else {
            $error = 'Failed to update client. Please try again.';
        }
        mysqli_stmt_close($update_stmt);
    }
}

$pageTitle = 'Edit Client - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="clients.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Edit Client</h1>
            </div>

            <div class="row">
                <div class="col-12 col-lg-8">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Client Information</h5>

                            <form method="post" action="edit-client.php?id=<?= $client_id ?>" novalidate>
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" value="<?= htmlspecialchars($company_name) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="contact_person" class="form-label">Contact Person</label>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= htmlspecialchars($contact_person) ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="business_type" class="form-label">Business Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="business_type" name="business_type" required>
                                            <option value="">-- Select --</option>
                                            <option value="Cafe" <?= $business_type === 'Cafe' ? 'selected' : '' ?>>Cafe</option>
                                            <option value="Restaurant" <?= $business_type === 'Restaurant' ? 'selected' : '' ?>>Restaurant</option>
                                            <option value="Event" <?= $business_type === 'Event' ? 'selected' : '' ?>>Event</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="category" class="form-label">Client Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">-- Select --</option>
                                            <option value="Regular" <?= $category === 'Regular' ? 'selected' : '' ?>>Regular</option>
                                            <option value="Priority" <?= $category === 'Priority' ? 'selected' : '' ?>>Priority</option>
                                            <option value="Bulk" <?= $category === 'Bulk' ? 'selected' : '' ?>>Bulk</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>">
                                    </div>
                                </div>

                                <h5 class="mb-3 mt-4">Credit & Payment</h5>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="credit_limit" class="form-label">Credit Limit</label>
                                        <input type="number" class="form-control" id="credit_limit" name="credit_limit" value="<?= htmlspecialchars($credit_limit) ?>" step="0.01" min="0">
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="payment_terms" class="form-label">Payment Terms</label>
                                        <input type="text" class="form-control" id="payment_terms" name="payment_terms" value="<?= htmlspecialchars($payment_terms) ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                                            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Update Client</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Delivery Addresses Section -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Delivery Addresses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($addresses)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Type</th>
                                                <th>Street Address</th>
                                                <th>City</th>
                                                <th>State</th>
                                                <th>Postal Code</th>
                                                <th>Default</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($addresses as $addr): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($addr['address_type']) ?></td>
                                                    <td><?= htmlspecialchars($addr['street_address']) ?></td>
                                                    <td><?= htmlspecialchars($addr['city']) ?></td>
                                                    <td><?= htmlspecialchars($addr['state'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($addr['postal_code']) ?></td>
                                                    <td><?= $addr['is_default'] ? '<span class="badge bg-success">Yes</span>' : '' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No delivery addresses added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';