<?php
// users.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* Original Permission Code
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php'); exit;
}
*/

// Admin-only access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_msg'] = 'Access denied. Only administrators may view this page.';
    header('Location: access_denied.php'); exit;
}

$success_msg = $_SESSION['user_success'] ?? '';
$error_msg   = $_SESSION['user_error']   ?? '';
unset($_SESSION['user_success'], $_SESSION['user_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $_SESSION['user_error'] = "Security check failed.";
        header("Location: users.php"); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['staff','admin']) ? $_POST['role'] : 'staff';

        // Check for username character constraints (alphanumeric and underscores/hyphens only)
        if (empty($username) || empty($password)) {
            $_SESSION['user_error'] = "All fields are required.";
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $_SESSION['user_error'] = "Username can only contain letters, numbers, underscores, and hyphens.";
        } elseif (strlen($password) < 8 || 
                  !preg_match('/[A-Z]/', $password) || 
                  !preg_match('/[a-z]/', $password) || 
                  !preg_match('/[0-9]/', $password) || 
                  !preg_match('/[^A-Za-z0-9]/', $password)) {
            $_SESSION['user_error'] = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
        } else {
            try {
                $check = $db->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $_SESSION['user_error'] = "Username already taken.";
                } else {
                    $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
                       ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                    $_SESSION['user_success'] = "Account created for \"$username\".";
                }
            } catch (PDOException $e) {
                $_SESSION['user_error'] = "Failed to create account: " . $e->getMessage();
            }
        }
        header("Location: users.php"); exit;
    }

    if ($action === 'delete_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id === intval($_SESSION['user_id'])) {
            $_SESSION['user_error'] = "You cannot delete your own account.";
        } else {
            try {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                $_SESSION['user_success'] = "Account deleted.";
            } catch (PDOException $e) {
                $_SESSION['user_error'] = "Delete failed: " . $e->getMessage();
            }
        }
        header("Location: users.php"); exit;
    }
}

try {
    $user_list = $db->query("SELECT id, username, role FROM users ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $user_list = [];
    $error_msg = "Failed to retrieve user list: " . $e->getMessage();
}

$current_page = 'users';
require_once __DIR__ . '/header.php';
?>

<div class="dashboard-header" style="margin-bottom:2.5rem;">
    <div>
        <h1>Staff Management</h1>
        <p>Add and manage credentials for Impact Hub Harare staff</p>
    </div>
    <div class="dashboard-actions">
        <a href="dashboard.php" class="btn btn-outline"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($success_msg) ?></span></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($error_msg) ?></span></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(350px,1fr));gap:2rem;align-items:start;">

    <div class="portal-card" style="margin:0;max-width:100%;padding:2rem;border-radius:var(--border-radius);">
        <h2 style="font-size:1.4rem;margin-bottom:1.5rem;">
            <i class="fa-solid fa-user-plus" style="color:var(--crimson);margin-right:.5rem;"></i> Create Staff Account
        </h2>
        <form action="users.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action"     value="add_user">

            <div class="form-group">
                <label for="username"><i class="fa-solid fa-user-tag"></i> Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required>
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-key"></i> Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Min 8 chars, mixed case, number, symbol" required>
            </div>
            <div class="form-group">
                <label for="role"><i class="fa-solid fa-user-shield"></i> Access Role</label>
                <select name="role" id="role" class="form-control" style="background-color:var(--card-bg);">
                    <option value="staff">Staff (Limited Access)</option>
                    <option value="admin">Administrator (Full Control)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-crimson" style="width:100%;margin-top:1rem;padding:.8rem;">
                <i class="fa-solid fa-plus"></i> Create Account
            </button>
        </form>
    </div>

    <div class="table-container" style="box-shadow:var(--shadow);">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($user_list as $u): ?>
                <tr>
                    <td>
                        <strong><i class="fa-solid fa-user-large" style="margin-right:.5rem;color:var(--text-secondary);"></i>
                        <?= htmlspecialchars($u['username']) ?></strong>
                        <?php if ($u['id'] === intval($_SESSION['user_id'])): ?>
                            <span class="badge badge-success" style="margin-left:.4rem;">You</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge badge-success">Administrator</span>
                        <?php else: ?>
                            <span class="badge badge-warning" style="background-color:rgba(162,28,38,.15);color:var(--crimson);border-color:rgba(162,28,38,.3);">Staff</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if ($u['id'] !== intval($_SESSION['user_id'])): ?>
                            <a href="users_edit.php?id=<?= intval($u['id']) ?>" class="btn btn-outline btn-sm" style="margin-right:.5rem;"><i class="fa-solid fa-pen"></i> Edit</a>
                        <form action="users.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete account for <?= addslashes(htmlspecialchars($u['username'])) ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action"    value="delete_user">
                            <input type="hidden" name="user_id"   value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm"
                                style="color:#ef4444;border-color:rgba(239,68,68,.2);padding:.45rem .6rem;font-size:.85rem;">
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:.8rem;color:var(--text-secondary);font-style:italic;">Active Session</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
