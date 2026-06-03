<?php
session_start();
include 'db.php';

// Auto-Clock Out the user before destroying the session
if (isset($_SESSION['tech_admin'])) {
    try {
        $stmt = $conn->prepare("UPDATE admin SET is_online = FALSE WHERE username = :username");
        $stmt->execute([':username' => $_SESSION['tech_admin']]);
    } catch (PDOException $e) {
        // Silently fail if DB connection drops during logout
    }
}

session_destroy();
header("Location: tech_login.php");
exit;
?>
