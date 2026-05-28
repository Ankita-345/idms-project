<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ensure $canCreate is always defined to avoid "Undefined variable" warnings
$canCreate = in_array($_SESSION['role'] ?? '', ['Admin','Client']);

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$ice_type_filter = trim($_GET['ice_type'] ?? '');
$team_id_filter = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$team_name = '';

$ice_types = ['Cube', 'Crushed', 'Dry Ice', 'Block', 'Tube Ice'];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Client') {


    $statuses = [
        'Pending',
        'Confirmed',
        'Out for Delivery',
        'Delivered',
        'Cancelled'
    ];

} else {

    $statuses = [
        'Pending',
        'Confirmed',
        'Assigned',
        'Picked',
        'In Transit',
        'Out for Delivery',
        'Delivered',
        'Completed',
        'Cancelled',
        'Failed'
    ];
}
// Build query with optional filters
$sql = 'SELECT o.id, o.ice_type, o.quantity, o.delivery_date, o.delivery_time_slot, o.status, o.assigned_team_id, dt.driver_name AS assigned_team_name, c.company_name, c.email AS client_email, c.phone AS client_phone, COALESCE(o.delivery_city, ca.city) AS city, o.client_id FROM orders o LEFT JOIN clients c ON o.client_id = c.id LEFT JOIN client_addresses ca ON o.client_address_id = ca.id LEFT JOIN delivery_teams dt ON o.assigned_team_id = dt.id';
$where = [];
$params = [];
$types = '';

// If logged-in user is a Client, restrict to their own client record
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Client') {
    $user_id_session = $_SESSION['user_id'] ?? 0;
    if ($user_id_session) {
        $cstmt = mysqli_prepare($conn, 'SELECT id FROM clients WHERE user_id = ? LIMIT 1');
        mysqli_stmt_bind_param($cstmt, 'i', $user_id_session);
        mysqli_stmt_execute($cstmt);
        $cres = mysqli_stmt_get_result($cstmt);
        if ($cres && mysqli_num_rows($cres) === 1) {
            $crow = mysqli_fetch_assoc($cres);
            $client_id_for_user = (int)$crow['id'];
            $where[] = 'o.client_id = ?';
            $params[] = $client_id_for_user;
            $types .= 'i';
        }
        mysqli_stmt_close($cstmt);
    }
}

// If logged-in user is a Delivery user, restrict to orders assigned to their team (if mapped)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Delivery') {
    $user_id_session = $_SESSION['user_id'] ?? 0;
    if ($user_id_session) {
        $where[] = 'o.assigned_team_id IN (SELECT id FROM delivery_teams WHERE user_id = ?)';
        $params[] = $user_id_session;
        $types .= 'i';
    }
}

