<?php
// index.php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$success_data = $_SESSION['success_data'] ?? null;
$error_msg    = $_SESSION['error_msg']    ?? '';
unset($_SESSION['success_data'], $_SESSION['error_msg']);

// ── Rate limit settings ───────────────────────────────────────────────────────
define('RATE_LIMIT_MAX',    20);   // max submissions allowed
define('RATE_LIMIT_WINDOW', 300);  // within this many seconds (5 minutes)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $_SESSION['error_msg'] = "Security check failed. Please try again.";
        header("Location: index.php"); exit;
    }

    // ── Rate limit check ─────────────────────────────────────────────────────
    $now = time();

    // Initialise the tracker if it doesn't exist yet
    if (!isset($_SESSION['reg_attempts'])) {
        $_SESSION['reg_attempts'] = [];
    }

    // Drop any timestamps outside the current window
    $_SESSION['reg_attempts'] = array_filter(
        $_SESSION['reg_attempts'],
        fn($t) => ($now - $t) < RATE_LIMIT_WINDOW
    );

    if (count($_SESSION['reg_attempts']) >= RATE_LIMIT_MAX) {
        // Tell the user when the window resets
        $oldest       = min($_SESSION['reg_attempts']);
        $retry_in     = RATE_LIMIT_WINDOW - ($now - $oldest);
        $retry_mins   = ceil($retry_in / 60);
        $_SESSION['error_msg'] = "Too many registrations submitted. Please wait {$retry_mins} minute(s) before trying again.";
        error_log("Rate limit hit on registration form — session " . session_id());
        header("Location: index.php"); exit;
    }
    // ─────────────────────────────────────────────────────────────────────────

    $name          = trim($_POST['name']          ?? '');
    $surname       = trim($_POST['surname']       ?? '');
    $laptop_number = trim($_POST['laptop_number'] ?? '');
    $given_by      = trim($_POST['given_by']      ?? '');
    $returned_to   = trim($_POST['returned_to']   ?? '');

    if (strlen($name) > 100 || strlen($surname) > 100 || strlen($given_by) > 100 || strlen($returned_to) > 100) {
    $_SESSION['error_msg'] = "One or more fields exceed the maximum allowed length.";
    header("Location: index.php"); exit;
    }
       if (strlen($laptop_number) > 30) {
    $_SESSION['error_msg'] = "Laptop number exceeds the maximum allowed length.";
    header("Location: index.php"); exit;
    }

    // Basic required fields: name and surname. Laptop-related fields are optional.
    if (empty($name) || empty($surname)) {
        $_SESSION['error_msg'] = "Name and surname are required.";
    } else {
        try {
            // Detect personal laptop — starts with "personal" (case-insensitive)
            $is_personal = ($laptop_number !== '' && stripos($laptop_number, 'personal') === 0);

            // If a hub laptop was provided, require given_by and verify assignment
            if ($laptop_number !== '' && !$is_personal) {
                if (empty($given_by)) {
                    $_SESSION['error_msg'] = "Please provide who issued the laptop.";
                } else {
                    // Verify hub laptop is assigned to this specific student in enrolled table
                    $stmt = $db->prepare("SELECT id FROM enrolled WHERE name = ? AND surname = ? AND laptop_number = ?");
                    $stmt->execute([$name, $surname, $laptop_number]);
                    $laptop_record = $stmt->fetch();

                    if (!$laptop_record) {
                        $_SESSION['error_msg'] = "The laptop number provided is not assigned to this student. Please verify your name and laptop number.";
                    }
                }

            // Personal laptop — verify the entry matches what's on their enrolled record
            } elseif ($is_personal) {
                $stmt = $db->prepare("SELECT id FROM enrolled WHERE name = ? AND surname = ? AND LOWER(laptop_number) = LOWER(?)");
                $stmt->execute([$name, $surname, $laptop_number]);
                $personal_record = $stmt->fetch();

                if (!$personal_record) {
                    $_SESSION['error_msg'] = "The personal laptop entry does not match your enrolled record. Please check your initials and try again (e.g. Personal - LM).";
                }
            }

            // If no error so far, check same-day duplicate registration
            if (empty($_SESSION['error_msg'])) {
                $today = date('Y-m-d');
                $stmt = $db->prepare("SELECT id FROM registrations WHERE name = ? AND surname = ? AND DATE(created_at) = ?");
                $stmt->execute([$name, $surname, $today]);
                $existing_registration = $stmt->fetch();

                if ($existing_registration) {
                    $_SESSION['error_msg'] = "This student has already registered today. Each student can only register once per day.";
                }
            }

            // Proceed to insert even if laptop fields are empty — store empty strings for optional columns
            if (empty($_SESSION['error_msg'])) {
                $laptop_db    = $laptop_number !== '' ? $laptop_number : '';
                $given_by_db  = $given_by !== '' ? $given_by : '';
                $returned_db  = $returned_to !== '' ? $returned_to : '';

                $stmt = $db->prepare("INSERT INTO registrations (name, surname, laptop_number, given_by, returned_to) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $surname, $laptop_db, $given_by_db, $returned_db]);

                // Record this successful submission in the rate limit tracker
                $_SESSION['reg_attempts'][] = $now;

                $_SESSION['success_data'] = [
                    'name'          => $name . ' ' . $surname,
                    'laptop_number' => $laptop_db !== '' ? $laptop_db : 'N/A',
                    'given_by'      => $given_by_db !== '' ? $given_by_db : 'N/A',
                ];
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Failed to submit attendance: " . $e->getMessage();
            error_log("Registration INSERT failed: " . $e->getMessage());
        }
    }
    header("Location: index.php"); exit;
}

$body_class   = 'register-bg';
$current_page = 'index';
require_once __DIR__ . '/header.php';
?>

<div class="portal-card">

    <?php if ($success_data): ?>
        <!-- ── Success screen ── -->
        <div class="success-screen">
            <div class="success-icon-ring">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h2>You're registered!</h2>
            <p>Your attendance has been recorded for today's session.</p>

            <div class="success-details">
                <div class="success-row">
                    <span><i class="fa-solid fa-user"></i> Student</span>
                    <strong><?= htmlspecialchars($success_data['name']) ?></strong>
                </div>
                <div class="success-row">
                    <span><i class="fa-solid fa-laptop"></i> Laptop</span>
                    <strong><?= htmlspecialchars($success_data['laptop_number']) ?></strong>
                </div>
                <?php if ($success_data['given_by'] !== 'N/A'): ?>
                <div class="success-row">
                    <span><i class="fa-solid fa-handshake-angle"></i> Issued by</span>
                    <strong><?= htmlspecialchars($success_data['given_by']) ?></strong>
                </div>
                <?php endif; ?>
                <div class="success-row">
                    <span><i class="fa-solid fa-calendar-day"></i> Session</span>
                    <strong><?= date('l, j F Y') ?></strong>
                </div>
            </div>

            <a href="index.php" class="btn btn-crimson" style="width:100%;justify-content:center;margin-top:1.5rem;padding:.85rem;">
                <i class="fa-solid fa-plus"></i> Register another student
            </a>
        </div>

    <?php else: ?>
        <!-- ── Registration form ── -->
        <div class="portal-title-area">
            <h2>DS4Y Attendance</h2>
            <p>Register your attendance and laptop details below</p>
            <div class="session-date-badge">
                <i class="fa-solid fa-calendar-day"></i>
                <?= date('l, j F Y') ?>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-section-label">Student Details</div>
            <div class="form-row-2">
                <div class="form-group">
                    <label for="name"><i class="fa-solid fa-user"></i> First Name</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Panashe" required>
                </div>
                <div class="form-group">
                    <label for="surname"><i class="fa-solid fa-user"></i> Surname</label>
                    <input type="text" name="surname" id="surname" class="form-control" placeholder="e.g. Matizanadzo" required>
                </div>
            </div>

            <div class="form-section-label">Laptop Details (optional)</div>
            <div class="form-group">
                <label for="laptop_number"><i class="fa-solid fa-laptop"></i> Laptop Tag / Serial Number <span class="optional-label">optional</span></label>
                <input type="text" name="laptop_number" id="laptop_number" class="form-control" placeholder="e.g. IH-LAP-024 or Personal - LM">
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="given_by"><i class="fa-solid fa-handshake-angle"></i> Received from <span class="optional-label">optional</span></label>
                    <input type="text" name="given_by" id="given_by" class="form-control" placeholder="Staff who gave you the laptop">
                </div>
                <div class="form-group">
                    <label for="returned_to"><i class="fa-solid fa-rotate-left"></i> Returned to <span class="optional-label">optional</span></label>
                    <input type="text" name="returned_to" id="returned_to" class="form-control" placeholder="Staff who received it back">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-crimson">
                    <i class="fa-solid fa-signature"></i> Submit Registration
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="login-redirect" style="margin-top:2rem;">
            <p>Are you new to Impact Hub DS4Y? <a href="registration.php">Register Here</a></p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
