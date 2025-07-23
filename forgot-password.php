<?php
session_start();
require 'includes/conn.php';

$success = "";
$error = "";

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request.";
    } else {
        $email = trim($_POST['email']);

        // Basic brute-force protection (delay)
        sleep(2);

        if (empty($email)) {
            $error = "Please enter your email.";
        } else {
            // Check user exists and is verified + approved
            $stmt = $pdo->prepare("SELECT id, first_name FROM members WHERE email = ? AND is_verified = 1 AND approved = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate reset token and expiry (1 hour)
                $token = bin2hex(random_bytes(16));
                $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

                $update = $pdo->prepare("UPDATE members SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $update->execute([$token, $expires, $user['id']]);

                // Reset link (HTTPS)
                $resetLink = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=$token";

                // Email details
                $subject = "Reset your password - St. Timothy";
                $message = "Hi {$user['first_name']},\n\n";
                $message .= "Please click the link below to reset your password:\n$resetLink\n\n";
                $message .= "This link is valid for 1 hour.\n\n";
                $message .= "St. Timothy Church";

                // Secure headers
                $headers = "From: no-reply@sttimothy.ca\r\n";
                $headers .= "Reply-To: no-reply@sttimothy.ca\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                // Send email
                if (mail($email, $subject, $message, $headers)) {
                    $success = "✅ A reset link has been sent to your email.";
                } else {
                    $error = "Failed to send reset email. Please try again later.";
                }
            } else {
                $error = "No verified and approved user found with this email.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2>Forgot Password</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="mb-3">
            <label>Email address</label>
            <input type="email" name="email" required class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Send Reset Link</button>
        <a href="login.php" class="btn btn-link">Back to login</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
