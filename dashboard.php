<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Dashboard - IDMS';
include 'includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

<main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-3 px-md-4 py-4">   
<div class="d-flex justify-content-between flex-wrap align-items-center gap-2 pb-2 mb-3 border-bottom">                <div>
                    <h1 class="h2">Dashboard</h1>
                    <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>.</p>
                </div>
            </div>

            <!-- Role-specific quick actions -->
            <?php $role = $_SESSION['role'] ?? ''; ?>
            <?php if ($role === 'Client'): ?>
                <?php
                // Resolve client id for this user if possible
                $client_id = null;
                if (!empty($_SESSION['email'])) {
                    $cstmt = mysqli_prepare($conn, 'SELECT id FROM clients WHERE email = ? LIMIT 1');
                    mysqli_stmt_bind_param($cstmt, 's', $_SESSION['email']);
                    mysqli_stmt_execute($cstmt);
                    $cres = mysqli_stmt_get_result($cstmt);
                    if ($cres && mysqli_num_rows($cres) === 1) {
                        $crow = mysqli_fetch_assoc($cres);
                        $client_id = (int)$crow['id'];
                    }
                    mysqli_stmt_close($cstmt);
                }
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <a class="card text-decoration-none text-dark h-100" href="add-order.php">
                            <div class="card-body">
                                <h5 class="card-title">Place Order</h5>
                                <p class="card-text">Create a new delivery request.</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <a class="card text-decoration-none text-dark h-100" href="orders.php">
                            <div class="card-body">
                                <h5 class="card-title">My Orders</h5>
                                <p class="card-text">View and track your orders.</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Order Status</h5>
                                <?php if ($client_id):
                                    $sstmt = mysqli_prepare($conn, 'SELECT status, COUNT(*) as cnt FROM orders WHERE client_id = ? GROUP BY status');
                                    mysqli_stmt_bind_param($sstmt, 'i', $client_id);
                                    mysqli_stmt_execute($sstmt);
                                    $sres = mysqli_stmt_get_result($sstmt);
                                    $status_counts = [];
                                    while ($r = mysqli_fetch_assoc($sres)) { $status_counts[$r['status']] = $r['cnt']; }
                                    mysqli_stmt_close($sstmt);
                                ?>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <?php foreach (['Pending','Confirmed','Assigned','Out for Delivery','Delivered','Completed','Cancelled','Failed'] as $st): ?>
                                            <?php
                                                $count = $status_counts[$st] ?? 0;
                                                $color = 'secondary';
                                                if ($st === 'Pending') { $color = 'warning'; }
                                                elseif ($st === 'Confirmed') { $color = 'info'; }
                                                elseif ($st === 'Assigned') { $color = 'primary'; }
                                                elseif ($st === 'Out for Delivery') { $color = 'secondary'; }
                                                elseif ($st === 'Delivered') { $color = 'success'; }
                                                elseif ($st === 'Completed') { $color = 'dark'; }
                                                elseif ($st === 'Cancelled' || $st === 'Failed') { $color = 'danger'; }
                                            ?>
                                            <span class="badge bg-<?= $color ?> rounded-pill py-2 px-3">
                                                <?= htmlspecialchars($st) ?>: <?= $count ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No client record found for your account. Place an order to create your client profile.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Manager' || $role === 'Operations Manager' || $role === 'Admin'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <a class="card text-decoration-none text-dark h-100" href="clients.php">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title">Clients</h5>
                                    <p class="card-text mb-0">Manage customer accounts and relationships.</p>
                                </div>
                                <i class="bi bi-people fs-2 text-primary"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <a class="card text-decoration-none text-dark h-100" href="orders.php">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title">Orders</h5>
                                    <p class="card-text mb-0">Review order pipeline and status.</p>
                                </div>
                                <i class="bi bi-bag-check fs-2 text-info"></i>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <a class="card text-decoration-none text-dark h-100" href="reports.php">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="card-title">Reports</h5>
                                    <p class="card-text mb-0">Track performance and delivery metrics.</p>
                                </div>
                                <i class="bi bi-graph-up fs-2 text-success"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <?php
                // Show dashboard analytics for managers/admins
                $summary = [];
                $summary['total_orders'] = (int)mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS cnt FROM orders'))['cnt'];
                $summary['pending_orders'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE status = 'Pending'"))['cnt'];
                $summary['delivered_orders'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE status IN ('Delivered','Completed')"))['cnt'];
                $summary['total_clients'] = (int)mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS cnt FROM clients'))['cnt'];
                $summary['total_teams'] = (int)mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS cnt FROM delivery_teams'))['cnt'];
                $summary['available_inventory'] = (int)mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COALESCE(SUM(quantity),0) AS cnt FROM inventory'))['cnt'];
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <p class="text-muted mb-2">Total Orders</p>
                                    <h3 class="mb-0"><?= $summary['total_orders'] ?></h3>
                                </div>
                                <div class="rounded-3 p-3 text-primary bg-primary bg-opacity-10">
                                    <i class="bi bi-basket fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <p class="text-muted mb-2">Pending Orders</p>
                                    <h3 class="mb-0"><?= $summary['pending_orders'] ?></h3>
                                </div>
                                <div class="rounded-3 p-3 text-warning bg-warning bg-opacity-10">
                                    <i class="bi bi-hourglass-split fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <p class="text-muted mb-2">Delivered Orders</p>
                                    <h3 class="mb-0"><?= $summary['delivered_orders'] ?></h3>
                                </div>
                                <div class="rounded-3 p-3 text-success bg-success bg-opacity-10">
                                    <i class="bi bi-check2-circle fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <?php
                // Show recent clients for managers
                $clients_preview = [];
                $c_stmt = mysqli_prepare($conn, 'SELECT id, company_name, email, phone, status FROM clients ORDER BY created_at DESC LIMIT 10');
                if ($c_stmt) {
                    mysqli_stmt_execute($c_stmt);
                    $cres = mysqli_stmt_get_result($c_stmt);
                    $clients_preview = mysqli_fetch_all($cres, MYSQLI_ASSOC);
                    mysqli_stmt_close($c_stmt);
                }
                ?>
                <div class="card table-wrapper mb-4">
                    <div class="card-body">
                        <h5 class="table-title"><i class="bi bi-people-fill me-2"></i>Recent Clients</h5>
                        <?php if (!empty($clients_preview)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients_preview as $cp): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($cp['company_name']) ?></td>
                                                <td><small><?= htmlspecialchars($cp['email'] ?? '') ?></small></td>
                                                <td><?= htmlspecialchars($cp['phone'] ?? '') ?></td>
                                                <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $cp['status'])) ?>"><?= htmlspecialchars($cp['status']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No clients found yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card border-primary shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">Your Role</h5>
                            <p class="card-text display-6"><?= htmlspecialchars($_SESSION['role']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card border-success shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">Account</h5>
                            <p class="card-text"><?= htmlspecialchars($_SESSION['email']) ?></p>
                        </div>
                    </div>
                </div>

            </div>


        </main>
    </div>
</div>
<?php include 'includes/footer.php';