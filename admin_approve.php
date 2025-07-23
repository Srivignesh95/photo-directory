<?php
session_start();
require 'includes/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_access'] != 1) {
    header("Location: login.php");
    exit;
}

// Approve user if 'approve' is set
if (isset($_GET['approve'])) {
    $id = (int) $_GET['approve'];
    $stmt = $pdo->prepare("UPDATE members SET approved = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_approve.php?success=1");
    exit;
}

// Reject (delete) user if 'reject' is set
if (isset($_GET['reject'])) {
    $id = (int) $_GET['reject'];
    $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_approve.php?rejected=1");
    exit;
}

// Fetch pending approvals
$stmt = $pdo->query("SELECT * FROM members WHERE is_verified = 1 AND approved = 0");
$pendingUsers = $stmt->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4">Pending Member Approvals</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Member approved successfully!</div>
    <?php elseif (isset($_GET['rejected'])): ?>
        <div class="alert alert-warning">🚫 Member rejected and removed.</div>
    <?php endif; ?>

    <?php if (empty($pendingUsers)): ?>
        <div class="alert alert-info">🎉 No members awaiting approval.</div>
    <?php else: ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingUsers as $user): ?>
                    <tr>
                        <td><img src="assets/images/uploads/<?= htmlspecialchars($user['photo']) ?>" width="60" height="60" style="object-fit:cover;border-radius:50%;"></td>
                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['contact_info']) ?></td>
                        <td>
                            <a href="?approve=<?= $user['id'] ?>" class="btn btn-success btn-sm">Approve</a>
                            <a href="?reject=<?= $user['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure to reject this user?')">Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
