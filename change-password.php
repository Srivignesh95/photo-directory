<?php
session_start();
require 'includes/conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE members SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);

        $success = "Password updated successfully.";
        if ($_SESSION['user_access'] == 1) {
            header("Location: dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4">Change Password</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
        </div>
        <div class="mb-3">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
