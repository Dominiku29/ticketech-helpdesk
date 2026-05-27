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
                            ['email' => $ticket['employee_email']] // The employee's email
                        ],
                        'subject' => 'Resolved: ' . $ticket['issue_title']
                    ]
                ],
                'from' => [
                    'email' => 'bustillo1229@gmail.com', // MUST MATCH YOUR SENDGRID VERIFIED EMAIL
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
