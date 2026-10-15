<?php
// enrolled.php - list students currently enrolled
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Require login to view enrolled students
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

// Admin-only access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_msg'] = 'Access denied. Only administrators may view this page.';
    header('Location: access_denied.php'); exit;
}


// Excel export handler
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // only admins
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $_SESSION['error_msg'] = 'Access denied.';
        header('Location: access_denied.php'); exit;
    }

    try {
        $rows = $db->query("SELECT id, name, surname, laptop_number, email, phone_number, gender, location, id_number, dob, marital_status FROM enrolled ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=enrolled_export_' . date('Ymd') . '.xls');

        echo '<?xml version="1.0"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Worksheet ss:Name="Enrolled"><Table>';

        // header row
        $headers = ['ID','First Name','Surname','Laptop','Email','Phone','Gender','Location','ID Number','DOB','Marital Status'];
        echo '<Row>';
        foreach ($headers as $h) echo '<Cell><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
        echo '</Row>';

        foreach ($rows as $r) {
            echo '<Row>';
            foreach (['id','name','surname','laptop_number','email','phone_number','gender','location','id_number','dob','marital_status'] as $col) {
                $v = $r[$col] ?? '';
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$v) . '</Data></Cell>';
            }
            echo '</Row>';
        }

        echo '</Table></Worksheet></Workbook>';
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'Export failed: ' . $e->getMessage();
        header('Location: enrolled.php'); exit;
    }
}

try {
    $students = $db->query("SELECT id, name, surname, email, phone_number, laptop_number FROM enrolled ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $students = [];
    $error_msg = "Failed to load enrolled students: " . $e->getMessage();
}

$current_page = 'enrolled';
require_once __DIR__ . '/header.php';
?>

<div class="dashboard-header" style="margin-bottom:2rem;">
    <div>
        <h1>Enrolled Students</h1>
        <p>Students currently enrolled in the programme</p>
    </div>
    <div class="dashboard-actions">
        <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-pen-to-square"></i> Register Student</a>
        <a href="enrolled.php?export=excel" class="btn btn-outline"><i class="fa-solid fa-file-excel"></i> Export to Excel</a>
    </div>
</div>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= h($error_msg) ?></span>
    </div>
<?php endif; ?>

<div class="table-container" style="box-shadow:var(--shadow);">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Surname</th>
                <th>Email</th>
                <th>Phone Number</th>
                <th>Laptop</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($students)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--text-secondary);">No student records found.</td>
                </tr>
            <?php else: foreach ($students as $s): ?>
                <tr>
                    <td><?= intval($s['id']) ?></td>
                    <td><?= h($s['name']) ?></td>
                    <td><?= h($s['surname']) ?></td>
                    <td><?= h($s['email']) ?></td>
                    <td><?= h($s['phone_number']) ?></td>
                    <td><?= h($s['laptop_number']) ?></td>
                    <td><a href="enrolled_edit.php?id=<?= intval($s['id']) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i> Edit</a></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
