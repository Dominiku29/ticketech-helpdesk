<?php
include 'db.php';

$success = null;
$error = null;

if (isset($_POST['submit_ticket'])) {
    $employee_name = trim($_POST['employee_name']);
    $department = trim($_POST['department']);
    $issue_title = trim($_POST['issue_title']);
    $issue_description = trim($_POST['issue_description']);
    $priority = $_POST['priority'];

    if (empty($employee_name) || empty($issue_title) || empty($issue_description)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Insert the new issue into our IT database
            $stmt = $conn->prepare("
                INSERT INTO support_tickets 
                (employee_name, department, issue_title, issue_description, priority, status) 
                VALUES 
                (:name, :dept, :title, :desc, :priority, 'Open')
            ");
            
            $stmt->execute([
                ':name' => $employee_name,
                ':dept' => $department,
                ':title' => $issue_title,
                ':desc' => $issue_description,
                ':priority' => $priority
            ]);
            
            $success = "Ticket submitted successfully! IT has been notified.";
            
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
    <title>Submit IT Ticket</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body>

<div class="kiosk-container">
    <div class="card">
        <h1>IT Help Desk</h1>
        <p class="subtitle">Submit a Support Ticket</p>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border-radius: 12px; padding: 40px 30px; text-align: center; margin-bottom: 20px;">
                
                <div style="background: #10b981; color: white; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px auto; font-weight: bold; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
                    ✓
                </div>
                
                <h2 style="color: #0f172a; font-weight: 800; margin-bottom: 10px;">Success!</h2>
                
                <p style="color: #475569; font-size: 1.05rem; margin-bottom: 25px;">
                    <?php echo htmlspecialchars($success); ?>
                </p>
                
                <p style="background: #f1f5f9; display: inline-block; padding: 8px 16px; border-radius: 20px; color: #64748b; font-size: 0.95rem; margin-bottom: 30px;">
                    Resetting form in <span id="countdown" style="font-weight: 800; color: #3b82f6; font-size: 1.2rem;">20</span> seconds...
                </p>
                
                <a href="submit_ticket.php" style="display: block; width: 100%; padding: 14px; background: white; color: #3b82f6; border: 2px solid #3b82f6; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 1rem;">
                    Submit Another Issue Now
                </a>
            </div>

            <script>
                let timeLeft = 20;
                const countdownElement = document.getElementById('countdown');
                
                const timer = setInterval(() => {
                    timeLeft--;
                    countdownElement.textContent = timeLeft;
                    
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        window.location.href = 'submit_ticket.php'; 
                    }
                }, 1000);
            </script>
            
        <?php else: ?>
            <form method="POST">
                <label>Your Name</label>
                <input type="text" name="employee_name" required>

                <label>Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="HR">Human Resources</option>
                    <option value="Finance">Finance</option>
                    <option value="Sales">Sales</option>
                    <option value="Operations">Operations</option>
                    <option value="Executive">Executive</option>
                </select>

                <label>Issue Title (Brief)</label>
                <input type="text" name="issue_title" required>

                <label>Describe the Problem</label>
                <input type="text" name="issue_description" required>

                <label>Priority Level</label>
                <select name="priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>

                <button type="submit" name="submit_ticket">Submit</button>
            </form>
        <?php endif; ?>
        
        <div class="links">
            <a href="tech_login.php">Admin Login</a>
        </div>
    </div>
</div>

</body>
</html>
