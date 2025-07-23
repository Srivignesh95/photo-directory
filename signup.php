<?php
require 'includes/conn.php';
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $contact_info = trim($_POST['contact_info']);
    $facebook = trim($_POST['facebook'] ?? '');
    $twitter = trim($_POST['twitter'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');

    // ✅ Validate at least one contact: email or phone
    if (empty($email) && empty($contact_info)) {
        $error = "Please provide at least an email or phone number.";
    } elseif (!empty($email) && ($password !== $confirmPassword)) {
        $error = "Passwords do not match.";
    } else {
        // ✅ Check for duplicate email if provided
        if (!empty($email)) {
            $checkStmt = $pdo->prepare("SELECT approved FROM members WHERE email = ?");
            $checkStmt->execute([$email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if ((int)$existing['approved'] === 0) {
                    $error = "Your profile is pending admin approval.";
                } else {
                    $error = "Email is already registered.";
                }
            }
        }

        if (empty($error)) {
            // ✅ Handle image upload
            $photoFile = $_FILES['photo'] ?? null;
            $photoName = 'default.jpg';

            if ($photoFile && $photoFile['error'] === 0) {
                $ext = pathinfo($photoFile['name'], PATHINFO_EXTENSION);
                $uniqueName = uniqid('user_', true) . '.' . $ext;
                $targetPath = 'assets/images/uploads/' . $uniqueName;

                if (move_uploaded_file($photoFile['tmp_name'], $targetPath)) {
                    $photoName = $uniqueName;
                }
            }

            // ✅ Handle password only if email is provided
            $hashedPassword = !empty($email) ? password_hash($password, PASSWORD_DEFAULT) : '';
            $token = !empty($email) ? bin2hex(random_bytes(16)) : null;

            // ✅ Insert user (self-signup: access=0, approved=0, is_verified depends on email)
            $stmt = $pdo->prepare("INSERT INTO members 
                (first_name, last_name, email, password, contact_info, designation, access, photo, facebook, twitter, instagram, linkedin, is_verified, approved, verification_token) 
                VALUES (?, ?, ?, ?, ?, 'Member', 0, ?, ?, ?, ?, ?, ?, 0, ?)");

            $stmt->execute([
                $first_name, 
                $last_name, 
                !empty($email) ? $email : null, 
                $hashedPassword, 
                !empty($contact_info) ? $contact_info : null, 
                $photoName,
                $facebook, 
                $twitter, 
                $instagram, 
                $linkedin, 
                !empty($email) ? 0 : null, // is_verified=0 if email present
                $token
            ]);

            // ✅ Send verification email only if email provided
            if (!empty($email)) {
                $subject = "Confirm your email - St. Timothy";
                $verifyLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=$token";
                $message = "Hi $first_name,\n\nPlease click the link below to verify your email:\n$verifyLink\n\nThanks,\nSt. Timothy Church";
                $headers = "From: no-reply@sttimothy.ca";

                mail($email, $subject, $message, $headers);
            }

            $success = "✅ Signup successful! ";
            if (!empty($email)) {
                $success .= "Please check your email to verify. Admin will review and approve your profile.";
            } else {
                $success .= "Your profile is pending admin approval.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4">Sign Up</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" name="first_name" required class="form-control">
        </div>
        <div class="col-md-6">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" name="last_name" required class="form-control">
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Email (optional if phone provided)</label>
            <input type="email" name="email" class="form-control">
        </div>
        <div class="col-md-6">
            <label for="contact_info" class="form-label">Contact Number (optional if email provided)</label>
            <input type="text" name="contact_info" class="form-control">
        </div>

        <div class="col-md-6">
            <label for="password" class="form-label">Password (required if email provided)</label>
            <input type="password" name="password" class="form-control" minlength="6">
        </div>
        <div class="col-md-6">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="6">
        </div>

        <div class="col-12">
            <label for="photo" class="form-label">Profile Photo</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
        </div>
        <div class="col-md-6"><label class="form-label">Facebook URL</label><input type="url" name="facebook" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Twitter URL</label><input type="url" name="twitter" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Instagram URL</label><input type="url" name="instagram" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">LinkedIn URL</label><input type="url" name="linkedin" class="form-control"></div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Register</button>
            <a href="login.php" class="btn btn-link">Already have an account?</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
