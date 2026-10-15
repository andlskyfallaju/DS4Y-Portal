<?php
// db.php – SQLite connection, schema init, and shared helpers

// --- Security Headers ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; frame-ancestors 'none';");

// --- Security Settings ---
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Set secure flag only if using HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// Set a strong, random session ID for new sessions
if (session_status() === PHP_SESSION_NONE && !isset($_COOKIE[session_name()])) {
    session_id(bin2hex(random_bytes(32)));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Session Timeout & Regeneration for Logged-In Users ---
if (isset($_SESSION['user_id'])) {
    // 15-minute inactivity timeout (900 seconds)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error_msg'] = "Your session has expired due to inactivity. Please log in again.";
        header("Location: login.php");
        exit;
    }
    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically every 5 minutes (300 seconds)
    if (!isset($_SESSION['created_time'])) {
        $_SESSION['created_time'] = time();
    } elseif (time() - $_SESSION['created_time'] > 300) {
        session_regenerate_id(true);
        $_SESSION['created_time'] = time();
    }
}
// -------------------------

$db_file = $db_file = 'C:/xampp/ds4y_data/ds4y.db';
$db = null;

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role     TEXT NOT NULL DEFAULT 'staff'
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS registrations (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        name          TEXT NOT NULL,
        surname       TEXT NOT NULL,
        laptop_number TEXT NOT NULL,
        given_by      TEXT NOT NULL,
        returned_to   TEXT DEFAULT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        checkout_at   DATETIME DEFAULT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS enrolled (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        name          TEXT NOT NULL COLLATE NOCASE,
        surname       TEXT NOT NULL COLLATE NOCASE,
        laptop_number TEXT DEFAULT NULL,
        email         TEXT DEFAULT NULL,
        phone_number  TEXT DEFAULT NULL,
        UNIQUE(name, surname)
    )");

    // Safe migrations (ignore if column already exists)
    foreach ([
        "ALTER TABLE registrations ADD COLUMN checkout_at DATETIME DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'staff'",
        "ALTER TABLE users ADD COLUMN full_name TEXT DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN email TEXT DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN phone_number TEXT DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN gender TEXT DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN location TEXT DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN id_number TEXT DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN dob DATE DEFAULT NULL",
        "ALTER TABLE enrolled ADD COLUMN marital_status TEXT DEFAULT NULL"
    ] as $migration) {
        try { $db->exec($migration); } catch (PDOException $e) { /* already exists */ }
    }

    // Seed default admin with a random first-run password
$count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count == 0) {
    $first_run_password = bin2hex(random_bytes(8)); // 16-char random hex string
    $hash = password_hash($first_run_password, PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
       ->execute(['admin', $hash, 'admin']);
    // Store it temporarily so it can be displayed once on the login page
    $_SESSION['first_run_password'] = $first_run_password;
} else {
    $db->exec("UPDATE users SET role = 'admin' WHERE username = 'admin'");
}

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ── CSRF helpers ────────────────────────────────────────────────────────────
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ── App-wide constants ───────────────────────────────────────────────────────
define('ARCHIVE_DAYS', 2); // registrations older than this many days move to archive

// ── Output Escaping Helper ──────────────────────────────────────────────────
function h($string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
