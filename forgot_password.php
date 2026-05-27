<?php
include 'db.php';

$success = null;
$error = null;

// The Master PIN for your Demo (Change this if you want!)
$master_pin = "1234";

if (isset($_POST['reset_password'])) {
    $username = trim($_POST['username']);
    $pin = trim($_POST['pin']);
    $new_password = $_POST['new_password'];

    if (empty($username) || empty($pin) || empty($new_password)) {
        $error = "Please fill in all fields.";
    } elseif ($pin !== $master_pin) {
        $error = "Incorrect Master Security PIN.";
    } else {
        try {
            // First, check if the username actually exists in the database
            $check = $conn->prepare("SELECT * FROM admin WHERE username = :username");
            $check->execute([':username' => $username]);
            
            if ($check->rowCount() > 0) {
                // User exists, so update their password
                $update = $conn->prepare("UPDATE admin SET password = :password WHERE username = :username");
                $update->execute([
                    ':password' => $new_password,
                    ':username' => $username
                ]);
                $success = "Password successfully reset! You can now log in.";
            } else {
                $error = "Username not found in the system.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        input { width: 100%; padding: 12px; margin-top: 5px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #cbd5e1; }
        .btn-reset { width: 100%; background: #ef4444; color: white; padding: 14px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-reset:hover { background: #dc2626; }
    </style>
</head>
<body>

<div class="kiosk-container">
    <div class="card">
        <h1>Account Recovery</h1>
        <p class="subtitle">Reset your admin access</p>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #10b981; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
            <a href="tech_login.php" style="display: block; width: 100%; padding: 14px; background: #3b82f6; color: white; border-radius: 8px; font-weight: bold; text-decoration: none; text-align: center;">Go to Login</a>
        <?php else: ?>
            <form method="POST" style="text-align: left;">
                <label style="font-weight: 600; color: #334155;">Admin Username</label>
                <input type="text" name="username" placeholder="e.g., Danilo" required>

                <label style="font-weight: 600; color: #334155;">Master Security PIN</label>
                <input type="password" name="pin" placeholder="Enter the 4-digit PIN" required>

                <label style="font-weight: 600; color: #334155;">New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password" required>

                <button type="submit" name="reset_password" class="btn-reset">Force Reset Password</button>
            </form>

            <div class="links" style="margin-top: 30px; text-align: center;">
                <a href="tech_login.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>