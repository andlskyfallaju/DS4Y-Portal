<?php
// init_db.php
require_once __DIR__ . '/db.php';

try {
    $username = 'admin';
    $password = 'password123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if the user already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if (!$stmt->fetch()) {
        $insert = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $insert->execute([$username, $hashed_password]);
        echo "Database initialized successfully! Default staff user created.\n";
        echo "Username: admin\n";
        echo "Password: password123\n";
    } else {
        echo "Database already initialized. Default admin user already exists.\n";
    }
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage());
}
?>
