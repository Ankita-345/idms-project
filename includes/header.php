<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'Ice Distribution Management System';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --body-bg: #f1f8ff;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            min-height: 100vh;
            background-color: var(--body-bg);
            color: var(--text-dark);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-bottom: 1px solid rgba(148,163,184,.18);
        }
        .navbar-brand {
            font-weight: 700;
            color: #f8fafc;
        }
        .sidebar {
            /* background: rgba(240, 248, 255, 0.15);
            backdrop-filter: blur(12px); */
    background: linear-gradient(180deg, #111827 0%, #0f172a 55%, #020617 100%);
    position: sticky;
    top: 56px;
    height: calc(100vh - 56px);
    overflow-y: hidden !important;
    border-right: 1px solid rgba(148,163,184,.18);

    min-width: 210px !important;
    width: 210px !important;
    overflow-x: hidden !important;
}
    .sidebar::-webkit-scrollbar {
    display: none;
}
.sidebar .nav-link {
    color: #cbd5e1;
    padding: 12px 16px;
    border-radius: 12px;
    transition: background .2s ease, color .2s ease;
    font-weight: 500;
    margin: 0 10px 8px 10px;

    display: flex;
    align-items: center;
    gap: 12px;
}   /* .sidebar .nav-link {
    color: #cbd5e1;
    padding: .85rem 1.25rem;
    border-radius: 12px;
    transition: background .2s ease, color .2s ease;
    font-weight: 500;
    margin: 0 .5rem;

    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    white-space: nowrap !important;
    /* max-width: calc(100% - 16px) !important;
    box-sizing: border-box !important; */
/* } */ 
        .sidebar .nav-link .bi {
    color: #93c5fd;
    transition: color .2s ease;

    width: 22px !important;
    min-width: 22px !important;
    margin-right: 0 !important;
    text-align: center !important;
}
        .sidebar .nav-link:hover {
             /* background: rgba(135, 206, 235, 0.3); /* Sky blue ice highlight */
                /* color: #ffffff; */ 
             color: #ffffff; 
             background-color: rgba(56,189,248,.12); 
        }
        .sidebar .nav-link:hover .bi {
            color: #38bdf8;
        }
        .sidebar .nav-link.active {
            color: #ffffff;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            /* box-shadow: inset 0 0 0 1px rgba(255,255,255,.08) !important; */
            /* box-shadow: 0 10px 25px rgba(14,165,233,.25); */
        }
        .sidebar .nav-link.active .bi {
            color: #ffffff;
        }
        .card {
            border: none;
            border-radius: .75rem;
            background-color: var(--card-bg);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        .main-content {
            padding-top: 1.5rem;
        }
        .modern-table th:last-child,
        .modern-table td:last-child {
    width: 220px;
}

.modern-table th:nth-child(10),
.modern-table td:nth-child(10) {
    width: 120px;
}
@media (max-width: 767.98px) {
    .sidebar {
        position: fixed !important;
        top: 56px !important;
        left: 0;
        z-index: 1040;
        height: calc(100vh - 56px) !important;
        width: 240px !important;
        min-width: 240px !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        box-shadow: 8px 0 24px rgba(15, 23, 42, 0.25);
    }

    .sidebar.collapse:not(.show) {
        display: none !important;
    }

    .sidebar.collapse.show {
        display: block !important;
    }

    main {
        width: 100% !important;
        margin-left: 0 !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }

    .container-fluid {
        overflow-x: hidden;
    }

    .card {
        border-radius: 14px;
    }
}

@media (min-width: 768px) {
    .sidebar.collapse {
        display: block !important;
    }
}

    </style>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid">
        <?php if (!empty($_SESSION['user_id'])): ?>
    <button class="btn btn-outline-light d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-expanded="false" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>

<?php endif; ?>
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <span class="me-2"><i class="bi bi-snow"></i></span>
            <span>IDMS</span>
        </a>
        <?php if (empty($_SESSION['user_id'])): ?>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
</button>
<?php endif; ?>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (!empty($_SESSION['user_id'])):
                    // Ensure notification functions are loaded
                    if (file_exists(__DIR__ . '/notify.php')) {
                        require_once __DIR__ . '/notify.php';
                        $nav_user_id = $_SESSION['user_id'] ?? 0;
                        $nav_role = $_SESSION['role'] ?? '';
                        $unread_count = get_unread_count($conn, $nav_user_id, $nav_role);
                    } else {
                        $unread_count = 0;
                    }
                    ?>
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger rounded-pill position-absolute" style="top:.2rem; right:.2rem; font-size:.65rem;"><?= htmlspecialchars($unread_count) ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown">
                           <li><a class="dropdown-item text-center" href="notifications.php">View all notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown profile-menu">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid-fill me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-fill me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php if (function_exists('display_flash_messages')): ?>
    <?php display_flash_messages(); ?>
<?php endif; ?>