<?php
// header.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DS4Y Attendance Portal – Impact Hub Harare</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=2">
    <style>
        /* Page-specific background helpers */
        body.register-bg {
            background-image: url("registerpage.jpg");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        body.enroll-bg {
            background-image: url("enrollmentpage.jpg");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        /* Keep portal cards readable on image backgrounds */
        body.register-bg .portal-card,
        body.enroll-bg .portal-card {
            background: rgba(255,255,255,0.92);
        }
    </style>
</head>
<body<?= isset($body_class) ? ' class="'.htmlspecialchars($body_class).'"' : '' ?>>
    <header class="app-header">
        <div class="header-container">
            <a href="home.php" class="logo-area" style="text-decoration:none;color:inherit;">
                <img src="download.jpeg" alt="Impact Hub Harare" class="logo-img">
                <div class="logo-text">
                    <span class="hub-title">IMPACT HUB</span>
                    <span class="hub-subtitle">Harare | DS4Y Portal</span>
                </div>
            </a>

            <!-- Hamburger toggle (mobile only) -->
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fa-solid fa-bars" id="navIcon"></i>
            </button>

            <nav class="main-nav" id="mainNav">
                <?php
                // $current_page is set in each PHP file before require_once header.php
                // e.g. $current_page = 'home'; or $current_page = 'index';
                $current_page = $current_page ?? '';

                // Helper: returns 'nav-link active' if this is the current page
                function nav_class(string $page, string $current): string {
                    return 'nav-link' . ($current === $page ? ' active' : '');
                }
                ?>

                <a href="index.php" class="<?= nav_class('index', $current_page) ?>">
                    <i class="fa-solid fa-pen-to-square"></i> Daily Register
                </a>

                <?php if ($current_page === 'home'): ?>
                    <a href="registration.php" class="<?= nav_class('registration', $current_page) ?>">
                        <i class="fa-solid fa-check-to-slot"></i> DS4Y Registration
                    </a>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="<?= nav_class('dashboard', $current_page) ?>">
                        <i class="fa-solid fa-chart-line"></i> Dashboard
                    </a>
                    <a href="archive.php" class="<?= nav_class('archive', $current_page) ?>">
                        <i class="fa-solid fa-box-archive"></i> Archive
                    </a>
                    <a href="account.php" class="<?= nav_class('account', $current_page) ?>">
                        <i class="fa-solid fa-user-cog"></i> My Account
                    </a>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <a href="enrolled.php" class="<?= nav_class('enrolled', $current_page) ?>">
                            <i class="fa-solid fa-user"></i> Enrolled Students
                        </a>
                        <a href="users.php" class="<?= nav_class('users', $current_page) ?>">
                            <i class="fa-solid fa-users-gear"></i> Manage Staff
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-crimson">
                        <i class="fa-solid fa-lock"></i> Staff Login
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="app-main">
