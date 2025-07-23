<?php
session_start();
require 'includes/conn.php';
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_access'] == 1) {
        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM members WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Invalid credentials.";
    } else {
        if (!password_verify($password, $user['password'])) {
            $error = "Invalid credentials.";
        } elseif (!$user['is_verified']) {
            $error = "Please verify your email address before logging in.";
        } elseif (!$user['approved']) {
            $error = "Your account is awaiting admin approval.";
        } else {
            // Valid login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_access'] = $user['access'];

            // Redirect based on access level
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
    <h2 class="mb-4">Login</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">
        <div class="col-12">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" required class="form-control" id="email">
        </div>
        <div class="col-12">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" required class="form-control" id="password">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Login</button>
            <a href="signup.php" class="btn btn-link">Don't have an account?</a>
            <a href="forgot-password.php" class="btn btn-link">Forgot Password?</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
