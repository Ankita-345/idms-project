<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ice_type = $_POST['ice_type'] ?? '';
    $qty = (int)($_POST['quantity'] ?? 0);
    $threshold = (int)($_POST['low_threshold'] ?? 10);

    if (!in_array($ice_type, ['Cube','Block','Crushed','Dry Ice'])) {
        $error = 'Select a valid ice type.';
    } elseif ($qty <= 0) {
        $error = 'Quantity must be greater than zero.';
    } else {
        // Insert or update existing
        $exists = mysqli_prepare($conn, 'SELECT id FROM inventory WHERE ice_type = ? LIMIT 1');
        mysqli_stmt_bind_param($exists, 's', $ice_type);
        mysqli_stmt_execute($exists);
        mysqli_stmt_store_result($exists);
        if (mysqli_stmt_num_rows($exists) > 0) {
            mysqli_stmt_bind_result($exists, $iid);
            mysqli_stmt_fetch($exists);
            mysqli_stmt_close($exists);

            $u = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity + ?, low_threshold = ?, updated_at = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($u, 'iii', $qty, $threshold, $iid);
            if (mysqli_stmt_execute($u)) {
                $msg = 'Stock updated successfully.';
            } else {
                $error = 'Failed to update stock.';
            }
            mysqli_stmt_close($u);
        } else {
            mysqli_stmt_close($exists);
            $ins = mysqli_prepare($conn, 'INSERT INTO inventory (ice_type, quantity, low_threshold) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($ins, 'sii', $ice_type, $qty, $threshold);
            if (mysqli_stmt_execute($ins)) {
                $msg = 'Stock added successfully.';
            } else {
                $error = 'Failed to add stock.';
            }
            mysqli_stmt_close($ins);
        }
    }
}

$pageTitle = 'Add Stock - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="mb-4 border-bottom pb-3">
                <a href="inventory.php" class="btn btn-sm btn-secondary mb-2">← Back</a>
                <h1 class="h2">Add Stock</h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

                    <form method="post" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Ice Type</label>
                            <select name="ice_type" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach (['Cube','Block','Crushed','Dry Ice'] as $it): ?>
                                    <option value="<?= $it ?>"><?= $it ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="0" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Low Threshold</label>
                            <input type="number" name="low_threshold" class="form-control" min="0" value="10">
                        </div>

                        <div class="col-12">
                            <button class="btn btn-success">Add Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php';