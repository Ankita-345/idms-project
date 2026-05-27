<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'Client';
$user_id = $_SESSION['user_id'] ?? 0;

if (!in_array($role, ['Admin', 'Manager', 'Operations Manager', 'Delivery', 'Client'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Reports & Analytics - IDMS';
include 'includes/header.php';

$totals = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'delivered_orders' => 0,
    'total_clients' => 0,
    'total_teams' => 0,
];

$driver_performance = [];
$sales_by_client = [];
$own_orders_summary = [];
$assigned_summary = [];

if (in_array($role, ['Admin', 'Manager', 'Operations Manager'])) {
    $row = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total_orders FROM orders'));
    $totals['total_orders'] = (int)($row['total_orders'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = 'Pending'"));
    $totals['pending_orders'] = (int)($row['pending_orders'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS delivered_orders FROM orders WHERE status IN ('Delivered','Completed')"));
    $totals['delivered_orders'] = (int)($row['delivered_orders'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total_clients FROM clients'));
    $totals['total_clients'] = (int)($row['total_clients'] ?? 0);
    $row = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS total_teams FROM delivery_teams'));
    $totals['total_teams'] = (int)($row['total_teams'] ?? 0);

    $driver_res = mysqli_query($conn, "SELECT dt.id, dt.driver_name, COUNT(o.id) AS total_assigned,
        SUM(o.status IN ('Delivered','Completed')) AS delivered_count,
        SUM(o.status IN ('Out for Delivery','Picked','In Transit')) AS in_progress
        FROM delivery_teams dt
        LEFT JOIN orders o ON o.assigned_team_id = dt.id
        GROUP BY dt.id ORDER BY total_assigned DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($driver_res)) {
        $driver_performance[] = [
            'driver_name' => $r['driver_name'],
            'total_assigned' => (int)$r['total_assigned'],
            'delivered_count' => (int)$r['delivered_count'],
            'in_progress' => (int)$r['in_progress'],
        ];
    }

    $sales_res = mysqli_query($conn, "SELECT c.company_name, COUNT(o.id) AS order_count, SUM(o.quantity) AS total_quantity
        FROM orders o
        JOIN clients c ON o.client_id = c.id
        GROUP BY c.id ORDER BY order_count DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($sales_res)) {
        $sales_by_client[] = $r;
    }
} elseif ($role === 'Delivery') {
    $team_id = null;
    $tstmt = mysqli_prepare($conn, 'SELECT id FROM delivery_teams WHERE user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($tstmt, 'i', $user_id);
    mysqli_stmt_execute($tstmt);
    $tres = mysqli_stmt_get_result($tstmt);
    if ($tres && mysqli_num_rows($tres) === 1) {
        $team_id = (int)mysqli_fetch_assoc($tres)['id'];
    }
    mysqli_stmt_close($tstmt);

    if ($team_id) {
        $assigned_summary['total_assigned'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE assigned_team_id = $team_id"))['cnt'];
        $assigned_summary['pending'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE assigned_team_id = $team_id AND status NOT IN ('Delivered','Completed')"))['cnt'];
        $assigned_summary['delivered'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE assigned_team_id = $team_id AND status IN ('Delivered','Completed')"))['cnt'];
        $assigned_summary['delayed'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE assigned_team_id = $team_id AND status NOT IN ('Delivered','Completed') AND delivery_date < CURDATE()"))['cnt'];
        $status_res = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM orders WHERE assigned_team_id = $team_id GROUP BY status");
        while ($r = mysqli_fetch_assoc($status_res)) {
            $orders_status_counts[$r['status']] = (int)$r['cnt'];
        }
    }
}
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2">Reports & Analytics</h1>
                    <p class="text-muted mb-0">System insights for <?= htmlspecialchars($role) ?> users.</p>
                </div>
            </div>

            <?php if (in_array($role, ['Admin', 'Manager', 'Operations Manager'])): ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="card border-primary shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Total Orders</h6>
                                <p class="display-6 mb-0"><?= $totals['total_orders'] ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card border-warning shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Pending Orders</h6>
                                <p class="display-6 mb-0"><?= $totals['pending_orders'] ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card border-success shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Delivered Orders</h6>
                                <p class="display-6 mb-0"><?= $totals['delivered_orders'] ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="card border-info shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Clients</h6>
                                <p class="display-6 mb-0"><?= $totals['total_clients'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Sales by Client</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Client</th>
                                                <th>Orders</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sales_by_client as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['company_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['order_count']) ?></td>
                                                    <td><?= htmlspecialchars($row['total_quantity']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Driver Performance</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Driver</th>
                                        <th>Assigned</th>
                                        <th>Delivered</th>
                                        <th>In Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($driver_performance as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['driver_name']) ?></td>
                                            <td><?= $row['total_assigned'] ?></td>
                                            <td><?= $row['delivered_count'] ?></td>
                                            <td><?= $row['in_progress'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Client'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">My Orders</h6>
                                <p class="display-6 mb-0"><?= $own_orders_summary['total_orders'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Pending</h6>
                                <p class="display-6 mb-0"><?= $own_orders_summary['pending_orders'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Delivered</h6>
                                <p class="display-6 mb-0"><?= $own_orders_summary['delivered_orders'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Order Status Breakdown</h5>
                        <div class="row g-3">
                            <?php foreach (['Pending','Confirmed','Assigned','Out for Delivery','Delivered','Completed','Cancelled','Failed'] as $status): ?>
                                <div class="col-6 col-md-3 mb-2">
                                    <span class="badge bg-secondary"><?= $status ?>: <?= $orders_status_counts[$status] ?? 0 ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Delivery'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Assigned Orders</h6>
                                <p class="display-6 mb-0"><?= $assigned_summary['total_assigned'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Pending</h6>
                                <p class="display-6 mb-0"><?= $assigned_summary['pending'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Delivered</h6>
                                <p class="display-6 mb-0"><?= $assigned_summary['delivered'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="card-title">Delayed</h6>
                                <p class="display-6 mb-0"><?= $assigned_summary['delayed'] ?? 0 ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Assigned Orders by Status</h5>
                        <div class="row g-3">
                            <?php foreach (['Pending','Confirmed','Assigned','Out for Delivery','Picked','In Transit','Delivered','Completed'] as $status): ?>
                                <div class="col-6 col-md-3 mb-2">
                                    <span class="badge bg-secondary"><?= $status ?>: <?= $orders_status_counts[$status] ?? 0 ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
if (document.getElementById('dailyOrdersChart')) {
    const dailyOrdersData = {
        labels: <?= json_encode(array_column($daily_orders, 'day')) ?>,
        datasets: [{
            label: 'Orders per Day',
            data: <?= json_encode(array_column($daily_orders, 'count')) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            fill: true,
            tension: 0.4
        }]
    };
    new Chart(document.getElementById('dailyOrdersChart'), {
        type: 'line',
        data: dailyOrdersData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
</script>

<?php include 'includes/footer.php';