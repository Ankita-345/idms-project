<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: inventory.php');
    exit;
}

$error = '';
$msg = '';

$stmt = mysqli_prepare($conn, 'SELECT id, ice_type, quantity, low_threshold FROM inventory WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if (!$res || mysqli_num_rows($res) === 0) {
    mysqli_stmt_close($stmt);
    header('Location: inventory.php');
    exit;
}
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)($_POST['quantity'] ?? $row['quantity']);
    $low_threshold = (int)($_POST['low_threshold'] ?? $row['low_threshold']);

    if ($quantity < 0) {
        $error = 'Quantity cannot be negative.';
    } else {
        $u = mysqli_prepare($conn, 'UPDATE inventory SET quantity = ?, low_threshold = ?, updated_at = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($u, 'iii', $quantity, $low_threshold, $id);
        if (mysqli_stmt_execute($u)) {
            $msg = 'Inventory updated.';
            // refresh
            $stmt = mysqli_prepare($conn, 'SELECT id, ice_type, quantity, low_threshold FROM inventory WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Failed to update inventory.';
        }
        mysqli_stmt_close($u);
    }
}

$pageTitle = 'Update Stock - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="inventory.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Update Stock — <?= htmlspecialchars($row['ice_type']) ?></h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Ice Type</label>
                            <input class="form-control" value="<?= htmlspecialchars($row['ice_type']) ?>" disabled>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="0" value="<?= htmlspecialchars($row['quantity']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Low Threshold</label>
                            <input type="number" name="low_threshold" class="form-control" min="0" value="<?= htmlspecialchars($row['low_threshold']) ?>">
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php';