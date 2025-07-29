<?php
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Save token in DB
            $stmt = $pdo->prepare("UPDATE users SET reset_token=?, token_expiry=? WHERE email=?");
            $stmt->execute([$token, $expiry, $email]);

            // Send email
            $reset_link = BASE_URL . "auth/reset_password.php?token=$token";

            $subject = "Reset Your Password – Photo Directory";

            $message = "
                <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; color: #333;'>
                    <h2 style='color: #2a7ae2;'>Forgot Your Password?</h2>
                    <p>We received a request to reset your password for the <strong>Photo Directory</strong>.</p>
                    <p>Click the button below to create a new password:</p>

                    <p style='margin: 20px 0;'>
                        <a href='$reset_link' style='background-color: #2a7ae2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                    </p>

                    <p>If the button doesn't work, you can also use this link:</p>
                    <p><a href='$reset_link'>$reset_link</a></p>

                    <p><strong>Note:</strong> This link will expire in 1 hour for your security.</p>

                    <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>

                    <p style='font-size: 12px; color: #888;'>If you didn't request this, you can ignore this email or contact support.</p>
                    <p style='font-size: 12px; color: #888;'>– The Photo Directory Team</p>
                </div>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@photodirectory.com\r\n";

            mail($email, $subject, $message, $headers);

            $success = "Password reset link has been sent to your email.";
        } else {
            $error = "Email not found.";
        }
    } else {
        $error = "Please enter your email.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-dark text-white text-center"><h4>Forgot Password</h4></div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