if ($search !== '') {
    $where[] = '(c.company_name LIKE ? OR o.special_instructions LIKE ? OR o.delivery_time_slot LIKE ? OR o.delivery_street LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}
if ($status_filter !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($ice_type_filter !== '') {
    $where[] = 'o.ice_type = ?';
    $params[] = $ice_type_filter;
    $types .= 's';
}

if ($team_id_filter > 0) {
    $where[] = 'o.assigned_team_id = ?';
    $params[] = $team_id_filter;
    $types .= 'i';

    // Fetch team name for the title
    $tstmt = mysqli_prepare($conn, 'SELECT driver_name FROM delivery_teams WHERE id = ?');
    mysqli_stmt_bind_param($tstmt, 'i', $team_id_filter);
    mysqli_stmt_execute($tstmt);
    $tres = mysqli_stmt_get_result($tstmt);
    if ($trow = mysqli_fetch_assoc($tres)) {
        $team_name = $trow['driver_name'];
    }
    mysqli_stmt_close($tstmt);
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY o.delivery_date DESC, o.created_at DESC';

$unit_map = [
    'Cube' => 'KG',
    'Crushed' => 'KG',
    'Dry Ice' => 'KG',
    'Block' => 'Pieces',
    'Tube Ice' => 'KG',
];

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        $bindParams = array_merge([$types], $params);
        $refs = [];
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $orders = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
        $orders = [];
    }
$pageTitle = 'Orders - IDMS';
include 'includes/header.php';
?>
<style>
.status-badge {
    font-weight: 600;
    padding: .4em .75em;
    border-radius: .5rem;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.status-pending { background-color: #fffbeb; color: #b45309; }
.status-confirmed { background-color: #eff6ff; color: #1d4ed8; }
.status-assigned { background-color: #f5f3ff; color: #5b21b6; }
.status-picked { background-color: #f3f4f6; color: #1f2937; }
.status-in-transit { background-color: #f0f9ff; color: #0369a1; }
.status-out-for-delivery { background-color: #ecfeff; color: #0e7490; }
.status-delivered { background-color: #f0fdf4; color: #15803d; }
.status-completed { background-color: #dcfce7; color: #166534; }
.status-cancelled { background-color: #fef2f2; color: #b91c1c; }
.status-failed { background-color: #fee2e2; color: #991b1b; }
</style>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="page-header">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <h1 class="page-title">
                            <?php if ($team_name): ?>
                                Orders Assigned to <?= htmlspecialchars($team_name) ?>
                            <?php else: ?>
                                Orders
                            <?php endif; ?>
                        </h1>
                        <p class="page-subtitle">
                            <?php if ($team_name): ?>
                                A list of all orders assigned to this team.
                            <?php else: ?>
                                Track order status and delivery progress.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($canCreate): ?>
                        <a href="add-order.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>New Order</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($_GET['success'])): ?>
                <div class="alert alert-success rounded-4 shadow-sm"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>

            <div class="card table-wrapper mb-4">
                <div class="card-body">
                    <h5 class="table-title"><i class="bi bi-funnel me-2"></i>Filter Orders</h5>
                    <form method="get" class="row g-3 align-items-end">
                        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Client'): ?>
                            <div class="col-12 col-md-5">
                                <input type="text" name="search" class="form-control" placeholder="Search by client, address or notes" value="<?= htmlspecialchars($search) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-12 col-md-4">
                      <select name="status" class="form-select shadow-sm rounded-3">                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                     <select name="ice_type" class="form-select shadow-sm rounded-3">                                <option value="">All Ice Types</option>
                                <?php foreach ($ice_types as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= $ice_type_filter === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100 rounded-3 shadow-sm">
                      <i class="bi bi-funnel me-1"></i> Filter
                             </button>                        </div>
                        <div class="col-12 col-md-2">
                <a href="orders.php" class="btn btn-light border w-100 rounded-3">
                   <i class="bi bi-x-circle me-1"></i> Clear
                            </a>                        </div>
                    </form>
                </div>
            </div>

            <div class="card table-wrapper">
                <div class="card-body">
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                   <table class="table table-hover align-middle modern-table">
                    <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Client</th>
                                        <th>Ice</th>
                                        <th>Qty</th>
                                        <th>Delivery</th>
                                        <th>City</th>
                                        <th>Team</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $o): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-dark">#<?= $o['id'] ?></span></td>
                                            <td><strong><?= htmlspecialchars($o['company_name'] ?? 'Client removed') ?></strong></td>
                                            <td><span class="badge-inline" style="background: rgba(56,189,248,.15); color: #0284c7;"><?= htmlspecialchars($o['ice_type']) ?></span></td>
                                            <td><?= htmlspecialchars($o['quantity'] . ' ' . ($unit_map[$o['ice_type']] ?? 'KG')) ?></td>
                                            <td><?= htmlspecialchars($o['delivery_date']) ?><br><small class="text-muted"><?= htmlspecialchars($o['delivery_time_slot']) ?></small></td>
                                            <td><?= htmlspecialchars($o['city'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($o['assigned_team_name'] ?? '-') ?></td>
                                            <td><span class="badge status-badge status-<?= strtolower(str_replace(' ', '-', $o['status'])) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                                    <a href="view-order.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-primary d-flex align-items-center" title="View">
                                                        <i class="bi bi-eye me-1"></i><span class="d-none d-md-inline">View</span>
                                                    </a>
                                                    <?php if (in_array($_SESSION['role'] ?? '', ['Admin','Manager'])): ?>
                                                        <a href="assign-order.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-info text-white d-flex align-items-center" title="Assign">
                                                            <i class="bi bi-truck me-1"></i><span class="d-none d-md-inline">Assign</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (in_array($_SESSION['role'] ?? '', ['Admin','Manager','Delivery'])): ?>
                                                        <a href="update-order-status.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-success d-flex align-items-center" title="Update Status">
                                                            <i class="bi bi-check2-circle me-1"></i><span class="d-none d-md-inline">Update</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($_SESSION['role'] ?? '' === 'Client' && $canCreate): ?>
                                                        <a href="edit-order.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary d-flex align-items-center" title="Edit">
                                                            <i class="bi bi-pencil-square me-1"></i><span class="d-none d-md-inline">Edit</span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5" style="color: var(--text-muted);">
                            <i class="bi bi-card-list" style="font-size: 3rem; opacity: 0.24; margin-bottom: 1rem; display: block;"></i>
                            <p>No orders found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include 'includes/footer.php';