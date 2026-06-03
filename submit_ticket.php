<?php
include 'db.php';

$success = null;
$error = null;

if (isset($_POST['submit_ticket'])) {
    $employee_name = trim($_POST['employee_name']);
    $employee_email = trim($_POST['employee_email']);
    $department = trim($_POST['department']);
    $issue_title = trim($_POST['issue_title']);
    $issue_description = trim($_POST['issue_description']);

    $email_pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

    if (empty($employee_name) || empty($employee_email) || empty($issue_title) || empty($issue_description)) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $employee_name)) {
        $error = "Your Name can only contain letters and spaces. No numbers or symbols allowed.";
    } elseif (!filter_var($employee_email, FILTER_VALIDATE_EMAIL) || !preg_match($email_pattern, $employee_email)) {
        $error = "Please enter a valid email address (e.g., you@company.com).";
    } else {
        try {
            // NEW: Auto-Assignment Logic - ONLY assign to techs who are Clocked In (is_online = TRUE)
            $tech_stmt = $conn->query("
                SELECT a.username, COUNT(t.id) as ticket_count 
                FROM admin a 
                LEFT JOIN support_tickets t ON a.username = t.assigned_to AND t.status != 'Resolved'
                WHERE a.is_online = TRUE
                GROUP BY a.username 
                ORDER BY ticket_count ASC 
                LIMIT 1
            ");
            $assigned_tech_row = $tech_stmt->fetch(PDO::FETCH_ASSOC);
            $assigned_to = $assigned_tech_row ? $assigned_tech_row['username'] : null;

            $stmt = $conn->prepare("
                INSERT INTO support_tickets 
                (employee_name, employee_email, department, issue_title, issue_description, priority, status, assigned_to) 
                VALUES 
                (:name, :email, :dept, :title, :desc, 'Low', 'Open', :assigned_to)
            ");
            
            $stmt->execute([
                ':name' => $employee_name,
                ':email' => $employee_email,
                ':dept' => $department,
                ':title' => $issue_title,
                ':desc' => $issue_description,
                ':assigned_to' => $assigned_to
            ]);
            
            if ($assigned_to) {
                $success = "Ticket submitted successfully! It has been automatically assigned to our online IT team.";
            } else {
                $success = "Ticket submitted successfully! Our IT team is currently offline, but we will review it as soon as we clock in.";
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
    <title>TickeTech - IT Help Desk</title>
    
    <meta property="og:title" content="TickeTech">
    <meta property="og:description" content="Modern. Sleek. Digital IT Support.">
    <meta property="og:image" content="logo.png">

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
                <div style="background: #10b981; color: white; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px auto; font-weight: bold; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">✓</div>
                <h2 style="color: #0f172a; font-weight: 800; margin-bottom: 10px;">Success!</h2>
                <p style="color: #475569; font-size: 1.05rem; margin-bottom: 25px;"><?php echo htmlspecialchars($success); ?></p>
                <p style="background: #f1f5f9; display: inline-block; padding: 8px 16px; border-radius: 20px; color: #64748b; font-size: 0.95rem; margin-bottom: 30px;">
                    Resetting form in <span id="countdown" style="font-weight: 800; color: #3b82f6; font-size: 1.2rem;">20</span> seconds...
                </p>
                <a href="submit_ticket.php" style="display: block; width: 100%; padding: 14px; background: white; color: #3b82f6; border: 2px solid #3b82f6; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 1rem;">Submit Another Issue Now</a>
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
                <input type="text" name="employee_name" pattern="[a-zA-Z\s]+" title="Only letters and spaces are allowed." required>

                <label>Email Address (For Status Updates)</label>
                <input type="email" name="employee_email" placeholder="you@company.com" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address." required>

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
