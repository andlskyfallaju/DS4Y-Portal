<?php
// dashboard.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$flash_ok    = $_SESSION['update_success'] ?? '';
$flash_error = $_SESSION['update_error']   ?? '';
unset($_SESSION['update_success'], $_SESSION['update_error']);

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $_SESSION['update_error'] = "Security check failed.";
        header("Location: dashboard.php"); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_returned') {
        $reg_id      = intval($_POST['reg_id'] ?? 0);
        $returned_to = trim($_POST['returned_to'] ?? '');
        if (empty($returned_to)) {
            $_SESSION['update_error'] = "Receiver name cannot be empty.";
        } else {
            try {
                $db->prepare("UPDATE registrations SET returned_to = ?, checkout_at = datetime('now','localtime') WHERE id = ?")
                   ->execute([$returned_to, $reg_id]);
                $_SESSION['update_success'] = "Laptop return recorded.";
            } catch (PDOException $e) {
                $_SESSION['update_error'] = "Update failed: " . $e->getMessage();
            }
        }
        header("Location: dashboard.php"); exit;
    }

    if ($action === 'delete_registration') {
        $reg_id = intval($_POST['reg_id'] ?? 0);
        try {
            $db->prepare("DELETE FROM registrations WHERE id = ?")->execute([$reg_id]);
            $_SESSION['update_success'] = "Record deleted.";
        } catch (PDOException $e) {
            $_SESSION['update_error'] = "Delete failed: " . $e->getMessage();
        }
        header("Location: dashboard.php"); exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$date_filter = trim($_GET['date']   ?? date('Y-m-d')); // default today

// ── Query ────────────────────────────────────────────────────────────────────
try {
    $params = [];
    $where  = ["date(created_at, 'localtime') = ?"];
    $params[] = $date_filter;

    if (!empty($search)) {
        $where[]  = "(name LIKE ? OR surname LIKE ? OR laptop_number LIKE ? OR given_by LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $sql = "SELECT id, name, surname, laptop_number, given_by, returned_to,
                   datetime(created_at, 'localtime') as created_at, checkout_at
            FROM registrations
            WHERE " . implode(" AND ", $where) . "
            ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll();
} catch (PDOException $e) {
    $registrations = [];
    $flash_error = "Failed to load data: " . $e->getMessage();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
try {
    $today        = date('Y-m-d');
    
    $stmt_today = $db->prepare("SELECT COUNT(*) FROM registrations WHERE date(created_at,'localtime') = ?");
    $stmt_today->execute([$today]);
    $today_count = (int)$stmt_today->fetchColumn();
    
    $total_count  = (int)$db->query("SELECT COUNT(*) FROM enrolled")->fetchColumn();
    
    $stmt_unreturned = $db->prepare("SELECT COUNT(*) FROM registrations WHERE (returned_to IS NULL OR returned_to = '') AND (laptop_number NOT LIKE 'personal%' OR laptop_number IS NULL OR laptop_number = '') AND date(created_at,'localtime') = ?");
    $stmt_unreturned->execute([$today]);
    $unreturned = (int)$stmt_unreturned->fetchColumn();
    
    $session_days = (int)$db->query("SELECT COUNT(DISTINCT date(created_at,'localtime')) FROM registrations")->fetchColumn();
} catch (PDOException $e) {
    $today_count = $total_count = $unreturned = $session_days = 0;
}

// ── Excel export ──────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=ds4y_registrations_' . date('Y-m-d') . '.xls');

    $all  = $db->query("SELECT id, name, surname, laptop_number, given_by, returned_to, datetime(created_at,'localtime') as created_at, checkout_at FROM registrations ORDER BY created_at ASC")->fetchAll();
    $students = []; $dates = []; $matrix = [];
    foreach ($all as $row) {
        $sn   = trim($row['name'] . ' ' . $row['surname']);
        $date = date('Y-m-d', strtotime($row['created_at']));
        $students[$sn] = $sn; $dates[$date] = $date;
        $matrix[$sn][$date] = true;
    }
    ksort($students); ksort($dates);

    echo '<?xml version="1.0"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Worksheet ss:Name="Attendance Matrix"><Table>';
    echo '<Row><Cell><Data ss:Type="String">Student Name</Data></Cell>';
    foreach ($dates as $d) echo '<Cell><Data ss:Type="String">' . htmlspecialchars($d) . '</Data></Cell>';
    echo '</Row>';
    foreach ($students as $sn) {
        echo '<Row><Cell><Data ss:Type="String">' . htmlspecialchars($sn) . '</Data></Cell>';
        foreach ($dates as $d) echo '<Cell><Data ss:Type="String">' . (!empty($matrix[$sn][$d]) ? '✓' : '') . '</Data></Cell>';
        echo '</Row>';
    }
    echo '</Table></Worksheet>';
    echo '<Worksheet ss:Name="Full Register"><Table>';
    echo '<Row>' . implode('', array_map(fn($h) => '<Cell><Data ss:Type="String">' . $h . '</Data></Cell>',
        ['ID','First Name','Surname','Laptop','Issued By','Returned To','Check-in','Check-out'])) . '</Row>';
    foreach ($all as $r) {
        echo '<Row>';
        foreach ([$r['id'],$r['name'],$r['surname'],$r['laptop_number'],$r['given_by'],$r['returned_to']??'',$r['created_at'],$r['checkout_at']??''] as $v)
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$v) . '</Data></Cell>';
        echo '</Row>';
    }
    echo '</Table></Worksheet></Workbook>';
    exit;
}

$current_page = 'dashboard';
require_once __DIR__ . '/header.php';
?>

<!-- ── Page header ── -->
<div class="dashboard-header">
    <div>
        <h1>Attendance Dashboard</h1>
        <p>DS4Y Programme &mdash; <?= date('F Y') ?></p>
    </div>
    <div class="dashboard-actions">
        <a href="archive.php" class="btn btn-outline"><i class="fa-solid fa-box-archive"></i> Archive</a>
        <a href="dashboard.php?export=excel&date=<?= htmlspecialchars($date_filter) ?><?= $search ? '&search='.urlencode($search) : '' ?>"
           class="btn btn-outline"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
    </div>
</div>

<?php if (!empty($flash_ok)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($flash_ok) ?></span></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($flash_error) ?></span></div>
<?php endif; ?>

<!-- ── Stat cards ── -->
<div class="stats-grid">
    <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
        <div class="stat-details">
            <h3><?= $today_count ?></h3>
            <p>Today's sign-ins</p>
        </div>
    </div>
    <div class="stat-card stat-default">
        <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
        <div class="stat-details">
            <h3><?= $total_count ?></h3>
            <p>Total enrolled students</p>
        </div>
    </div>
    <div class="stat-card <?= $unreturned > 0 ? 'stat-amber' : 'stat-default' ?>">
        <div class="stat-icon"><i class="fa-solid fa-laptop-code"></i></div>
        <div class="stat-details">
            <h3><?= $unreturned ?></h3>
            <p>Laptops not returned today</p>
        </div>
    </div>
    <div class="stat-card stat-default">
        <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="stat-details">
            <h3><?= $session_days ?></h3>
            <p>Session days recorded</p>
        </div>
    </div>
</div>

<!-- ── Filter bar ── -->
<div class="filter-bar">
    <form method="GET" action="dashboard.php" style="display:contents;">
        <div class="search-box" style="flex:1;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="live-search" name="search"
                placeholder="Search by name, laptop, or issuer…"
                value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="date-filter-wrap">
            <label for="date-filter" style="font-size:.85rem;color:var(--text-secondary);margin-right:.5rem;">
                <i class="fa-solid fa-calendar-day"></i> Date:
            </label>
            <input type="date" id="date-filter" name="date"
                class="form-control date-input"
                value="<?= htmlspecialchars($date_filter) ?>">
        </div>

        <div style="display:flex;gap:.5rem;flex-shrink:0;">
            <button type="submit" class="btn btn-crimson btn-sm">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if ($date_filter !== date('Y-m-d') || !empty($search)): ?>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-outline btn-sm today-btn">
                <i class="fa-solid fa-star"></i> Today
            </a>
        </div>
    </form>
</div>

<!-- ── Table ── -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Check-in Time</th>
                <th>Student</th>
                <th>Laptop #</th>
                <th>Handed Out By</th>
                <th>Returned To</th>
                <th>Status</th>
                <th style="text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($registrations) > 0): ?>
            <?php foreach ($registrations as $reg): ?>
            <tr>
                <td style="font-size:.85rem;color:var(--text-secondary);">
                    <?= htmlspecialchars(date('H:i', strtotime($reg['created_at']))) ?>
                </td>
                <td><strong><?= htmlspecialchars($reg['name'] . ' ' . $reg['surname']) ?></strong></td>
                <td><code><?= htmlspecialchars($reg['laptop_number']) ?></code></td>
                <td><?= htmlspecialchars($reg['given_by']) ?></td>
                <td>
                    <?php
                    $is_personal_dash = (stripos($reg['laptop_number'] ?? '', 'personal') === 0);
                    if ($is_personal_dash): ?>
                        <span style="color:var(--text-secondary);font-size:.85rem;font-style:italic;">Own laptop</span>
                    <?php elseif (!empty($reg['returned_to'])): ?>
                        <?= htmlspecialchars($reg['returned_to']) ?>
                    <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:.85rem;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_personal_dash): ?>
                        <span class="badge badge-personal"><i class="fa-solid fa-laptop"></i> Personal</span>
                    <?php elseif (!empty($reg['returned_to'])): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Returned</span>
                    <?php else: ?>
                        <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Out</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <div style="display:flex;gap:.4rem;justify-content:flex-end;flex-wrap:wrap;">
                        <?php if (empty($reg['returned_to'])): ?>
                        <button type="button" class="btn btn-crimson btn-sm action-return"
                            data-id="<?= intval($reg['id']) ?>"
                            data-name="<?= h($reg['name'] . ' ' . $reg['surname']) ?>">
                            <i class="fa-solid fa-rotate-left"></i> Return
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline btn-sm delete-btn action-delete"
                            data-id="<?= intval($reg['id']) ?>"
                            data-name="<?= h($reg['name'] . ' ' . $reg['surname']) ?>">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <strong>No registrations found</strong>
                    <span>
                        <?php if (!empty($search)): ?>
                            No results match "<?= htmlspecialchars($search) ?>" for this date.
                        <?php elseif ($date_filter === date('Y-m-d')): ?>
                            No students have registered for today's session yet.
                        <?php else: ?>
                            No registrations recorded for <?= htmlspecialchars(date('j F Y', strtotime($date_filter))) ?>.
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<div style="font-size:.8rem;color:var(--text-secondary);margin-top:.75rem;text-align:right;">
    Showing <?= count($registrations) ?> record<?= count($registrations) !== 1 ? 's' : '' ?>
    for <?= htmlspecialchars(date('j F Y', strtotime($date_filter))) ?>
</div>

<!-- ── Return Modal ── -->
<div class="modal-backdrop" id="returnModalBackdrop">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-rotate-left"></i> Mark Laptop Returned</h3>
            <button class="modal-close" onclick="closeModal('return')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action"   value="update_returned">
            <input type="hidden" name="reg_id"   id="returnRegId">
            <div class="modal-body">
                <p class="modal-student-name" id="returnStudentName"></p>
                <div class="form-group">
                    <label for="returned_to_modal"><i class="fa-solid fa-user-check"></i> Received back by</label>
                    <input type="text" name="returned_to" id="returned_to_modal" class="form-control"
                        placeholder="Name of staff who received the laptop" required autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('return')">Cancel</button>
                <button type="submit" class="btn btn-crimson"><i class="fa-solid fa-circle-check"></i> Confirm Return</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Modal ── -->
<div class="modal-backdrop" id="deleteModalBackdrop">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-trash-can"></i> Delete Record</h3>
            <button class="modal-close" onclick="closeModal('delete')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action"  value="delete_registration">
            <input type="hidden" name="reg_id"  id="deleteRegId">
            <div class="modal-body">
                <p>Permanently delete the record for <strong id="deleteStudentName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('delete')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReturnModal(id, name) {
    document.getElementById('returnRegId').value = id;
    document.getElementById('returnStudentName').textContent = 'Student: ' + name;
    document.getElementById('returned_to_modal').value = '';
    document.getElementById('returnModalBackdrop').classList.add('active');
    setTimeout(() => document.getElementById('returned_to_modal').focus(), 80);
}
function openDeleteModal(id, name) {
    document.getElementById('deleteRegId').value = id;
    document.getElementById('deleteStudentName').textContent = name;
    document.getElementById('deleteModalBackdrop').classList.add('active');
}
function closeModal(type) {
    document.getElementById(type + 'ModalBackdrop').classList.remove('active');
}
document.querySelectorAll('.modal-backdrop').forEach(b => {
    b.addEventListener('click', e => { if (e.target === b) b.classList.remove('active'); });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop').forEach(b => b.classList.remove('active'));
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
