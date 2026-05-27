<?php
session_start();
include 'db.php';

if (!isset($_SESSION['tech_admin'])) {
    header("Location: tech_login.php");
    exit;
}

// 1. Handle Ticket Updates & AUTOMATED EMAIL (SENDGRID API)
if (isset($_POST['update_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $new_status = $_POST['status'];
    $resolution_notes = trim($_POST['resolution_notes']);
    $resolved_at = ($new_status === 'Resolved') ? date('Y-m-d H:i:s') : null;

    // Update the database first
    $update = $conn->prepare("UPDATE support_tickets SET status = :status, resolution_notes = :notes, resolved_at = :resolved_at WHERE id = :id");
    $update->execute([':status' => $new_status, ':notes' => $resolution_notes, ':resolved_at' => $resolved_at, ':id' => $ticket_id]);

    // --- MAGIC EMAIL TRIGGER (SENDGRID API) ---
    if ($new_status === 'Resolved') {
        $stmt = $conn->prepare("SELECT employee_name, employee_email, issue_title FROM support_tickets WHERE id = :id");
        $stmt->execute([':id' => $ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket && !empty($ticket['employee_email'])) {
            $api_key = getenv('SENDGRID_API_KEY');
            
            // Build the HTML email
            $html_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                    <div style='background: #3b82f6; padding: 20px; color: white; text-align: center;'>
                        <h2 style='margin: 0;'>Ticket Resolved</h2>
                    </div>
                    <div style='padding: 30px; background: #ffffff; color: #334155;'>
                        <p style='font-size: 16px;'>Hello <strong>{$ticket['employee_name']}</strong>,</p>
                        <p style='font-size: 16px;'>Good news! Your IT Support ticket regarding <strong>'{$ticket['issue_title']}'</strong> has been successfully resolved by our team.</p>";

            if (!empty($resolution_notes)) {
                $html_body .= "
                        <div style='background: #f8fafc; border-left: 4px solid #10b981; padding: 15px; margin: 25px 0;'>
                            <p style='margin: 0 0 10px 0; font-size: 14px; color: #64748b; text-transform: uppercase; font-weight: bold;'>Resolution Notes from IT:</p>
                            <p style='margin: 0; font-size: 15px; color: #0f172a;'>" . nl2br(htmlspecialchars($resolution_notes)) . "</p>
                        </div>";
            }

            $html_body .= "
                        <p style='font-size: 15px; color: #64748b; margin-top: 25px;'>Thank you for your patience,<br><strong>The TickeTech IT Team</strong></p>
                    </div>
                </div>
            ";

            // Prepare the JSON payload for SendGrid
            $post_data = json_encode([
                'personalizations' => [
                    [
                        'to' => [
                            ['email' => $ticket['employee_email']] 
                        ],
                        'subject' => 'Resolved: ' . $ticket['issue_title']
                    ]
                ],
                'from' => [
                    'email' => 'bustillo1229@gmail.com', // ⚠️ CHANGE THIS TO YOUR VERIFIED EMAIL!
                    'name' => 'TickeTech IT'
                ],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $html_body
                    ]
                ]
            ]);

            // Open a cURL connection to the SendGrid API
            $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ]);

            // Execute the API call and close connection
            $response = curl_exec($ch);
            curl_close($ch);
        }
    }
    // --- END MAGIC EMAIL TRIGGER ---

    header("Location: tech_dashboard.php");
    exit;
}

