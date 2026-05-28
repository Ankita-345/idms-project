<?php
require 'db.php';

require_role(['Admin', 'Operations Manager']);

$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Fetch all clients, optionally filtered by search
if (!empty($search)) {
    $stmt = mysqli_prepare($conn, 'SELECT id, company_name, business_type, category, phone, email, credit_limit, status FROM clients WHERE company_name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY company_name ASC');
    $search_param = '%' . $search . '%';
    mysqli_stmt_bind_param($stmt, 'sss', $search_param, $search_param, $search_param);
} else {
    $stmt = mysqli_prepare($conn, 'SELECT id, company_name, business_type, category, phone, email, credit_limit, status FROM clients ORDER BY company_name ASC');
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$clients = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = 'Client Management - IDMS';
include 'includes/header.php';
?>
<style>
    .client-action-buttons .btn {
        height: auto !important;
        min-height: unset !important;
        padding: 3px 8px !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        border-radius: 6px !important;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .client-action-buttons .btn i {
        font-size: 12px !important;
        margin-right: 2px !important;
    }
</style>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Clients</h1>
                        <p class="page-subtitle">Manage Ice Distribution clients.</p>
                    </div>
                    <a href="add-client.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Client</a>
                </div>
            </div>

            <!-- Search Form -->
            <div class="search-box mb-4">
                <form method="get" action="clients.php" class="row g-2">
                    <div class="col-12 col-md-8">
                        <input type="text" class="form-control" name="search" placeholder="Search by company name, phone, or email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Search</button>
                        <a href="clients.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></a>
                    </div>
                </form>
            </div>

            <!-- Clients Table -->
            <div class="card table-wrapper">
                <div class="card-body">
                    <h5 class="table-title"><i class="bi bi-table me-2"></i>Client List</h5>
                    <?php if (!empty($clients)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:20%;">Company</th>
                                        <th style="width:12%;">Business Type</th>
                                        <th style="width:10%;">Category</th>
                                        <th style="width:12%;">Phone</th>
                                        <th style="width:15%;">Email</th>
                                        <th style="width:10%;">Credit Limit</th>
                                        <th style="width:8%;">Status</th>
                                        <th style="width:13%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($client['company_name']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge-inline" style="background: rgba(99,102,241,.15); color: #4f46e5;"><?= htmlspecialchars($client['business_type']) ?></span>
                                            </td>
                                            <td>
                                                <span><?= htmlspecialchars($client['category']) ?></span>
                                            </td>
                                            <td>
                                                <code style="font-size:.85rem;"><?= htmlspecialchars($client['phone'] ?? '–') ?></code>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($client['email'] ?? '–') ?></small>
                                            </td>
                                            <td>
                                                <strong><?= number_format($client['credit_limit'] ?? 0, 2) ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $client['status'] ?? 'Unknown';
                                                $status_class = 'light'; // Default color
                                                if (strtolower($status) === 'active') {
                                                    $status_class = 'success';
                                                } elseif (strtolower($status) === 'inactive') {
                                                    $status_class = 'secondary';
                                                } elseif (strtolower($status) === 'suspended') {
                                                    $status_class = 'danger';
                                                }
                                                ?>
                                                <span class="badge text-bg-<?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                                            </td>
                                            <td>
                                                <div class="client-action-buttons d-flex gap-1 align-items-center">
                                                    <a href="edit-client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-primary px-2 py-1"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                                                    <a href="delete-client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-danger px-2 py-1" onclick="return confirm('Delete this client?');"><i class="bi bi-trash me-1"></i>Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="color: var(--text-muted);">
                            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                            <p>No clients found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';