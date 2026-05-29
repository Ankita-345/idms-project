<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header('Location: login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: orders.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, amount FROM orders WHERE id = ? AND client_id = (SELECT id FROM clients WHERE user_id = ?)');
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    header('Location: orders.php');
    exit;
}

$pageTitle = 'Pay for Order - IDMS';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="page-header">
                <h1 class="page-title">Pay for Order #<?= htmlspecialchars($order['id']) ?></h1>
            </div>

            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Scan to Pay</h5>
                    <p>Please scan the QR code below to complete your payment.</p>
                 <img src="assets/images/payment-qr.jpeg" alt="Payment QR Code" class="img-fluid" style="max-width: 300px;">                    <h4 class="mt-3">Order Amount: ₹<?= htmlspecialchars(number_format($order['amount'], 2)) ?></h4>
                    <div class="alert alert-info mt-4">
                        <strong>Important:</strong> Payment will be verified by Admin. Your order will be marked as paid after confirmation.
                    </div>
                    <a href="orders.php" class="btn btn-primary mt-3">Back to My Orders</a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>