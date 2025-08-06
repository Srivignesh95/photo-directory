<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Fetch user details
        $user_stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();

        // Approve the user
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$user_id]);

        // Send primary user email
        $subject = "Welcome to St. Timothy's Photo Directory – Your Account Has Been Approved!";
        $message = "
            <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; color: #333;'>
                <h2 style='color: #2a7ae2;'>Hi " . htmlspecialchars($user['name']) . ",</h2>
                <p>Your account for St. Timothy's <strong>Photo Directory</strong> has been approved.</p>
                <p>
                    <a href='" . BASE_URL . "auth/login.php' style='background-color: #2a7ae2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a>
                </p>
            </div>
        ";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: no-reply@photodirectory.com\r\n";

        mail($user['email'], $subject, $message, $headers);

        // Also approve spouse (if exists)
        $memberCheck = $pdo->prepare("SELECT spouse_user_id FROM members WHERE user_id = ?");
        $memberCheck->execute([$user_id]);
        $member = $memberCheck->fetch();

        if ($member && $member['spouse_user_id']) {
            $spouse_id = $member['spouse_user_id'];

            $spouse_stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
            $spouse_stmt->execute([$spouse_id]);
            $spouse = $spouse_stmt->fetch();

            if ($spouse) {
                $temp_password = bin2hex(random_bytes(4));
                $hashed = password_hash($temp_password, PASSWORD_DEFAULT);

                $updateSpouse = $pdo->prepare("UPDATE users SET password = ?, status = 'approved' WHERE id = ?");
                $updateSpouse->execute([$hashed, $spouse_id]);

                $message_spouse = "
                    <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; color: #333;'>
                        <h2 style='color: #2a7ae2;'>Hi " . htmlspecialchars($spouse['name']) . ",</h2>
                        <p>Your account has been approved.</p>
                        <p><strong>Temporary Password: </strong>" . $temp_password . "</p>
                        <p><a href='" . BASE_URL . "auth/login.php' style='background-color: #2a7ae2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a></p>
                        <p>Please change your password after login.</p>
                    </div>
                ";

                mail($spouse['email'], $subject, $message_spouse, $headers);
            }
        }

    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
    }

    header("Location: pending.php");
    exit;
}

// Fetch all pending users with spouse info
$stmt = $pdo->query("
    SELECT u.*, m.spouse_user_id
    FROM users u
    LEFT JOIN members m ON u.id = m.user_id
    WHERE u.status = 'pending'
");
$pending_users = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-3">
        <div class="list-group">
            <a href="dashboard.php" class="list-group-item">Dashboard</a>
            <a href="pending.php" class="list-group-item active">Pending Approvals</a>
            <a href="members.php" class="list-group-item">Manage Members</a>
            <a href="add_member.php" class="list-group-item">Add Member</a>
        </div>
    </div>
    <div class="col-md-9">
        <h3>Pending Approvals</h3>
        <?php if (count($pending_users) === 0): ?>
            <p>No pending approvals.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Spouse Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending_users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td>
                            <?php echo $user['spouse_user_id'] ? 'Yes' : 'No'; ?>
                        </td>
                        <td>
                            <a href="?action=approve&id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                            <a href="?action=reject&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm">Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
