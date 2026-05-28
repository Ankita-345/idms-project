<?php
$role = $_SESSION['role'] ?? 'Client';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse show" id="dashboardSidebar">
    <div class="sidebar-inner">
        <div class="sidebar-menu flex-grow-1 px-3 pt-4 pb-4">
            <ul class="nav flex-column mb-0">
                <li class="nav-item mb-1">
                    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a>
                </li>
                <?php if ($role === 'Client'): ?>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'add-order.php' ? 'active' : '' ?>" href="add-order.php"><i class="bi bi-cart-plus"></i>Place Order</a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-card-list"></i>My Orders</a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'Admin'): ?>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'clients.php' ? 'active' : '' ?>" href="clients.php"><i class="bi bi-people"></i>Clients</a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'add-admin.php' ? 'active' : '' ?>" href="add-admin.php"><i class="bi bi-person-plus"></i>Add Admin</a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-bag-check"></i>Orders</a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'delivery-teams.php' ? 'active' : '' ?>" href="delivery-teams.php"><i class="bi bi-truck"></i>Delivery Teams</a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-graph-up"></i>Reports</a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= in_array($currentPage, ['inventory.php','add-stock.php','update-stock.php']) ? 'active' : '' ?>" href="inventory.php"><i class="bi bi-box-seam"></i>Inventory</a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'Delivery'): ?>
                    <li class="nav-item mb-1">
                        <a class="nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-truck-flatbed"></i>Assigned Orders</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item mb-1">
                    <a class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="bi bi-person-circle"></i>Profile</a>
                </li>
            </ul>
        </div>
    </div>
</nav>