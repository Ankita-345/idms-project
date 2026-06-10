<?php
session_start();

// If user is already logged in, redirect to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'KRYSTAL CUBE - Always Fresh, Always Pure.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <!-- Replace with your logo -->
<img src="assets/images/krystal-cube-logo.jpeg" alt="KRYSTAL CUBE" style="height: 55px; width: auto;">            </a>
            <div class="ms-auto">
                <a href="login.php" class="btn btn-outline-light">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-white text-center">
        <div class="container">
            <div class="hero-content">
<img src="assets/images/krystal-cube-logo.jpeg" alt="KRYSTAL CUBE" class="hero-logo mb-4">                <h1 class="display-3">KRYSTAL CUBE</h1>
                <p class="lead">Always Fresh, Always Pure.</p>
            </div>
        </div>
    </header>

    <!-- Services Section -->
    <section class="services-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Products</h2>
            <div class="row text-center">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="icon-circle mx-auto mb-3">
                                <i class="bi bi-box"></i>
                            </div>
                            <h5 class="card-title">Ice Cubes</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="icon-circle mx-auto mb-3">
                                <i class="bi bi-app"></i>
                            </div>
                            <h5 class="card-title">Block Ice</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="icon-circle mx-auto mb-3">
                                <i class="bi bi-snow"></i>
                            </div>
                            <h5 class="card-title">Dry Ice</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="icon-circle mx-auto mb-3">
                                <i class="bi bi-truck"></i>
                            </div>
                            <h5 class="card-title">Bulk Supply</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-choose-us-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose Us</h2>
            <div class="row">
                <div class="col-md-3 text-center mb-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-gem"></i>
                    </div>
                    <h4>Pure Quality</h4>
                    <p class="text-muted">Our ice is made from purified water, ensuring the highest quality and clarity.</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <h4>Fast Delivery</h4>
                    <p class="text-muted">We provide prompt and efficient delivery services to meet your needs.</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4>Reliable Service</h4>
                    <p class="text-muted">Count on us for consistent and dependable ice supply for your business.</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <h4>Bulk Orders</h4>
                    <p class="text-muted">We cater to large-volume orders for events, restaurants, and commercial use.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-section text-white text-center py-4">
        <div class="container">
            <h5>KRYSTAL CUBE</h5>
            <p class="mb-0">Always Fresh, Always Pure.</p>
            <p class="mt-2 mb-0 text-white-50">&copy; <?= date('Y') ?> KRYSTAL CUBE. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>