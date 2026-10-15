<?php
// enrolled_edit.php — Admin edit form for individual enrolled records
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Must be admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = 'Please log in to continue.';
    header('Location: access_denied.php'); exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error_msg'] = 'Access denied. Administrators only.';
    header('Location: access_denied.php'); exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: enrolled.php'); exit; }

$error_msg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $_SESSION['error_msg'] = 'Security check failed.'; header('Location: enrolled_edit.php?id=' . $id); exit; }

    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $laptop_number = trim($_POST['laptop_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');

        if (strlen($name) > 100 || strlen($surname) > 100 || strlen($location) > 100 || strlen($email) > 150) {
    $error_msg = "One or more fields exceed the maximum allowed length.";
    } elseif (strlen($laptop_number) > 30 || strlen($id_number) > 20 || strlen($phone_number) > 20) {
    $error_msg = "One or more fields exceed the maximum allowed length.";
    }

    if (empty($name) || empty($surname)) {
        $error_msg = 'Name and surname are required.';
    } else {
        try {
            $stmt = $db->prepare("UPDATE enrolled SET name = ?, surname = ?, laptop_number = ?, email = ?, phone_number = ?, gender = ?, location = ?, id_number = ?, dob = ?, marital_status = ? WHERE id = ?");
            $stmt->execute([
                $name,
                $surname,
                $laptop_number ?: null,   // allow null — matches optional field on registration form
                $email         ?: null,
                $phone_number  ?: null,
                $gender        ?: null,
                $location      ?: null,
                $id_number     ?: null,
                $dob           ?: null,
                $marital_status ?: null,
                $id
            ]);
            $success = true;
        } catch (PDOException $e) {
            $error_msg = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Load record
$stmt = $db->prepare("SELECT * FROM enrolled WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) { header('Location: enrolled.php'); exit; }

$current_page = 'enrolled';
require_once __DIR__ . '/header.php';
?>

<div class="dashboard-header" style="margin-bottom:2rem;">
    <div>
        <h1>Edit Enrolled Student</h1>
        <p>Modify details for the enrolled student</p>
    </div>
    <div class="dashboard-actions">
        <a href="enrolled.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Record updated successfully.</div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($error_msg) ?></div>
<?php endif; ?>

<form method="POST" action="enrolled_edit.php?id=<?= intval($id) ?>">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-row-2">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="name" class="form-control" value="<?= h($record['name']) ?>" maxlength="100" required>
        </div>
        <div class="form-group">
            <label>Surname</label>
            <input type="text" name="surname" class="form-control" value="<?= h($record['surname']) ?>" maxlength="100" required>
        </div>
    </div>

    <div class="form-group">
        <label>Laptop Tag / Serial Number</label>
        <input type="text" name="laptop_number" class="form-control" value="<?= h($record['laptop_number']) ?>" maxlength="30">
    </div>

    <div class="form-row-2">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($record['email']) ?>" maxlength="150">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone_number" class="form-control" value="<?= h($record['phone_number']) ?>" maxlength="20">
        </div>
    </div>

    <div class="form-row-2">
        <div class="form-group">
            <label>Gender</label>
            <select name="gender" class="form-control">
                <option value="">Prefer not to say</option>
                <option value="male" <?= $record['gender']==='male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= $record['gender']==='female' ? 'selected' : '' ?>>Female</option>
                <option value="other" <?= $record['gender']==='other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" class="form-control" value="<?= h($record['location']) ?>" maxlength="100">
        </div>
    </div>

    <div class="form-row-2">
        <div class="form-group">
            <label>ID Number</label>
            <input type="text" name="id_number" class="form-control" value="<?= h($record['id_number']) ?>" maxlength="20">
        </div>
        <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="dob" class="form-control" value="<?= h($record['dob']) ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Marital Status</label>
        <select name="marital_status" class="form-control">
            <option value="">Prefer not to say</option>
            <option value="single" <?= $record['marital_status']==='single' ? 'selected' : '' ?>>Single</option>
            <option value="married" <?= $record['marital_status']==='married' ? 'selected' : '' ?>>Married</option>
            <option value="other" <?= $record['marital_status']==='other' ? 'selected' : '' ?>>Other</option>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-crimson">Save changes</button>
    </div>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
