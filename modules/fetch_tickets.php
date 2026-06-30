<?php

include '../db.php';
require 'login_verification.php';
// Fetch all tickets for the admin
$tickets = $pdo->query("SELECT * FROM tickets")->fetchAll();

foreach ($tickets as $ticket): ?>
    <tr data-ticket-id="<?php echo htmlspecialchars($ticket['id']); ?>">
        <td><?php echo htmlspecialchars($ticket['id']); ?></td>
        <td><?php echo htmlspecialchars($ticket['title']); ?></td>
        <td><?php echo htmlspecialchars($ticket['description']); ?></td>
        <td class="status"><?php echo htmlspecialchars($ticket['status']); ?></td>
        <td><?php echo htmlspecialchars($ticket['created_at']); ?></td>
        <td>
            <form method="POST">
                <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['id']); ?>">
                <select name="status">
                    <option value="Pending" <?php echo $ticket['status'] == ' Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Processing" <?php echo $ticket['status'] == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="Done" <?php echo $ticket['status'] == 'Done' ? 'selected' : ''; ?>>Done</option>
                </select>
                <button type="submit">Update Status</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>