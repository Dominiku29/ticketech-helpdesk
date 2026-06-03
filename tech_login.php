<?php
session_start();
include 'db.php';

$error = null;

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify credentials
    if ($user && $password === $user['password']) {
        session_regenerate_id(true);
        $_SESSION['tech_admin'] = $user['username'];

        // --- NEW: AUTO-CLOCK IN ---
        // Instantly set the admin to online/clocked-in so they start receiving tickets
        try {
            $clock_in_stmt = $conn->prepare("UPDATE admin SET is_online = TRUE WHERE username = :username");
            $clock_in_stmt->execute([':username' => $user['username']]);
        } catch (PDOException $e) {
            // Silently continue even if the status update fails, so they can still log in
        }
        // ---------------------------

        header("Location: tech_dashboard.php");
        exit;
    } else {
        $error = "Invalid IT credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IT Login - Help Desk</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body>

<div class="login-container">
    <div class="card login-card">
        <h1>IT Portal</h1>
        <p class="subtitle">Secure Technician Access</p>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit" name="login">Authenticate</button>
        </form>
        
        <div style="text-align: center; margin-top: 15px;">
            <a href="forgot_password.php" style="color: #3b82f6; font-size: 0.9rem; text-decoration: none; font-weight: 600;">Forgot Password?</a>
        </div>
    </div>
</div>

</body>
</html>
