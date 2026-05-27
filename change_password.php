<?php
session_start();
include 'db.php';

// Security check: Must be logged in
if (!isset($_SESSION['tech_admin'])) {
    header("Location: tech_login.php");
    exit;
}

$tech_name = $_SESSION['tech_admin'];
$success = null;
$error = null;

if (isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match. Please try again.";
    } else {
        try {
            // Update the password in the database
            // Note: For a real production app, you would use password_hash() here!
            $stmt = $conn->prepare("UPDATE admin SET password = :password WHERE username = :username");
            $stmt->execute([
                ':password' => $new_password,
                ':username' => $tech_name
            ]);
            
            $success = "Password successfully updated!";
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
    <title>Change Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        input { width: 100%; padding: 12px; margin-top: 5px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #cbd5e1; }
        .btn-update { width: 100%; background: #3b82f6; color: white; padding: 14px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<div class="kiosk-container">
    <div class="card">
        <h1>Account Settings</h1>
        <p class="subtitle">Change password for <strong><?php echo htmlspecialchars($tech_name); ?></strong></p>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #10b981; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="text-align: left;">
            <label style="font-weight: 600; color: #334155;">New Password</label>
            <input type="password" name="new_password" required>

            <label style="font-weight: 600; color: #334155;">Confirm New Password</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" name="update_password" class="btn-update">Update Password</button>
        </form>

        <div class="links" style="margin-top: 30px;">
            <a href="tech_dashboard.php">← Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>