<?php
// registration.php
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
        header("Location: registration.php"); exit;
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
        header("Location: registration.php"); exit;
    }
    // ─────────────────────────────────────────────────────────────────────────

    $name          = trim($_POST['name']          ?? '');
    $surname       = trim($_POST['surname']       ?? '');
    $laptop_number = trim($_POST['laptop_number'] ?? '');
    $phone_number  = trim($_POST['phone_number']  ?? '');
    $email         = trim($_POST['email']         ?? '');
    $gender        = trim($_POST['gender']        ?? '');
    $location      = trim($_POST['location']      ?? '');
    $id_number     = trim($_POST['id_number']     ?? '');
    $dob           = trim($_POST['dob']           ?? '');
    $marital_status= trim($_POST['marital_status']?? '');

        if (strlen($name) > 100 || strlen($surname) > 100 || strlen($location) > 100 || strlen($email) > 150) {
    $_SESSION['error_msg'] = "One or more fields exceed the maximum allowed length.";
    header("Location: registration.php"); exit;
    }
        if (strlen($laptop_number) > 30 || strlen($id_number) > 20 || strlen($phone_number) > 20) {
    $_SESSION['error_msg'] = "One or more fields exceed the maximum allowed length.";
    header("Location: registration.php"); exit;
    }

    if (empty($name) || empty($surname) || empty($phone_number) || empty($email)) {
        $_SESSION['error_msg'] = "All required fields must be filled in.";
    } else {
        try {
            // 1) Prevent duplicate name+surname (case-insensitive)
            $stmt = $db->prepare("SELECT id FROM enrolled WHERE name = ? AND surname = ? COLLATE NOCASE");
            $stmt->execute([$name, $surname]);
            $existing = $stmt->fetch();

            if ($existing) {
                $_SESSION['error_msg'] = "A student with this name is already enrolled.";
            } else {
                // 2) Prevent duplicate email
                if (!empty($email)) {
                    $stmt = $db->prepare("SELECT 1 FROM enrolled WHERE LOWER(email) = LOWER(?)");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_msg'] = "Email already in use.";
                    }
                }

                // 3) Prevent duplicate phone
                if (empty($_SESSION['error_msg']) && !empty($phone_number)) {
                    $stmt = $db->prepare("SELECT 1 FROM enrolled WHERE phone_number = ?");
                    $stmt->execute([$phone_number]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_msg'] = "Phone number already in use.";
                    }
                }

                // 4) Prevent duplicate ID number
                if (empty($_SESSION['error_msg']) && !empty($id_number)) {
                    $stmt = $db->prepare("SELECT 1 FROM enrolled WHERE id_number = ?");
                    $stmt->execute([$id_number]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_msg'] = "ID number already in use.";
                    }
                }

                // 5) Prevent duplicate laptop
                if (empty($_SESSION['error_msg'])) {
                    $stmt = $db->prepare("SELECT 1 FROM enrolled WHERE laptop_number = ?");
                    $stmt->execute([$laptop_number]);
                    if ($stmt->fetch()) {
                        $_SESSION['error_msg'] = "Laptop number already in use.";
                    }
                }

                // All uniqueness checks passed -> insert (include new fields)
                if (empty($_SESSION['error_msg'])) {
                    $stmt = $db->prepare("INSERT INTO enrolled (name, surname, email, phone_number, laptop_number, gender, location, id_number, dob, marital_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $surname, $email, $phone_number, $laptop_number, $gender ?: null, $location ?: null, $id_number ?: null, $dob ?: null, $marital_status ?: null]);

                    // Record this successful submission in the rate limit tracker
                    $_SESSION['reg_attempts'][] = $now;

                    $_SESSION['success_data'] = [
                        'name'          => $name . ' ' . $surname,
                        'laptop_number' => !empty($laptop_number) ? $laptop_number : 'N/A',
                        'email'         => $email,
                        'phone_number'  => $phone_number,
                    ];
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Failed to submit registration: " . $e->getMessage();
            error_log("Registration INSERT failed: " . $e->getMessage());
        }
    }
    header("Location: registration.php"); exit;
}

$body_class   = 'enroll-bg';
$current_page = 'registration';
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
            <p>Your registration has been received.</p>

            <div class="success-details">
                <div class="success-row">
                    <span><i class="fa-solid fa-user"></i> Student</span>
                    <strong><?= htmlspecialchars($success_data['name']) ?></strong>
                </div>
                <div class="success-row">
                    <span><i class="fa-solid fa-laptop"></i> Laptop</span>
                    <strong><?= htmlspecialchars($success_data['laptop_number']) ?></strong>
                </div>
                <div class="success-row">
                    <span><i class="fa-solid fa-envelope"></i> Email</span>
                    <strong><?= htmlspecialchars($success_data['email']) ?></strong>
                </div>
                <div class="success-row">
                    <span><i class="fa-solid fa-phone"></i> Phone</span>
                    <strong><?= htmlspecialchars($success_data['phone_number']) ?></strong>
                </div>
                <div class="success-row">
                    <span><i class="fa-solid fa-calendar-day"></i> Session</span>
                    <strong><?= date('l, j F Y') ?></strong>
                </div>
            </div>

            <a href="registration.php" class="btn btn-crimson" style="width:100%;justify-content:center;margin-top:1.5rem;padding:.85rem;">
                <i class="fa-solid fa-plus"></i> Register another student
            </a>
        </div>

    <?php else: ?>
        <!-- ── Registration form ── -->
        <div class="portal-title-area">
            <h2>DS4Y Registration</h2>
            <p>Fill in your details to register for the DS4Y program</p>
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

        <form action="registration.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-section-label">Student Details</div>
            <div class="form-row-2">
               <div class="form-group">
                   <label for="name"><i class="fa-solid fa-user"></i> First Name<span class="required-star">*</span></label>
                   <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Panashe" maxlength="100" required>
               </div>
               <div class="form-group">
                   <label for="surname"><i class="fa-solid fa-user"></i> Surname<span class="required-star">*</span></label>
                   <input type="text" name="surname" id="surname" class="form-control" placeholder="e.g. Matizanadzo" maxlength="100" required>
               </div>
            </div>

            <div class="form-group">
               <label for="laptop_number"><i class="fa-solid fa-laptop"></i> Laptop Tag / Serial Number <span class="optional-label">optional</span></span></label>
               <input type="text" name="laptop_number" id="laptop_number" class="form-control" placeholder="e.g. LPT0000" maxlength="30">
            </div>

            <div class="form-group">
               <label for="email"><i class="fa-solid fa-envelope"></i> Email<span class="required-star">*</span></label>
               <input type="email" name="email" id="email" class="form-control" placeholder="e.g. panashe@example.com" maxlength="150" required>
            </div>

            <div class="form-group">
                <label for="phone_number"><i class="fa-solid fa-phone"></i> Phone Number<span class="required-star">*</span></label>
                <input type="tel" name="phone_number" id="phone_number" class="form-control" placeholder="e.g. +263 12 345 6789" maxlength="20" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="gender"><i class="fa-solid fa-venus-mars"></i> Gender <span class="required-star">*</span></label>
                    <select name="gender" id="gender" class="form-control" required>
                        <option value="">Prefer not to say</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="location"><i class="fa-solid fa-location-dot"></i> City/Town <span class="required-star">*</span></label>
                    <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Harare CBD" maxlength="100" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label for="id_number"><i class="fa-solid fa-id-card"></i> ID Number <span class="required-star">*</span></label>
                    <input type="text" name="id_number" id="id_number" class="form-control" placeholder="e.g. 22-1234567A89" maxlength="20" required>
                </div>
                <div class="form-group">
                    <label for="dob"><i class="fa-solid fa-calendar"></i> Date of Birth <span class="required-star">*</span></label>
                    <input type="date" name="dob" id="dob" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label for="marital_status"><i class="fa-solid fa-ring"></i> Marital Status <span class="required-star">*</span></label>
                <select name="marital_status" id="marital_status" class="form-control" required>
                    <option value="">Prefer not to say</option>
                    <option value="single">Single</option>
                    <option value="married">Married</option>
                    <option value="other">Other</option>
                </select>
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
            <p>Already a part of Impact Hub DS4Y? <a href="index.php">Sign In</a></p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
