<?php
// home.php — DS4Y Registration Home Screen
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DS4Y Registration Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=2">
    <style>
        body.home-bg {
            background-image: url("homepage.jpg");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        /* lighten portal card so text remains readable */
        .portal-card { background: rgba(255,255,255,0.92); }
    </style>
</head>
<body class="home-bg">
    <header class="app-header">
        <div class="header-container">
            <a href="home.php" class="logo-area" style="text-decoration:none;color:inherit;">
                <img src="download.jpeg" alt="Impact Hub Harare" class="logo-img">
                <div class="logo-text">
                    <span class="hub-title">IMPACT HUB</span>
                    <span class="hub-subtitle">Harare | DS4Y Portal</span>
                </div>
            </a>

            <nav class="main-nav" id="mainNav">
                            <a href="index.php" class="nav-link"><i class="fa-solid fa-pen-to-square"></i> Daily Register</a>
                <a href="registration.php" class="nav-link"><i class="fa-solid fa-check-to-slot"></i> DS4Y Registration</a>
                <a href="login.php" class="nav-link"><i class="fa-solid fa-lock"></i> Staff Login</a>
            </nav>
        </div>
    </header>

    <?php $current_page = 'home'; ?>
    <main class="app-main">
        <div class="portal-card" style="max-width:760px;">
            <div class="portal-title-area">
                <h2>Welcome to the DS4Y Registration Portal</h2>
                <p class="login-sub">Use the links above to manage enrolment and attendance.</p>
            </div>

            <div style="display:flex;gap:1rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
                            <a href="index.php" class="btn btn-crimson">Daily Register</a>
                <a href="registration.php" class="btn btn-crimson">DS4Y Registration</a>
                <a href="login.php" class="btn btn-crimson">Staff Login</a>
            </div>
        </div>
    </main>

</body>
</html>