// 2. Handle Claim Ticket
if (isset($_POST['claim_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $tech_name = $_SESSION['tech_admin']; 

    $claim = $conn->prepare("UPDATE support_tickets SET assigned_to = :tech, status = 'In Progress' WHERE id = :id");
    $claim->execute([':tech' => $tech_name, ':id' => $ticket_id]);
    header("Location: tech_dashboard.php");
    exit;
}

// 3. Handle Unclaim Ticket
if (isset($_POST['unclaim_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $unclaim = $conn->prepare("UPDATE support_tickets SET assigned_to = NULL, status = 'Open' WHERE id = :id");
    $unclaim->execute([':id' => $ticket_id]);
    header("Location: tech_dashboard.php");
    exit;
}

// 4. Handle Archive Ticket (Soft Delete)
if (isset($_POST['archive_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $archive = $conn->prepare("UPDATE support_tickets SET is_archived = TRUE WHERE id = :id");
    $archive->execute([':id' => $ticket_id]);
    header("Location: tech_dashboard.php");
    exit;
}

// 5. Build the Dynamic Search & Filter Query
$current_view = $_GET['view'] ?? 'active';
$is_archived_param = ($current_view === 'archived') ? 'TRUE' : 'FALSE';

$where_clauses = ["is_archived = " . $is_archived_param]; 
$params = [];

if (!empty($_GET['search'])) {
    $where_clauses[] = "(employee_name ILIKE :search OR issue_title ILIKE :search)";
    $params[':search'] = '%' . trim($_GET['search']) . '%';
}
if (!empty($_GET['filter_status'])) {
    $where_clauses[] = "status = :status";
    $params[':status'] = $_GET['filter_status'];
}
if (!empty($_GET['filter_priority'])) {
    $where_clauses[] = "priority = :priority";
    $params[':priority'] = $_GET['filter_priority'];
}

$where_sql = implode(' AND ', $where_clauses);

$stmt = $conn->prepare("
    SELECT * FROM support_tickets
    WHERE $where_sql
    ORDER BY 
        CASE status WHEN 'Open' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END ASC,
        CASE priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 END ASC,
        created_at ASC
");
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_search = $_GET['search'] ?? '';
$current_status = $_GET['filter_status'] ?? '';
$current_priority = $_GET['filter_priority'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TickeTech - Dashboard</title>
    
    <meta property="og:title" content="TickeTech Admin">
    <meta property="og:description" content="Secure IT Dashboard.">
    <meta property="og:image" content="logo.png">

    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="logo.png">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; text-align: left; }
        th, td { padding: 15px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; color: #0f172a; font-weight: 800; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: white; display: inline-block; text-align: center; }
        .Critical { background: #ef4444; }
        .High { background: #f97316; }
        .Medium { background: #eab308; color: #1e293b; } 
        .Low { background: #3b82f6; }
        textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 5px; font-family: inherit; }
        .update-btn { padding: 10px 15px; margin-top: 10px; font-size: 0.9rem; cursor: pointer; }
        .unclaim-btn { background: white; color: #ef4444; border: 1px solid #ef4444; margin-top: 5px; }
        .unclaim-btn:hover { background: #fef2f2; }
        .archive-btn { background: #64748b; color: white; border: none; margin-top: 5px; }
        .archive-btn:hover { background: #475569; }
        .filter-bar { background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: center; border: 1px solid #e2e8f0; }
        .filter-bar input, .filter-bar select { padding: 12px; margin: 0; width: auto; flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; }
        .filter-bar button { margin: 0; padding: 12px 25px; width: auto; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .clear-btn { background: #cbd5e1; color: #334155; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: 600; text-align: center; }
        .clear-btn:hover { background: #94a3b8; color: white; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="card" style="max-width: 100%; padding: 30px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1 style="text-align: left; margin: 0;">IT Technician Portal</h1>
            <span style="background: #f8fafc; color: #475569; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 700; border: 1px solid #e2e8f0;">
                👤 Tech: <span style="color: #3b82f6;"><?php echo htmlspecialchars($_SESSION['tech_admin']); ?></span>
            </span>
        </div>
        <p class="subtitle" style="text-align: left; margin-top: 5px;">Manage and Resolve Support Tickets</p>
        <form method="GET" class="filter-bar">
            <select name="view" style="font-weight: bold; color: #0f172a;">
                <option value="active" <?php if ($current_view == 'active') echo 'selected'; ?>>Active Queue</option>
                <option value="archived" <?php if ($current_view == 'archived') echo 'selected'; ?>>Archived Tickets</option>
            </select>

            <input type="text" name="search" placeholder="Search name or issue..." value="<?php echo htmlspecialchars($current_search); ?>">
            
            <select name="filter_status">
                <option value="">All Statuses</option>
                <option value="Open" <?php if ($current_status == 'Open') echo 'selected'; ?>>Open</option>
                <option value="In Progress" <?php if ($current_status == 'In Progress') echo 'selected'; ?>>In Progress</option>
                <option value="Resolved" <?php if ($current_status == 'Resolved') echo 'selected'; ?>>Resolved</option>
            </select>

            <select name="filter_priority">
                <option value="">All Priorities</option>
                <option value="Critical" <?php if ($current_priority == 'Critical') echo 'selected'; ?>>Critical</option>
                <option value="High" <?php if ($current_priority == 'High') echo 'selected'; ?>>High</option>
                <option value="Medium" <?php if ($current_priority == 'Medium') echo 'selected'; ?>>Medium</option>
                <option value="Low" <?php if ($current_priority == 'Low') echo 'selected'; ?>>Low</option>
            </select>

            <button type="submit">Filter</button>
            <a href="tech_dashboard.php" class="clear-btn">Clear</a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Employee / Dept</th>
                    <th>Issue</th>
                    <th>Priority</th>
                    <th>Status & Resolution</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tickets) > 0): ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>#<?php echo substr($ticket['id'], -7); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($ticket['employee_name']); ?></strong><br>
                                <span style="font-size: 0.85rem; color: #64748b;"><?php echo htmlspecialchars($ticket['department']); ?></span><br>
                                <span style="font-size: 0.8rem; color: #3b82f6;"><?php echo htmlspecialchars($ticket['employee_email'] ?? 'No email provided'); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($ticket['issue_title']); ?></strong><br>
                                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($ticket['issue_description']); ?></span><br>
                                <span style="font-size: 0.75rem; color: #94a3b8;">Reported: <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></span>
                            </td>
                            <td><span class="badge <?php echo $ticket['priority']; ?>"><?php echo $ticket['priority']; ?></span></td>
                            <td style="min-width: 250px;">
                                <?php if (empty($ticket['assigned_to'])): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <button type="submit" name="claim_ticket" class="update-btn" style="background: #3b82f6; color: white; width: 100%; border: none; border-radius: 8px; font-weight: bold; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);">Claim Ticket</button>
                                    </form>
                                <?php else: ?>
                                    <div style="margin-bottom: 10px; font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 5px 10px; border-radius: 6px; display: inline-block;">
                                        👤 Assigned to: <strong><?php echo htmlspecialchars($ticket['assigned_to']); ?></strong>
                                    </div>
                                    <form method="POST" style="margin: 0; display: flex; flex-direction: column; gap: 5px;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <select name="status" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                            <option value="Open" <?php if ($ticket['status'] == 'Open') echo 'selected'; ?>>Open</option>
                                            <option value="In Progress" <?php if ($ticket['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                                            <option value="Resolved" <?php if ($ticket['status'] == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                        </select>
                                        <textarea name="resolution_notes" rows="2" placeholder="Enter resolution notes..."><?php echo htmlspecialchars($ticket['resolution_notes'] ?? ''); ?></textarea>
                                        <button type="submit" name="update_ticket" class="update-btn" style="background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: bold;">Save Update</button>
                                        
                                        <?php if ($ticket['status'] !== 'Resolved'): ?>
                                            <button type="submit" name="unclaim_ticket" class="update-btn unclaim-btn" style="border-radius: 8px; font-weight: bold;">Unclaim Ticket</button>
                                        <?php endif; ?>

                                        <?php if ($ticket['status'] === 'Resolved' && $ticket['is_archived'] == false): ?>
                                            <button type="submit" name="archive_ticket" class="update-btn archive-btn" style="border-radius: 8px; font-weight: bold;">Archive Ticket</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">No tickets found in this view.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="links" style="justify-content: space-between; margin-top: 20px;">
            <a href="submit_ticket.php" style="color: #64748b; font-weight: 600; text-decoration: none;">← Back to Employee Portal</a>
            <div>
                <a href="change_password.php" style="color: #3b82f6; font-weight: 600; text-decoration: none; margin-right: 20px;">Change Password</a>
                <a href="logout.php" style="color: #ef4444; font-weight: 600; text-decoration: none;">Secure Logout</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
