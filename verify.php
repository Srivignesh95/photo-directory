<?php
require 'includes/conn.php';

$token = $_GET['token'] ?? '';
$message = '';

if ($token) {
    // Check if token exists and not already verified
    $stmt = $pdo->prepare("SELECT id, is_verified FROM members WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_verified']) {
            $message = "<div class='alert alert-info'>✅ Email already verified.</div>";
        } else {
            // Update verification
            $update = $pdo->prepare("UPDATE members SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $update->execute([$user['id']]);
            $message = "<div class='alert alert-success'>🎉 Email verified successfully! Please wait for admin approval.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>❌ Invalid or expired verification token.</div>";
    }
} else {
    $message = "<div class='alert alert-warning'>⚠️ No token provided.</div>";
}
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2>Email Verification</h2>
    <?= $message ?>
    <a href="login.php" class="btn btn-primary mt-3">Go to Login</a>
</div>

<?php include 'includes/footer.php'; ?>
