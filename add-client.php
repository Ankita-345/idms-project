<?php
require 'db.php';

require_role(['Admin', 'Operations Manager']);

$error = '';
$success = '';
$company_name = '';
$business_type = '';
$category = '';
$contact_person = '';
$email = '';
$phone = '';
$credit_limit = '0.00';
$payment_terms = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $company_name = trim($_POST['company_name'] ?? '');
    $business_type = $_POST['business_type'] ?? '';
    $category = $_POST['category'] ?? '';
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $credit_limit = $_POST['credit_limit'] ?? '0.00';
    $payment_terms = trim($_POST['payment_terms'] ?? '');

    // Validate required fields
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
        // Insert client into database
        $stmt = mysqli_prepare($conn, 'INSERT INTO clients (company_name, business_type, category, contact_person, email, phone, credit_limit, payment_terms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'ssssssds', $company_name, $business_type, $category, $contact_person, $email, $phone, $credit_limit, $payment_terms);

        if (mysqli_stmt_execute($stmt)) {
            $client_id = mysqli_insert_id($conn);

            // Get delivery address data from POST
            $street_address = trim($_POST['street_address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');

            // Insert primary delivery address if provided
            if (!empty($street_address) && !empty($city)) {
                $address_stmt = mysqli_prepare($conn, 'INSERT INTO client_addresses (client_id, address_type, street_address, city, state, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $address_type = 'Delivery';
                $is_default = 1;
                mysqli_stmt_bind_param($address_stmt, 'isssssi', $client_id, $address_type, $street_address, $city, $state, $postal_code, $is_default);
                mysqli_stmt_execute($address_stmt);
                mysqli_stmt_close($address_stmt);
            }

            $success = 'Client added successfully. <a href="clients.php">Back to list</a>.';
            // Reset form
            $company_name = '';
            $business_type = '';
            $category = '';
            $contact_person = '';
            $email = '';
            $phone = '';
            $credit_limit = '0.00';
            $payment_terms = '';
            $street_address = '';
            $city = '';
            $postal_code = '';
        } else {
            $error = 'Failed to add client. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}

$pageTitle = 'Add Client - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="clients.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Add New Client</h1>
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

                            <form method="post" action="add-client.php" novalidate>
                                <h5 class="mb-3">Client Information</h5>

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
                                        <input type="text" class="form-control" id="payment_terms" name="payment_terms" placeholder="e.g., Net 30 days" value="<?= htmlspecialchars($payment_terms) ?>">
                                    </div>
                                </div>

                                <h5 class="mb-3 mt-4">Delivery Address</h5>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="street_address" class="form-label">Street Address</label>
                                        <input type="text" class="form-control" id="street_address" name="street_address" placeholder="Building/Street details" value="<?= htmlspecialchars($_POST['street_address'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="state" class="form-label">State</label>
                                        <input type="text" class="form-control" id="state" name="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label for="postal_code" class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">Add Client</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';