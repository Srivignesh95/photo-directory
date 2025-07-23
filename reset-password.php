<?php
session_start();
require 'includes/conn.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$showForm = true;

// Step 1: Validate the token and expiry
if (!$token) {
    $error = "Missing or invalid reset token.";
    $showForm = false;
} else {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Invalid or expired token.";
        $showForm = false;
    }
}

// Step 2: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request.";
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password & remove token
            $update = $pdo->prepare("UPDATE members SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $update->execute([$hashed, $user['id']]);

            // ✅ Auto-login after reset
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_access'] = $user['access'];
            $_SESSION['user_name'] = $user['first_name'] ?? 'User';

            // Redirect based on role
            if ($user['access'] == 1) {
                header("Location: dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2>Reset Your Password</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="mb-3">
            <label>New Password</label>
            <input type="password" name="new_password" required class="form-control" minlength="6">
        </div>
        <div class="mb-3">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required class="form-control" minlength="6">
        </div>
        <button type="submit" class="btn btn-success">Reset Password</button>
        <a href="login.php" class="btn btn-link">Back to login</a>
    </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
