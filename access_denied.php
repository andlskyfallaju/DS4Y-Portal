<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

http_response_code(403);

require_once __DIR__ . '/header.php';

$msg = $_SESSION['error_msg'] ?? 'Access denied.';
unset($_SESSION['error_msg']);
?>

<div class="portal-card" style="max-width:720px; text-align:center;">
    <div class="portal-title-area">
        <h2>Access Denied</h2>
        <p class="login-sub">You do not have permission to view this page.</p>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($msg) ?></div>
    <?php endif; ?>

    <div style="display:flex;gap:1rem;justify-content:center;margin-top:1.25rem;">
        <a href="home.php" class="btn btn-outline">Home</a>
        <a href="login.php" class="btn btn-crimson">Staff Login</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
