<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin','Manager'])) {
    header('Location: index.php');
    exit;
}

$msg = '';
$error = '';

$low_res = mysqli_query($conn, 'SELECT COUNT(*) AS low_count FROM inventory WHERE quantity <= low_threshold');
$low_count = (int)(mysqli_fetch_assoc($low_res)['low_count'] ?? 0);

// Most used ice type (by confirmed/assigned/delivered orders)
$usage_sql = "SELECT ice_type, COUNT(*) AS cnt FROM orders WHERE status IN ('Confirmed','Assigned','Picked','In Transit','Out for Delivery','Delivered') GROUP BY ice_type ORDER BY cnt DESC LIMIT 1";
$usage_res = mysqli_query($conn, $usage_sql);
$most_used = ($usage_res && mysqli_num_rows($usage_res) === 1) ? mysqli_fetch_assoc($usage_res)['ice_type'] : '-';

$inv_res = mysqli_query($conn, 'SELECT * FROM inventory ORDER BY ice_type');
$inventory = mysqli_fetch_all($inv_res, MYSQLI_ASSOC);

$unit_map = [
    'Cube' => 'KG',
    'Crushed' => 'KG',
    'Dry Ice' => 'KG',
    'Block' => 'Pieces',
    'Tube Ice' => 'KG',
];

$pageTitle = 'Inventory - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <h1 class="h2">Inventory</h1>
                <div>
                    <a href="add-stock.php" class="btn btn-success">+ Add Stock</a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6>Low Stock Items</h6>
                            <h3><?= $low_count ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6>Most Used Ice Type</h6>
                            <h3><?= htmlspecialchars($most_used) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Ice Type</th>
                                <th>Quantity</th>
                                <th>Low Threshold</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $row):
                                $low = ((int)$row['quantity'] <= (int)$row['low_threshold']);
                            ?>
                            <tr class="<?= $low ? 'table-warning' : '' ?>">
                                <td><span class="badge bg-primary"><?= htmlspecialchars($row['ice_type']) ?></span></td>
                                <td><?= htmlspecialchars($row['quantity'] . ' ' . ($unit_map[$row['ice_type']] ?? 'KG')) ?></td>
                                <td><?= htmlspecialchars($row['low_threshold'] . ' ' . ($unit_map[$row['ice_type']] ?? 'KG')) ?></td>
                                <td><?= htmlspecialchars($row['updated_at']) ?></td>
                                <td>
                                    <a href="update-stock.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php';