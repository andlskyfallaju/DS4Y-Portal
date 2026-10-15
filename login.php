<?php
// login.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit;
}

$error_msg = '';

// Brute-force protection settings
$max_attempts = 5; // Max failed attempts
$lockout_time = 300; // Lockout duration in seconds (5 minutes)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = "Security check failed. Please refresh and try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // Initialize session lockout variables
        if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
        if (!isset($_SESSION['lockout_until'])) $_SESSION['lockout_until'] = [];

        // Check for active lockout for this user
        if (isset($_SESSION['lockout_until'][$username]) && $_SESSION['lockout_until'][$username] > time()) {
            $remaining_time = $_SESSION['lockout_until'][$username] - time();
            $error_msg = "Too many failed attempts. Account locked for $remaining_time seconds.";
            sleep(2); // Further slow down attempts on locked accounts
        } elseif (empty($username) || empty($password)) {
            $error_msg = "Please enter both username and password.";
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Prevent session fixation by regenerating session ID on login
                    session_regenerate_id(true);

                    // Successful login: reset attempts
                    unset($_SESSION['login_attempts'][$username]);
                    unset($_SESSION['lockout_until'][$username]);

                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['last_activity'] = time(); // Reset inactivity timer
                    $_SESSION['created_time']  = time(); // Initialize regeneration timer
                    header('Location: dashboard.php'); exit;
                } else {
                    // Failed login: increment attempts
                    $_SESSION['login_attempts'][$username] = ($_SESSION['login_attempts'][$username] ?? 0) + 1;

                    if ($_SESSION['login_attempts'][$username] >= $max_attempts) {
                        $_SESSION['lockout_until'][$username] = time() + $lockout_time;
                        $error_msg = "Too many failed attempts. Account locked for $lockout_time seconds.";
                    } else {
                        $error_msg = "Invalid username or password.";
                    }
                    sleep(1); // always slow brute-force
                }
            } catch (PDOException $e) {
                $error_msg = "An error occurred. Please try again.";
                error_log("Login PDOException: " . $e->getMessage()); // Log error for debugging
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login – DS4Y | Impact Hub Harare</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=2">
    <style>
        body.login-body {
            background-image: url("loginpage.jpg");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        body.login-body .login-card { background: rgba(255,255,255,0.95); }
    </style>
</head>
<body class="login-body">

<div class="login-wrap">
    <div class="login-card">

        <!-- Header band -->
        <div class="login-header">
            <img src="download.jpeg" alt="Impact Hub Harare" class="login-logo">
            <div class="login-header-text">
                <span class="hub-title">IMPACT HUB</span>
                <span class="hub-subtitle">Harare | DS4Y Portal</span>
            </div>
        </div>

        <div class="login-body-inner">
            <div class="login-lock-ring">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="login-title">Staff Sign In</h2>
            <p class="login-sub">Enter your credentials to access the dashboard</p>

            <?php if (!empty($_SESSION['first_run_password'])): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-info"></i>
        <div>
            <strong>First run detected.</strong> Your temporary admin password is:<br>
            <code style="display:block;margin-top:.5rem;font-size:1.1rem;letter-spacing:.15em;font-weight:700;">
                <?= $_SESSION['first_run_password'] ?>
            </code>
            Copy it now — it will not be shown again. Log in and change it via My Account.
        </div>
    </div>
    <?php unset($_SESSION['first_run_password']); ?>
                <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-group">
                    <label for="username"><i class="fa-solid fa-user-shield"></i> Username</label>
                    <input type="text" name="username" id="username" class="form-control"
                        placeholder="Enter username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required autofocus>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-key"></i> Password</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Enter password" required>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
                            <i class="fa-solid fa-eye" id="pwIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-crimson">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </button>
                </div>
            </form>

            <div class="login-back-link">
                <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to student registration</a>
            </div>
        </div>
    </div>
</div>

<script>
const pwToggle = document.getElementById('pwToggle');
const pwInput  = document.getElementById('password');
const pwIcon   = document.getElementById('pwIcon');
if (pwToggle) {
    pwToggle.addEventListener('click', () => {
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        pwIcon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    });
}
</script>
</body>
</html>
