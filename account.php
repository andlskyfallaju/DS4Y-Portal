<?php
// account.php — user profile & password change
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = 'Please log in to manage your account.';
    header('Location: login.php'); exit;
}

$user_id = intval($_SESSION['user_id']);
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $flash_err = 'Security check failed.'; }
    else {
        // Update full name
        if (isset($_POST['full_name'])) {
            $full = trim($_POST['full_name']);
            try {
                $db->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$full ?: null, $user_id]);
                $flash_ok = 'Profile updated.';
            } catch (PDOException $e) { $flash_err = 'Update failed: ' . $e->getMessage(); }
        }

        // Change password
        if (!empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (empty($current) || empty($new) || empty($confirm)) {
                $flash_err = 'Fill all password fields to change password.';
            } elseif ($new !== $confirm) {
                $flash_err = 'New passwords do not match.';
            } else {
                try {
                    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
                    $stmt->execute([$user_id]);
                    $row = $stmt->fetch();
                    if (!$row || !password_verify($current, $row['password'])) {
                        $flash_err = 'Current password is incorrect.';
                    } else {
                        $hash = password_hash($new, PASSWORD_DEFAULT);
                        $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user_id]);
                        $flash_ok = $flash_ok ? $flash_ok . ' Password changed.' : 'Password changed.';
                    }
                } catch (PDOException $e) { $flash_err = 'Password update failed.'; }
            }
        }
    }
}

// load user
$stmt = $db->prepare('SELECT username, full_name FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) { $_SESSION['error_msg']='User not found.'; header('Location: login.php'); exit; }

require_once __DIR__ . '/header.php';
?>

<div class="portal-card" style="max-width:760px;">
    <div class="portal-title-area"><h2>My Account</h2><p class="login-sub">Update your profile and password</p></div>

    <?php if ($flash_ok): ?><div class="alert alert-success"><?= h($flash_ok) ?></div><?php endif; ?>
    <?php if ($flash_err): ?><div class="alert alert-danger"><?= h($flash_err) ?></div><?php endif; ?>

    <form method="POST" action="account.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
        </div>
        <div class="form-group">
            <label>Full name</label>
            <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name']) ?>">
        </div>

        <hr>
        <div class="form-group"><label>Change password</label></div>
        <div class="form-group"><input type="password" name="current_password" class="form-control" placeholder="Current password"></div>
        <div class="form-group"><input type="password" name="new_password" class="form-control" placeholder="New password"></div>
        <div class="form-group"><input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password"></div>

        <div class="form-actions"><button type="submit" class="btn btn-crimson">Save changes</button></div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
