<?php
// archive.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$flash_ok    = $_SESSION['update_success'] ?? '';
$flash_error = $_SESSION['update_error']   ?? '';
unset($_SESSION['update_success'], $_SESSION['update_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $_SESSION['update_error'] = "Security check failed.";
        header("Location: archive.php"); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_returned') {
        $reg_id      = intval($_POST['reg_id'] ?? 0);
        $returned_to = trim($_POST['returned_to'] ?? '');  // Bug fixed: was [returned_to] (bare word)
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
        header("Location: archive.php"); exit;
    }

    if ($action === 'delete_registration') {
        $reg_id = intval($_POST['reg_id'] ?? 0);
        try {
            $db->prepare("DELETE FROM registrations WHERE id = ?")->execute([$reg_id]);
            $_SESSION['update_success'] = "Record deleted.";
        } catch (PDOException $e) {
            $_SESSION['update_error'] = "Delete failed: " . $e->getMessage();
        }
        header("Location: archive.php"); exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');

try {
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT id, name, surname, laptop_number, given_by, returned_to,
                                     datetime(created_at,'localtime') as created_at, checkout_at
                              FROM registrations
                              WHERE name LIKE ? OR surname LIKE ? OR laptop_number LIKE ?
                              ORDER BY created_at DESC");
        $like = "%$search%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $db->query("SELECT id, name, surname, laptop_number, given_by, returned_to,
                                   datetime(created_at,'localtime') as created_at, checkout_at
                            FROM registrations ORDER BY created_at DESC");
    }
    $all = $stmt->fetchAll();
} catch (PDOException $e) {
    $all = [];
    $flash_error = "Failed to load records: " . $e->getMessage();
}

// Group registrations by date
$grouped_regs = [];
foreach ($all as $reg) {
    $date = date('Y-m-d', strtotime($reg['created_at']));
    if (!isset($grouped_regs[$date])) {
        $grouped_regs[$date] = [];
    }
    $grouped_regs[$date][] = $reg;
}

// Archive = older than 2 days
$cutoff = strtotime('-2 days');
$archived_regs = array_filter($all, fn($r) => strtotime($r['created_at']) < $cutoff);
$archived_regs = array_values($archived_regs);

$current_page = 'archive';
require_once __DIR__ . '/header.php';
?>

<div class="dashboard-header" style="margin-bottom:2.5rem;">
    <div>
        <h1>DS4Y Archive</h1>
        <p>Historical student registrations older than 2 days</p>
    </div>
    <div class="dashboard-actions">
        <a href="dashboard.php" class="btn btn-outline"><i class="fa-solid fa-table-list"></i> Active Register</a>
        <a href="dashboard.php?export=excel" class="btn btn-crimson btn-sm"><i class="fa-solid fa-file-excel"></i> Export Register</a>
    </div>
</div>

<?php if (!empty($flash_ok)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($flash_ok) ?></span></div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($flash_error) ?></span></div>
<?php endif; ?>

<div class="filter-bar" style="margin-bottom:2rem;">
    <form method="GET" action="archive.php" style="display:contents;">
        <div class="search-box" style="flex:1;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="live-search" name="search"
                placeholder="Search archive by name or laptop…"
                value="<?= htmlspecialchars($search) ?>">
        </div>
        <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-crimson btn-sm"><i class="fa-solid fa-filter"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="archive.php" class="btn btn-crimson btn-sm"><i class="fa-solid fa-filter-circle-xmark"></i> Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (count($grouped_regs) > 0): ?>
    <?php foreach ($grouped_regs as $date => $registrations_on_date): ?>
    <div class="accordion-item">
        <button type="button" class="accordion-header" aria-expanded="false" data-date-group="<?= htmlspecialchars($date) ?>">
            <i class="fa-solid fa-calendar-day"></i>
            <span>Records for <?= htmlspecialchars(date("l, j F Y", strtotime($date))) ?></span>
            <span class="accordion-count"><?= count($registrations_on_date) ?></span>
            <i class="fa-solid fa-chevron-down accordion-icon"></i>
        </button>
        <div class="accordion-content collapsible" id="accordion-<?= htmlspecialchars($date) ?>">
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
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registrations_on_date as $reg): ?>
                        <tr>
                            <td style="font-size:.85rem;color:var(--text-secondary);">
                                <?= htmlspecialchars(date("H:i", strtotime($reg["created_at"]))) ?>
                            </td>
                            <td><strong><?= htmlspecialchars($reg["name"] . " " . $reg["surname"]) ?></strong></td>
                            <td><code><?= htmlspecialchars($reg["laptop_number"]) ?></code></td>
                            <td><?= htmlspecialchars($reg["given_by"]) ?></td>
                            <td>
                                <?php
                                $is_personal_arc = (stripos($reg["laptop_number"] ?? "", "personal") === 0);
                                if ($is_personal_arc): ?>
                                    <span style="color:var(--text-secondary);font-size:.85rem;font-style:italic;">Own laptop</span>
                                <?php elseif (!empty($reg["returned_to"])): ?>
                                    <?= htmlspecialchars($reg["returned_to"]) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);font-size:.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_personal_arc): ?>
                                    <span class="badge badge-personal"><i class="fa-solid fa-laptop"></i> Personal</span>
                                <?php elseif (!empty($reg["returned_to"])): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Returned</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Out</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex;gap:.4rem;justify-content:flex-end;flex-wrap:wrap;">
                                    <?php if (!$is_personal_arc && empty($reg["returned_to"])): ?>
                                    <button type="button" class="btn btn-crimson btn-sm" data-id="<?= intval($reg["id"]) ?>" data-name="<?= h($reg["name"] . " " . $reg["surname"]) ?>">
                                        <i class="fa-solid fa-rotate-left"></i> Return
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-crimson btn-sm" data-id="<?= intval($reg["id"]) ?>" data-name="<?= h($reg["name"] . " " . $reg["surname"]) ?>">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="font-size:.8rem;color:var(--text-secondary);margin-top:.75rem;text-align:right;padding-right:1rem;">
                Showing <?= count($registrations_on_date) ?> record<?= count($registrations_on_date) !== 1 ? "s" : "" ?> for this day
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="table-container" style="box-shadow:var(--shadow);">
        <table class="data-table">
            <tbody>
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fa-solid fa-box-archive"></i>
                        <strong>No archived records found</strong>
                        <span>
                            <?= !empty($search)
                                ? "No results match \"" . htmlspecialchars($search) . "\"."
                                : "Records older than 2 days will appear here."
                            ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div style="font-size:.8rem;color:var(--text-secondary);margin-top:.75rem;text-align:right;">
    Total archived records: <?= count($archived_regs) ?>
</div>

<!-- ── Return Modal ── -->
<div class="modal-backdrop" id="returnModalBackdrop">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-rotate-left"></i> Mark Laptop Returned</h3>
            <button class="modal-close" onclick="closeModal('return')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="archive.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action"   value="update_returned">
            <input type="hidden" name="reg_id"   id="returnRegId">
            <div class="modal-body">
                <p class="modal-student-name" id="returnStudentName"></p>
                <div class="form-group">
                    <label for="returned_to_modal"><i class="fa-solid fa-user-check"></i> Received back by</label>
                    <input type="text" name="returned_to" id="returned_to_modal" class="form-control"
                        placeholder="Name of staff who received the laptop" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-crimson" onclick="closeModal('return')">Cancel</button>
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
        <form method="POST" action="archive.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action"  value="delete_registration">
            <input type="hidden" name="reg_id"  id="deleteRegId">
            <div class="modal-body">
                <p>Permanently delete the record for <strong id="deleteStudentName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-crimson" onclick="closeModal('delete')">Cancel</button>
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

// Accordion handled by main.js
</script>

<?php require_once __DIR__ . '/footer.php'; ?>