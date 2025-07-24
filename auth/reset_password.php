<?php
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = $_GET['token'];

// Check if token is valid and not expired
$stmt = $pdo->prepare("SELECT id, token_expiry FROM users WHERE reset_token=?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("Invalid or expired token.");
}

if (strtotime($user['token_expiry']) < time()) {
    die("Token expired. Please request a new reset link.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password && $confirm_password) {
        if ($password === $confirm_password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password & clear token
            $stmt = $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, token_expiry=NULL WHERE id=?");
            $stmt->execute([$hashed_password, $user['id']]);

            $success = "Password reset successful! <a href='login.php'>Login now</a>";
        } else {
            $error = "Passwords do not match.";
        }
    } else {
        $error = "Please fill all fields.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-dark text-white text-center"><h4>Reset Password</h4></div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <?php if (!$success): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
