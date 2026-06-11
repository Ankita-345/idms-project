<?php
$role = $_SESSION['role'] ?? 'Client';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" id="dashboardSidebar">
        <div class="sidebar-inner">
        <div class="sidebar-menu flex-grow-1 px-3 pt-4 pb-4">
            <ul class="nav flex-column mb-0">
                <li class="nav-item mb-1">
                    <a class="nav-link sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 sidebar-icon"></i><span class="sidebar-text">Dashboard</span></a>
                </li>
                <?php if ($role === 'Client'): ?>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'add-order.php' ? 'active' : '' ?>" href="add-order.php"><i class="bi bi-cart-plus sidebar-icon"></i><span class="sidebar-text">Place Order</span></a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-card-list sidebar-icon"></i><span class="sidebar-text">My Orders</span></a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'Admin'): ?>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'clients.php' ? 'active' : '' ?>" href="clients.php"><i class="bi bi-people sidebar-icon"></i><span class="sidebar-text">Clients</span></a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'add-admin.php' ? 'active' : '' ?>" href="add-admin.php"><i class="bi bi-person-plus sidebar-icon"></i><span class="sidebar-text">Add Admin</span></a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-bag-check sidebar-icon"></i><span class="sidebar-text">Orders</span></a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'delivery-teams.php' ? 'active' : '' ?>" href="delivery-teams.php"><i class="bi bi-truck sidebar-icon"></i><span class="sidebar-text">Delivery Teams</span></a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-graph-up sidebar-icon"></i><span class="sidebar-text">Reports</span></a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= in_array($currentPage, ['inventory.php','add-stock.php','update-stock.php']) ? 'active' : '' ?>" href="inventory.php"><i class="bi bi-box-seam sidebar-icon"></i><span class="sidebar-text">Inventory</span></a>
                    </li>
                <?php endif; ?>
                <?php if ($role === 'Delivery'): ?>
                    <li class="nav-item mb-1">
                        <a class="nav-link sidebar-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>" href="orders.php"><i class="bi bi-truck-flatbed sidebar-icon"></i><span class="sidebar-text">Assigned Orders</span></a>
                    </li>
                <?php endif; ?>
                <li class="nav-item mb-1">
                    <a class="nav-link sidebar-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="bi bi-person-circle sidebar-icon"></i><span class="sidebar-text">Profile</span></a>
                </li>
            </ul>
        </div>
    </div>
</nav>