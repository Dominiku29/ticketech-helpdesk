<?php
include 'db.php';

$success = null;
$error = null;
$display_id = null;

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
            // Auto-Assignment Logic - ONLY assign to techs who are Clocked In
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

            // Insert ticket and RETURN the new ID for the receipt
            $stmt = $conn->prepare("
                INSERT INTO support_tickets 
                (employee_name, employee_email, department, issue_title, issue_description, priority, status, assigned_to) 
                VALUES 
                (:name, :email, :dept, :title, :desc, 'Low', 'Open', :assigned_to)
                RETURNING id
            ");
            
            $stmt->execute([
                ':name' => $employee_name,
                ':email' => $employee_email,
                ':dept' => $department,
                ':title' => $issue_title,
                ':desc' => $issue_description,
                ':assigned_to' => $assigned_to
            ]);
            
            // Fetch the newly created ID to display on the receipt
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $ticket_id = $result['id'];
            $display_id = substr($ticket_id, -7);

            // --- SEND AUTOMATED EMAIL RECEIPT ---
            $api_key = getenv('SENDGRID_API_KEY');
            if ($api_key) {
                $formatted_name = htmlspecialchars(ucwords(strtolower($employee_name)));
                $formatted_issue = htmlspecialchars($issue_title);
                $tech_display = $assigned_to ? htmlspecialchars($assigned_to) : 'Pending (Unassigned)';
                
                $html_body = "
                <div style='font-family: \"Segoe UI\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f1f5f9; padding: 40px 20px;'>
                    <div style='background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);'>
                        <div style='background: #10b981; padding: 30px 20px; text-align: center;'>
                            <h2 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: 0.5px;'>Ticket Received</h2>
                        </div>
                        <div style='padding: 35px 30px; color: #334155;'>
                            <p style='font-size: 16px; margin-top: 0;'>Hello <strong>{$formatted_name}</strong>,</p>
                            <p style='font-size: 16px; line-height: 1.6;'>We have successfully received your IT Support request. Here is your official receipt for your records:</p>
                            
                            <div style='background: #f8fafc; border: 2px dashed #cbd5e1; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                                <p style='margin: 0 0 10px 0; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;'>Ticket Details</p>
                                <p style='margin: 5px 0; font-size: 15px;'><strong>Ticket ID:</strong> <span style='color: #3b82f6;'>#{$display_id}</span></p>
                                <p style='margin: 5px 0; font-size: 15px;'><strong>Issue:</strong> {$formatted_issue}</p>
                                <p style='margin: 5px 0; font-size: 15px;'><strong>Assigned Tech:</strong> {$tech_display}</p>
                            </div>
                            
                            <p style='font-size: 15px; color: #64748b; line-height: 1.6; margin-bottom: 0;'>Our team will review this shortly and update you once resolved.<br><br><strong style='color: #0f172a;'>TickeTech IT Team</strong></p>
                        </div>
                        <div style='background: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #e2e8f0;'>
                            <p style='margin: 0; font-size: 12px; color: #64748b;'>This is an automated receipt from the TickeTech Help Desk system.</p>
                        </div>
                    </div>
                </div>";

                $post_data = json_encode([
                    'personalizations' => [['to' => [['email' => $employee_email]], 'subject' => 'Support Ticket Receipt: #' . $display_id]],
                    'from' => ['email' => 'ticketech.support@gmail.com', 'name' => 'TickeTech IT'],
                    'content' => [['type' => 'text/html', 'value' => $html_body]]
                ]);

                $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
                curl_exec($ch);
                curl_close($ch);
            }
            
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
    <style>
        /* Styling for the new standard textarea */
        textarea.professional-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            color: #334155;
            background-color: #f8fafc;
            transition: all 0.3s ease;
            resize: vertical; /* Allows users to drag it taller if needed */
            margin-top: 5px;
        }
        textarea.professional-input:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }
    </style>
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
                <div style="background: #10b981; color: white; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 15px auto; font-weight: bold; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">✓</div>
                <h2 style="color: #0f172a; font-weight: 800; margin-bottom: 10px;">Ticket Submitted!</h2>
                
                <div style="background: white; border: 2px dashed #cbd5e1; padding: 25px; border-radius: 12px; margin: 25px 0; text-align: left; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <p style="margin: 0 0 15px 0; color: #64748b; font-size: 0.85rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Official Receipt (Please Save)</p>
                    <p style="margin: 8px 0; font-size: 1.05rem; color: #334155;"><strong>Ticket ID:</strong> <span style="color: #3b82f6; font-weight: bold; font-size: 1.2rem;">#<?php echo htmlspecialchars($display_id); ?></span></p>
                    <p style="margin: 8px 0; font-size: 1.05rem; color: #334155;"><strong>Name:</strong> <?php echo htmlspecialchars($employee_name); ?></p>
                    <p style="margin: 8px 0; font-size: 1.05rem; color: #334155;"><strong>Issue:</strong> <?php echo htmlspecialchars($issue_title); ?></p>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9; color: #10b981; font-weight: 600; font-size: 0.9rem;">
                        ✉️ An email confirmation receipt has been sent to <?php echo htmlspecialchars($employee_email); ?>.
                    </div>
                </div>
                
                <p style="color: #475569; font-size: 0.95rem; margin-bottom: 25px;"><?php echo htmlspecialchars($success); ?></p>
                <p style="background: #f1f5f9; display: inline-block; padding: 8px 16px; border-radius: 20px; color: #64748b; font-size: 0.95rem; margin-bottom: 30px;">
                    Resetting form in <span id="countdown" style="font-weight: 800; color: #3b82f6; font-size: 1.2rem;">30</span> seconds...
                </p>
                <a href="submit_ticket.php" style="display: block; width: 100%; padding: 14px; background: white; color: #3b82f6; border: 2px solid #3b82f6; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 1rem;">Submit Another Issue Now</a>
            </div>
            <script>
                let timeLeft = 30;
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
                <textarea name="issue_description" class="professional-input" rows="4" placeholder="Please provide as much detail as possible (e.g., error codes, steps to reproduce)..." required></textarea>

                <button type="submit" name="submit_ticket">Submit</button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 25px; opacity: 0.3;">
            <a href="tech_login.php" style="color: #64748b; font-size: 0.75rem; text-decoration: none; font-weight: 500; cursor: default;">v1.0</a>
        </div>

    </div>
</div>

</body>
</html>
