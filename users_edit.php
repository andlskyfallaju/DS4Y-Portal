<?php
// users_edit.php — Edit a user account (admin-only)
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_msg'] = 'Access denied.';
    header('Location: access_denied.php'); exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: users.php'); exit; }

$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $flash_err = 'Security check failed.'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_role') {
            $role = in_array($_POST['role'] ?? '', ['staff','admin']) ? $_POST['role'] : 'staff';
            try { $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]); $flash_ok = 'Role updated.'; }
            catch (PDOException $e) { $flash_err = 'Update failed: ' . $e->getMessage(); }
        } elseif ($action === 'reset_password') {
            $new = trim($_POST['new_password'] ?? '');
            if (strlen($new) < 6) { $flash_err = 'Password must be at least 6 characters.'; }
            else {
                try { $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $id]); $flash_ok = 'Password reset.'; }
                catch (PDOException $e) { $flash_err = 'Reset failed: ' . $e->getMessage(); }
            }
        } elseif ($action === 'update_name') {
            $full = trim($_POST['full_name'] ?? '');
            try { $db->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$full ?: null, $id]); $flash_ok = 'Name updated.'; }
            catch (PDOException $e) { $flash_err = 'Update failed: ' . $e->getMessage(); }
        }
    }
}

// load user
$stmt = $db->prepare('SELECT id, username, full_name, role FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { header('Location: users.php'); exit; }

$current_page = 'users';
require_once __DIR__ . '/header.php';
?>

<div class="portal-card" style="max-width:720px;">
    <div class="portal-title-area"><h2>Edit User</h2><p class="login-sub">Manage account role and password</p></div>

    <?php if ($flash_ok): ?><div class="alert alert-success"><?= h($flash_ok) ?></div><?php endif; ?>
    <?php if ($flash_err): ?><div class="alert alert-danger"><?= h($flash_err) ?></div><?php endif; ?>

    <form method="POST" action="users_edit.php?id=<?= intval($id) ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
        </div>
        <div class="form-group">
            <label>Full name</label>
            <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name']) ?>">
            <button type="submit" name="action" value="update_name" class="btn btn-outline" style="margin-top:.5rem;">Save name</button>
        </div>

        <hr>
        <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control" style="max-width:220px;">
                <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <button type="submit" name="action" value="update_role" class="btn btn-crimson" style="margin-top:.5rem;">Update role</button>
        </div>

        <hr>
        <div class="form-group">
            <label>Reset password</label>
            <input type="password" name="new_password" class="form-control" placeholder="New password">
            <button type="submit" name="action" value="reset_password" class="btn btn-outline" style="margin-top:.5rem;">Reset password</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
