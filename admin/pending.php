<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();
if (!isAdmin()) {
    die("Access Denied");
}

// Approve or Reject
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$user_id]);

        // Fetch user email
        $user_stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();

        // Send approval email
        $subject = "Your Account is Approved";
        $message = "
            <h2>Congratulations!</h2>
            <p>Your account has been approved. You can now login and access the directory.</p>
            <p><a href='".BASE_URL."auth/login.php'>Login Now</a></p>
        ";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: no-reply@photodirectory.com" . "\r\n";

        mail($user['email'], $subject, $message, $headers);

    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
    }

    header("Location: pending.php");
    exit;
}

// Fetch pending users
$stmt = $pdo->query("SELECT * FROM users WHERE status = 'pending'");
$pending_users = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-3">
        <div class="list-group">
            <a href="dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
            <a href="pending.php" class="list-group-item list-group-item-action active">Pending Approvals</a>
            <a href="members.php" class="list-group-item list-group-item-action">Manage Members</a>
            <a href="add_member.php" class="list-group-item list-group-item-action">Add Member</a>
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
