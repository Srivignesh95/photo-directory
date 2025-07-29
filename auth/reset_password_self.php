<?php
require_once __DIR__ . '/../includes/header.php';
checkAuth();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password'])) {
            $error = "Current password is incorrect.";
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed, $user_id]);
            $success = "Password has been updated.";
        }
    }
}
?>

<div class="container mt-4">
    <div class="card shadow p-4">
        <h4 class="mb-4">Reset Your Password</h4>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Update Password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
